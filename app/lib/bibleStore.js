// The Bible on the phone, for reading without internet. Chapters are kept in one small file per book and translation
// (phones: expo-file-system; the web preview: localStorage). Every chapter you read online is kept automatically, and
// the Offline Bible screen can download whole books or the whole Bible. Highlights come along as the last known colours.
//
//   bible-offline/meta.json            the book list, translations and the member's translation (from GET /api/v1/bible)
//   bible-offline/passages.json        the last few passages (plans, sermons, search) as the server answered them
//   bible-offline/{translation}/{BOOK}.json   { chapters: { "3": { v: [[1, "text"], …], c: { "16": "yellow" } } } }
//   bible-offline/{translation}/_index.json   { books: { JHN: { c: [1, 2, 3], b: bytes } } }
import { Platform } from 'react-native';

const ROOT = 'bible-offline';
const native = Platform.OS !== 'web';

let fsModule = null;
const fs = () => (fsModule ??= require('expo-file-system'));

// ── A tiny JSON file layer ──────────────────────────────────────────────────────────────────────────────

function fileFor(name) {
  const { File, Paths } = fs();
  return new File(Paths.document, ROOT, ...name.split('/'));
}

async function readJson(name) {
  try {
    if (!native) {
      const raw = globalThis.localStorage?.getItem(`${ROOT}/${name}`);
      return raw ? JSON.parse(raw) : null;
    }
    const file = fileFor(name);
    return file.exists ? JSON.parse(await file.text()) : null;
  } catch {
    return null;
  }
}

/** Resolves true when written. A full disk or storage quota is reported as false, never thrown. */
async function writeJson(name, value) {
  const raw = JSON.stringify(value);
  try {
    if (!native) {
      globalThis.localStorage?.setItem(`${ROOT}/${name}`, raw);
      return true;
    }
    const file = fileFor(name);
    file.parentDirectory.create({ intermediates: true, idempotent: true });
    if (!file.exists) file.create({ intermediates: true });
    file.write(raw);
    return true;
  } catch {
    return false;
  }
}

function removeFile(name) {
  try {
    if (!native) return globalThis.localStorage?.removeItem(`${ROOT}/${name}`);
    const file = fileFor(name);
    if (file.exists) file.delete();
  } catch {
    // Already gone.
  }
}

function removeDir(name) {
  try {
    if (!native) {
      const prefix = `${ROOT}/${name}/`;
      Object.keys(globalThis.localStorage ?? {}).filter((k) => k.startsWith(prefix)).forEach((k) => globalThis.localStorage.removeItem(k));
      return;
    }
    const { Directory, Paths } = fs();
    const dir = new Directory(Paths.document, ROOT, name);
    if (dir.exists) dir.delete();
  } catch {
    // Already gone.
  }
}

// Writes to the same file wait for each other, so reading a chapter while a download saves a book cannot lose either.
const chains = new Map();
function serial(key, task) {
  const next = (chains.get(key) ?? Promise.resolve()).then(task, task);
  chains.set(key, next.catch(() => {}));
  return next;
}

// ── Books and the index of what is stored ───────────────────────────────────────────────────────────────

const bookCache = new Map(); // "kjv/JHN" → { chapters: {…} }
const indexCache = new Map(); // "kjv" → { books: {…} }

async function indexOf(translation) {
  if (!indexCache.has(translation)) indexCache.set(translation, (await readJson(`${translation}/_index.json`)) ?? { books: {} });
  return indexCache.get(translation);
}

async function bookOf(translation, book) {
  const key = `${translation}/${book}`;
  if (!bookCache.has(key)) {
    if (bookCache.size > 6) bookCache.delete(bookCache.keys().next().value);
    bookCache.set(key, (await readJson(`${key}.json`)) ?? { chapters: {} });
  }
  return bookCache.get(key);
}

const toRecord = (verses) => {
  const colors = {};
  for (const v of verses) if (v.color) colors[v.v] = v.color;
  return { v: verses.map((x) => [x.v, x.t]), ...(Object.keys(colors).length ? { c: colors } : {}) };
};

