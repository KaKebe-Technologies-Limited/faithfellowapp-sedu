// The Bible on the phone, for reading without internet. Chapters are kept in one small file per book and translation
// (see lib/localFiles.js). Every chapter you read online is kept automatically, and the Offline Bible screen can
// download whole books or the whole Bible. Highlights come along as the last known colours.
//
//   bible/meta.json                  the book list, translations and the member's translation (from GET /api/v1/bible)
//   bible/passages.json              the last few passages (plans, sermons, search) as the server answered them
//   bible/{translation}/{BOOK}.json  { chapters: { "3": { v: [[1, "text"], …], c: { "16": "yellow" } } } }
//   bible/{translation}/_index.json  { books: { JHN: { c: [1, 2, 3], b: bytes, h: true } } }   (h: has highlight colours)
import { readJson, removeDir, removeFile, serial, writeJson } from './localFiles';

const bookCache = new Map(); // "kjv/JHN" → { chapters: {…} }
const indexCache = new Map(); // "kjv" → { books: {…} }

async function indexOf(translation) {
  if (!indexCache.has(translation)) indexCache.set(translation, (await readJson(`bible/${translation}/_index.json`)) ?? { books: {} });
  return indexCache.get(translation);
}

async function bookOf(translation, book) {
  const key = `${translation}/${book}`;
  if (!bookCache.has(key)) {
    if (bookCache.size > 6) bookCache.delete(bookCache.keys().next().value);
    bookCache.set(key, (await readJson(`bible/${key}.json`)) ?? { chapters: {} });
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
  const ok = await writeJson(`bible/${translation}/${book}.json`, data);
  if (!ok) return false;
  const index = await indexOf(translation);
  index.books[book] = { c: Object.keys(data.chapters).map(Number).sort((a, b) => a - b), b: raw.length, ...(Object.values(data.chapters).some((r) => r.c) ? { h: true } : {}) };
  await writeJson(`bible/${translation}/_index.json`, index);
  return true;
}

/** One stored chapter as `[{ v, t, color }]`, or null when it is not on the phone. */
export async function getChapter(translation, book, chapter) {
  const record = (await bookOf(translation, book)).chapters[chapter];
  return record ? fromRecord(record) : null;
}

/** Keeps a chapter you just read (verses are `{ v, t, color }`). */
export function saveChapter(translation, book, chapter, verses) {
  return serial(`bible/${translation}/${book}`, async () => {
    const data = await bookOf(translation, book);
    data.chapters[chapter] = toRecord(verses);
    return persist(translation, book, data);
  });
}

/** Keeps many chapters of one book in one write (`chapters`: { "1": [{ v, t }], … }). */
export function saveChapters(translation, book, chapters) {
  return serial(`bible/${translation}/${book}`, async () => {
    const data = await bookOf(translation, book);
    for (const [n, verses] of Object.entries(chapters)) {
      // Keep the highlight colours we already know for a chapter we are re-downloading.
      data.chapters[n] = { ...toRecord(verses), ...(data.chapters[n]?.c ? { c: data.chapters[n].c } : {}) };
    }
    return persist(translation, book, data);
  });
}

/**
 * Sets highlight colours on a chapter in every translation stored on the phone (a highlight belongs to the verse,
 * not to a translation). `colors` is `{ verse: 'yellow' | null }`; null clears.
 */
export async function setVerseColors(book, chapter, colors) {
  for (const translation of Object.keys(await translationsOnPhone())) {
    const held = (await indexOf(translation)).books[book]?.c ?? [];
    if (!held.includes(Number(chapter))) continue;
    await serial(`bible/${translation}/${book}`, async () => {
      const data = await bookOf(translation, book);
      const record = data.chapters[chapter];
      if (!record) return;
      const next = { ...(record.c ?? {}) };
      for (const [verse, color] of Object.entries(colors)) {
        if (color) next[verse] = color;
        else delete next[verse];
      }
      data.chapters[chapter] = { v: record.v, ...(Object.keys(next).length ? { c: next } : {}) };
      await persist(translation, book, data);
    });
  }
}

/** Forgets every highlight colour kept on the phone (when someone signs out). The Bible text stays. */
export async function clearHighlights() {
  removeFile('bible/highlights.json');
  for (const translation of Object.keys(await translationsOnPhone())) {
    const index = await indexOf(translation);
    for (const [book, entry] of Object.entries(index.books)) {
      if (!entry.h) continue;
      await serial(`bible/${translation}/${book}`, async () => {
        const data = await bookOf(translation, book);
        for (const n of Object.keys(data.chapters)) data.chapters[n] = { v: data.chapters[n].v };
        await persist(translation, book, data);
      });
    }
  }
}

async function translationsOnPhone() {
  const keys = ((await getMeta())?.translations ?? []).map((t) => t.key);
  return Object.fromEntries(keys.map((k) => [k, 1]));
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
  return serial(`bible/${translation}/${book}`, async () => {
    bookCache.delete(`${translation}/${book}`);
    removeFile(`bible/${translation}/${book}.json`);
    const index = await indexOf(translation);
    delete index.books[book];
    await writeJson(`bible/${translation}/_index.json`, index);
  });
}

export async function removeTranslation(translation) {
  bookCache.forEach((_, key) => key.startsWith(`${translation}/`) && bookCache.delete(key));
  indexCache.delete(translation);
  removeDir(`bible/${translation}`);
}

// ── What the Bible tab shows (books, translations) and the member's own translation ────────────────────

let metaCache = null;

export async function getMeta() {
  return (metaCache ??= await readJson('bible/meta.json'));
}

/** Merges into the stored meta: `{ books, translations, colors, translation }`. */
export async function saveMeta(patch) {
  metaCache = { ...((await getMeta()) ?? {}), ...patch };
  await writeJson('bible/meta.json', metaCache);
  return metaCache;
}

// ── Your highlight list, as the server last sent it ─────────────────────────────────────────────────────

export const getHighlightsCache = async () => (await readJson('bible/highlights.json'))?.list ?? null;
export const saveHighlightsCache = (list) => writeJson('bible/highlights.json', { list });

// ── Passages: the last few the server resolved, for plans and sermons ───────────────────────────────────

const MAX_PASSAGES = 40;
const passageKey = (translation, reference) => `${translation}|${String(reference).trim().toLowerCase()}`;

export async function getPassage(translation, reference) {
  return ((await readJson('bible/passages.json')) ?? {})[passageKey(translation, reference)] ?? null;
}

export function savePassage(translation, reference, passage) {
  return serial('bible/passages', async () => {
    const all = (await readJson('bible/passages.json')) ?? {};
    const key = passageKey(translation, reference);
    delete all[key];
    all[key] = passage;
    for (const old of Object.keys(all).slice(0, Math.max(0, Object.keys(all).length - MAX_PASSAGES))) delete all[old];
    await writeJson('bible/passages.json', all);
  });
}

/** "2.4 MB" */
export function formatBytes(bytes) {
  if (!bytes) return '0 KB';
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
