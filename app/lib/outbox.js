// Changes made with no internet — highlights, notes written, pinned or deleted — wait here and are sent in order as
// soon as there is a connection (when the internet comes back, when the app comes forward, every 15 seconds while
// something waits, and right after you make a change).
// Each change is already applied on the phone, so nothing looks different while it waits. Replaying a change is safe:
// the server keys highlights by verse and notes by their context, and the newest version of a note replaces older
// waiting ones. If the same note was changed on another device, the last one sent wins.
//
// The queue belongs to one member: if someone else signs in on this phone, it and the cached notes are cleared.
import { useEffect, useSyncExternalStore } from 'react';
import { AppState, Platform } from 'react-native';
import api, { errorMessage } from './api';
import { clearHighlights } from './bibleStore';
import { readJson, removeFile, serial, writeJson } from './localFiles';
import { isRejected } from './net';
import * as notes from './notesStore';

const QUEUE = 'outbox.json';
const OWNER = 'owner.json';
const MAX_WAITING = 500;

let queue = null; // [{ id, type, key, payload, at }]
let snapshot = { pending: 0, flushing: false };
const listeners = new Set();
let running = null;

async function load() {
  if (!queue) queue = (await readJson(QUEUE)) ?? [];
  return queue;
}

function emit() {
  const next = { pending: queue?.length ?? 0, flushing: !!running };
  if (next.pending !== snapshot.pending || next.flushing !== snapshot.flushing) snapshot = next;
  listeners.forEach((l) => l());
  return serial('outbox', () => writeJson(QUEUE, queue));
}

/** `{ pending, flushing }` — re-renders when the number of waiting changes changes. */
export const useOutbox = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => snapshot);

/** Waiting changes of one type (for screens that show them on top of what the server last said). */
export async function waiting(type) {
  return (await load()).filter((op) => op.type === type);
}

// ── Doing the changes ───────────────────────────────────────────────────────────────────────────────────

async function perform(op) {
  const { type, key, payload } = op;

  if (type === 'highlight') {
    await api.post('/bible/highlights', payload);
    return;
  }

  const rec = await notes.getRaw(key);

  if (type === 'note.save') {
    if (!rec || rec.deleted) return;
    const sentAt = rec.updated_at;
    let res = null;
    if (rec.id) {
      try {
        res = await api.patch(`/notes/${rec.id}`, { title: rec.title || null, body: rec.body ?? '', color: rec.color });
      } catch (e) {
        if (e.response?.status !== 404) throw e; // deleted elsewhere: write it again below
      }
    }
    res ??= await api.post('/notes', { context: key, reference: rec.reference, church_id: rec.church_id ?? undefined, title: rec.title || null, body: rec.body ?? '', color: rec.color });
    await notes.applyServerNote(key, res.data.data, sentAt);
    return;
  }

  // Pinning and deleting need the server's id for the note.
  let serverNote = null;
  if (!rec?.id || type === 'note.pin') serverNote = (await api.get('/notes/context', { params: { context: key } })).data.data;
  const id = rec?.id ?? serverNote?.id;
  if (!id) {
    if (type === 'note.delete') await notes.dropNote(key);
    return;
  }

  if (type === 'note.pin') {
    if (!!serverNote?.pinned !== !!payload.pinned) await api.post(`/notes/${id}/pin`);
    await notes.setServerId(key, id);
    if (!queue.some((o) => o.key === key && o.id !== op.id)) await notes.markClean(key);
    return;
  }

  if (type === 'note.delete') {
    try {
      await api.delete(`/notes/${id}`);
    } catch (e) {
      if (e.response?.status !== 404) throw e;
    }
    if ((await notes.getRaw(key))?.deleted) await notes.dropNote(key);
  }
}

/**
 * Sends what is waiting, oldest first. Stops (keeping the rest) when there is no connection or the server is busy;
 * a change the server refuses for good (for example a note that is too long) is dropped and reported.
 * Resolves `{ sent: [ids], failed: [{ id, error }], stalled }`.
 */
export function flush() {
  if (running) return running;
  running = (async () => {
    const result = { sent: [], failed: [], stalled: false };
    await load();
    emit();
    while (queue.length) {
      const op = queue[0];
      try {
        await perform(op);
        result.sent.push(op.id);
      } catch (e) {
        if (isRejected(e)) result.failed.push({ id: op.id, error: errorMessage(e) });
        else {
          result.stalled = true;
          break;
        }
      }
      queue = queue.filter((o) => o.id !== op.id);
      emit();
    }
    return result;
  })().finally(() => {
    running = null;
    emit();
  });
  emit();
  return running;
}

/**
 * Queues a change and tries to send it straight away. Resolves `{ queued }`: true when it is still waiting (no
 * connection), false when the server has it. Throws when the server refused it.
 */
export async function submit(type, payload, key = null) {
  await load();
  const op = { id: `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`, type, key, payload, at: Date.now() };

  // The newest version of a note replaces older waiting ones; deleting makes waiting edits pointless.
  if (type === 'note.save' || type === 'note.pin') queue = queue.filter((o) => !(o.type === type && o.key === key));
  if (type === 'note.save') queue = queue.filter((o) => !(o.type === 'note.delete' && o.key === key));
  if (type === 'note.delete') queue = queue.filter((o) => !(o.key === key && o.type.startsWith('note.')));
  if (queue.length >= MAX_WAITING) queue.shift();
  queue.push(op);
  await emit();

  const result = await flush();
  const failed = result.failed.find((f) => f.id === op.id);
  if (failed) throw new Error(failed.error);
  return { queued: queue.some((o) => o.id === op.id) };
}

// ── Whose changes these are ─────────────────────────────────────────────────────────────────────────────

/** Forgets everything kept for a member on this phone: waiting changes, notes, highlight colours. */
export async function forgetEverything() {
  queue = [];
  removeFile(QUEUE);
  removeFile(OWNER);
  await notes.clearNotes();
  await clearHighlights();
  emit();
}

async function claim(userId) {
  const owner = await readJson(OWNER);
  if (owner?.id && owner.id !== userId) await forgetEverything();
  if (owner?.id !== userId) await writeJson(OWNER, { id: userId });
}

/**
 * Mount once in the signed-in app: makes sure the saved changes belong to this member, sends what is waiting when
 * the app starts, comes forward, or on a timer while something waits. `onSync` also runs then (to refresh notes).
 */
export function useOfflineSync(user, onSync) {
  const userId = user?.id ?? null;
  const { pending } = useOutbox();

  useEffect(() => {
    if (!userId) return undefined;
    let alive = true;
    const run = async () => {
      if (!alive) return;
      await flush();
      await onSync?.().catch(() => {});
    };
    claim(userId).then(run);
    const sub = AppState.addEventListener('change', (s) => s === 'active' && run());
    // The moment the phone gets internet back, send what is waiting.
    let network = null;
    const back = () => run();
    if (Platform.OS === 'web') globalThis.addEventListener?.('online', back);
    else {
      try {
        network = require('expo-network').addNetworkStateListener((s) => s.isInternetReachable !== false && s.isConnected && back());
      } catch {
        // No network events here: the timer below still retries.
      }
    }
    return () => {
      alive = false;
      sub.remove();
      network?.remove();
      if (Platform.OS === 'web') globalThis.removeEventListener?.('online', back);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  useEffect(() => {
    if (!userId || !pending) return undefined;
    const timer = setInterval(() => flush(), 15000);
    return () => clearInterval(timer);
  }, [userId, pending]);
}