const fromRecord = (record) => record.v.map(([v, t]) => ({ v, t, color: record.c?.[v] ?? null }));

async function persist(translation, book, data) {
  const raw = JSON.stringify(data);
  const ok = await writeJson(`${translation}/${book}.json`, data);
  if (!ok) return false;
  const index = await indexOf(translation);
  index.books[book] = { c: Object.keys(data.chapters).map(Number).sort((a, b) => a - b), b: raw.length };
  await writeJson(`${translation}/_index.json`, index);
  return true;
}

/** One stored chapter as `[{ v, t, color }]`, or null when it is not on the phone. */
export async function getChapter(translation, book, chapter) {
  const record = (await bookOf(translation, book)).chapters[chapter];
  return record ? fromRecord(record) : null;
}

/** Keeps a chapter you just read (verses are `{ v, t, color }`). */
export function saveChapter(translation, book, chapter, verses) {
  return serial(`${translation}/${book}`, async () => {
    const data = await bookOf(translation, book);
    data.chapters[chapter] = toRecord(verses);
    return persist(translation, book, data);
  });
}

/** Keeps many chapters of one book in one write (`chapters`: { "1": [{ v, t }], … }). */
export function saveChapters(translation, book, chapters) {
  return serial(`${translation}/${book}`, async () => {
    const data = await bookOf(translation, book);
    for (const [n, verses] of Object.entries(chapters)) {
      // Keep the highlight colours we already know for a chapter we are re-downloading.
      data.chapters[n] = { ...toRecord(verses), ...(data.chapters[n]?.c ? { c: data.chapters[n].c } : {}) };
    }
    return persist(translation, book, data);
  });
}

/** The chapter numbers of a book that are on the phone. */
export async function chaptersHeld(translation, book) {
  return (await indexOf(translation)).books[book]?.c ?? [];
}

/** `{ JHN: 21, … }` — how many chapters of each book are stored. */
export async function downloadedCounts(translation) {
  const { books } = await indexOf(translation);
  return Object.fromEntries(Object.entries(books).map(([code, b]) => [code, b.c.length]));
}

/** Bytes used by a translation's stored books (about; counted when they were written). */
export async function storageUsed(translation) {
  const { books } = await indexOf(translation);
  return Object.values(books).reduce((sum, b) => sum + (b.b ?? 0), 0);
}

export async function removeBook(translation, book) {
  return serial(`${translation}/${book}`, async () => {
    bookCache.delete(`${translation}/${book}`);
    removeFile(`${translation}/${book}.json`);
    const index = await indexOf(translation);
    delete index.books[book];
    await writeJson(`${translation}/_index.json`, index);
  });
}

export async function removeTranslation(translation) {
  bookCache.forEach((_, key) => key.startsWith(`${translation}/`) && bookCache.delete(key));
  indexCache.delete(translation);
  removeDir(translation);
}

// ── What the Bible tab shows (books, translations) and the member's own translation ────────────────────

let metaCache = null;

export async function getMeta() {
  return (metaCache ??= await readJson('meta.json'));
}

/** Merges into the stored meta: `{ books, translations, colors, translation }`. */
export async function saveMeta(patch) {
  metaCache = { ...((await getMeta()) ?? {}), ...patch };
  await writeJson('meta.json', metaCache);
  return metaCache;
}

// ── Passages: the last few the server resolved, for plans and sermons ───────────────────────────────────

const MAX_PASSAGES = 40;
const passageKey = (translation, reference) => `${translation}|${String(reference).trim().toLowerCase()}`;

export async function getPassage(translation, reference) {
  return ((await readJson('passages.json')) ?? {})[passageKey(translation, reference)] ?? null;
}

export function savePassage(translation, reference, passage) {
  return serial('passages', async () => {
    const all = (await readJson('passages.json')) ?? {};
    const key = passageKey(translation, reference);
    delete all[key];
    all[key] = passage;
    for (const old of Object.keys(all).slice(0, Math.max(0, Object.keys(all).length - MAX_PASSAGES))) delete all[old];
    await writeJson('passages.json', all);
  });
}

/** "2.4 MB" */
export function formatBytes(bytes) {
  if (!bytes) return '0 KB';
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
