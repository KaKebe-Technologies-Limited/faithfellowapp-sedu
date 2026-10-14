// The hymn book on the phone: a hymnal can be saved once and read forever with no internet (every hymn with its verses,
// refrain and credits). Anything opened is also remembered, so "recently opened" and a hymn you already read still work
// offline. Everything here is safe: a missing file is empty, a full disk just means it was not saved.
import { useEffect, useState } from 'react';
import api from './api';
import { readJson, removeFile, writeJson } from './localFiles';
import { getStored, setStored } from './storage';

const INDEX = 'hymns/index.json'; // [{ ...hymnal card, saved_at }]
const LIST = 'hymns/hymnals.json'; // the last list of hymnals from the server
const RECENT = 'hymns/recent.json'; // the last hymns opened (full detail)
const SIZE_KEY = 'hymn_font_size';

export const MIN_SIZE = 16;
export const MAX_SIZE = 40;
export const getHymnSize = () => getStored(SIZE_KEY, 21);
export const setHymnSize = (size) => setStored(SIZE_KEY, Math.max(MIN_SIZE, Math.min(MAX_SIZE, size)));

const fileFor = (id) => `hymns/hymnal-${id}.json`;

/** A request that falls back to what was kept last time when there is no connection. Resolves { data, offline }. */
export async function withCache(name, request) {
  try {
    const data = await request();
    writeJson(`hymns/cache-${name}.json`, data);

    return { data, offline: false };
  } catch (e) {
    const kept = await readJson(`hymns/cache-${name}.json`);
    if (kept) return { data: kept, offline: true };
    throw e;
  }
}

export async function getHymnalList() {
  const { data, offline } = await withCache('list', async () => (await api.get('/hymnals')).data.data);
  await writeJson(LIST, data);

  return { ...data, offline };
}

/** Ids of the hymnals saved on this phone, with when they were saved. */
export async function savedHymnals() {
  return (await readJson(INDEX)) ?? [];
}

export async function saveHymnal(id) {
  const { data } = await api.get(`/hymnals/${id}/download`);
  const hymnal = data.data;
  if (!(await writeJson(fileFor(id), hymnal))) throw new Error('There is not enough space on the phone to save this hymnal.');
  const index = (await savedHymnals()).filter((h) => h.id !== id);
  index.push({ id, name: hymnal.name, hymns: hymnal.hymns.length, saved_at: new Date().toISOString() });
  await writeJson(INDEX, index);

  return hymnal;
}

export async function removeSavedHymnal(id) {
  removeFile(fileFor(id));
  await writeJson(INDEX, (await savedHymnals()).filter((h) => h.id !== id));
}

export const getSavedHymnal = (id) => readJson(fileFor(id));

/** The full hymn from a saved hymnal or from the recently opened ones, or null. */
export async function getKeptHymn(hymnId, hymnalId = null) {
  const id = Number(hymnId);
  const hymnalIds = hymnalId ? [hymnalId] : (await savedHymnals()).map((h) => h.id);
  for (const hid of hymnalIds) {
    const hymnal = await getSavedHymnal(hid);
    const at = hymnal?.hymns?.findIndex((h) => h.id === id) ?? -1;
    if (at >= 0) {
      const hymn = hymnal.hymns[at];

      return { ...hymn, hymnal: { id: hymnal.id, name: hymnal.name, language: hymnal.language }, prev_id: hymnal.hymns[at - 1]?.id ?? null, next_id: hymnal.hymns[at + 1]?.id ?? null };
    }
  }

  return (await recentHymns()).find((h) => h.id === id) ?? null;
}

export async function recentHymns() {
  return (await readJson(RECENT)) ?? [];
}

export async function rememberHymn(hymn) {
  const list = (await recentHymns()).filter((h) => h.id !== hymn.id);
  list.unshift(hymn);
  await writeJson(RECENT, list.slice(0, 25));
}

/** Loads a hymn: the server first (it knows your favourites), the phone when offline. Resolves { hymn, offline }. */
export async function loadHymn(id) {
  try {
    const { data } = await api.get(`/hymns/${id}`);
    rememberHymn(data.data);

    return { hymn: data.data, offline: false };
  } catch (e) {
    const kept = await getKeptHymn(id);
    if (kept) return { hymn: kept, offline: true };
    throw e;
  }
}

/** Hook: the hymnals saved on this phone, refreshed when `version` changes. */
export function useSavedHymnals(version = 0) {
  const [saved, setSaved] = useState([]);
  useEffect(() => {
    let alive = true;
    savedHymnals().then((list) => alive && setSaved(list));

    return () => {
      alive = false;
    };
  }, [version]);

  return saved;
}
