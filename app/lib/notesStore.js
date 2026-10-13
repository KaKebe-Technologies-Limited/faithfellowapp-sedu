// Your notes on the phone, so the notebook opens and can be written in with no internet. A note is identified by its
// "context" (bible:JHN.3, sermon:12, note:abc123…), which is unique per member and what the server also keys on.
// Changes are marked `dirty` until lib/outbox.js has sent them. Notes are private: signing out clears this.
import { useSyncExternalStore } from 'react';
import { readJson, removeFile, serial, writeJson } from './localFiles';

const FILE = 'notes/notes.json';

export const PAPER = { paper: '#FFFDF7', rose: '#FDF0F3', sky: '#EEF5FF', mint: '#EDF9F2', sun: '#FFF8E6' };
export const KINDS = { bible: 'Bible', plan: 'Reading plan', sermon: 'Sermon', devotional: 'Devotional', podcast: 'Podcast', live: 'Live service', note: 'Note' };

let state = null; // { notes: { [context]: record }, syncedAt }
let version = 0;
const listeners = new Set();

async function load() {
  if (!state) state = (await readJson(FILE)) ?? { notes: {}, syncedAt: null };
  return state;
}

function changed() {
  version += 1;
  listeners.forEach((l) => l());
  return serial('notes', () => writeJson(FILE, state));
}

/** Re-renders a component when any note changes. */
export const useNotesVersion = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => version);

export const newContext = () => `note:${Math.random().toString(36).slice(2, 8)}${Date.now().toString(36)}`;

/** The note as a screen draws it (same fields the server sends). */
export function present(rec) {
  const prefix = String(rec.context).split(':')[0];
  const kind = KINDS[prefix] ? prefix : 'note';
  const firstLine = String(rec.body ?? '').split('\n').find((l) => l.trim()) ?? '';
  return {
    ...rec,
    kind,
    kind_label: KINDS[kind],
    display_title: rec.title || rec.reference || (firstLine.length > 60 ? `${firstLine.slice(0, 57)}...` : firstLine) || 'Untitled note',
    color: rec.color || 'paper',
    color_hex: PAPER[rec.color || 'paper'] ?? PAPER.paper,
  };
}

const fromServer = (n) => ({
  context: n.context, id: n.id, title: n.title ?? '', body: n.body ?? '', reference: n.reference ?? null,
  color: n.color || 'paper', pinned: !!n.pinned, church_id: n.church_id ?? null, updated_at: n.updated_at, dirty: false, deleted: false,
});

/** All notes that are not deleted, filtered and sorted like the server does (pinned first, newest first). */
export async function listNotes({ q = '', kind = '' } = {}) {
  const { notes } = await load();
  const needle = q.trim().toLowerCase();
  return Object.values(notes)
    .filter((r) => !r.deleted)
    .map(present)
    .filter((n) => (!kind || n.kind === kind) && (!needle || [n.title, n.body, n.reference].some((t) => String(t ?? '').toLowerCase().includes(needle))))
    .sort((a, b) => Number(b.pinned) - Number(a.pinned) || String(b.updated_at).localeCompare(String(a.updated_at)));
}

export async function getNote(context) {
  const rec = (await load()).notes[context];
  return rec && !rec.deleted ? present(rec) : null;
}

/** The record exactly as stored (also a note that is waiting to be deleted), or undefined. */
export async function getRaw(context) {
  return (await load()).notes[context];
}

/** Remembers the server's id for a note without touching what you wrote. */
export async function setServerId(context, id) {
  const s = await load();
  if (s.notes[context] && !s.notes[context].id) {
    s.notes[context].id = id;
    await changed();
  }
}

export async function getNoteById(id) {
  const rec = Object.values((await load()).notes).find((r) => r.id === Number(id) && !r.deleted);
  return rec ? present(rec) : null;
}

/** Writes your change (title, body, colour, pinned, reference) and marks the note as waiting to be sent. */
export async function putNote(context, patch) {
  const s = await load();
  const now = new Date().toISOString();
  s.notes[context] = { id: null, title: '', body: '', reference: null, color: 'paper', pinned: false, church_id: null, ...s.notes[context], ...patch, context, updated_at: now, dirty: true, deleted: false };
  await changed();
  return present(s.notes[context]);
}

/** Hides a note at once; it is forgotten for good when the server has deleted it. */
export async function markDeleted(context) {
  const s = await load();
  if (!s.notes[context]) return;
  s.notes[context] = { ...s.notes[context], deleted: true, dirty: true };
  await changed();
}

/** Marks a note as fully sent (nothing of it is waiting any more). */
export async function markClean(context) {
  const s = await load();
  if (s.notes[context] && !s.notes[context].deleted && s.notes[context].dirty) {
    s.notes[context].dirty = false;
    await changed();
  }
}

export async function dropNote(context) {
  const s = await load();
  delete s.notes[context];
  await changed();
}

/**
 * The server accepted a note: remember its id and times. A note you edited again while it was being sent stays
 * marked as waiting, so the newer text is sent next.
 */
export async function applyServerNote(context, serverNote, sentAt) {
  const s = await load();
  const rec = s.notes[context];
  if (!rec) return;
  const editedSince = sentAt && rec.updated_at !== sentAt;
  // Pinning is sent as its own change, so what you chose on the phone stays until the server has it.
  s.notes[context] = editedSince ? { ...rec, id: serverNote.id } : { ...fromServer(serverNote), context, pinned: rec.pinned };
  await changed();
}

/**
 * Brings the phone up to date with the server's notes. Notes with changes still waiting to be sent are kept as
 * they are; with `complete` (every page was fetched) notes the server no longer has are removed.
 */
export async function mergeFromServer(serverNotes, complete) {
  const s = await load();
  const seen = new Set();
  for (const n of serverNotes) {
    seen.add(n.context);
    if (s.notes[n.context]?.dirty) {
      s.notes[n.context].id = s.notes[n.context].id ?? n.id;
    } else {
      s.notes[n.context] = fromServer(n);
    }
  }
  if (complete) {
    for (const [context, rec] of Object.entries(s.notes)) if (!seen.has(context) && !rec.dirty && rec.id) delete s.notes[context];
  }
  s.syncedAt = new Date().toISOString();
  await changed();
}

export const lastSynced = async () => (await load()).syncedAt;

/** Forgets every note on the phone (signing out, or a different person signing in). */
export async function clearNotes() {
  state = { notes: {}, syncedAt: null };
  removeFile(FILE);
  version += 1;
  listeners.forEach((l) => l());
}
