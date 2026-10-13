// How the Bible screens work with and without internet. Reading asks the server first (for your highlights and the
// right translation) and keeps what it gets; when there is no connection it falls back to what is on the phone.
// Downloading fetches whole books through GET /api/v1/bible/download/{book} and saves them with lib/bibleStore.
import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react';
import api, { errorMessage } from './api';
import { parseReference, referenceTitle, segmentLabel } from './bibleRef';
import * as store from './bibleStore';

/** True when a request failed for lack of a connection (no answer at all), not because the server said no. */
export const isOffline = (e) => !!e && !e.response && e.code !== 'ERR_CANCELED';

const OFFLINE_CHAPTER = 'You are offline, and this chapter is not on your phone yet. Download the Bible under “Offline Bible” to read without internet.';

// ── The Bible tab: books, translations ───────────────────────────────────────────────────────────────────

/** GET /bible, remembered; with no connection the last answer is used. */
export function useBibleIndex() {
  const [state, setState] = useState({ bible: null, loading: true, refreshing: false, error: null, offline: false });

  const load = useCallback(async (mode = 'load') => {
    setState((s) => ({ ...s, loading: mode === 'load' && !s.bible, refreshing: mode === 'refresh', error: null }));
    try {
      const { data } = await api.get('/bible');
      const bible = data.data;
      store.saveMeta({ books: bible.books, translations: bible.translations, colors: bible.colors, translation: bible.translation });
      setState({ bible, loading: false, refreshing: false, error: null, offline: false });
    } catch (e) {
      const meta = isOffline(e) ? await store.getMeta() : null;
      if (meta?.books) {
        setState({ bible: { books: meta.books, translations: meta.translations ?? [], translation: meta.translation, colors: meta.colors ?? [], highlights: [] }, loading: false, refreshing: false, error: null, offline: true });
      } else {
        setState((s) => ({ ...s, loading: false, refreshing: false, error: errorMessage(e) }));
      }
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return { ...state, reload: () => load('refresh'), retry: () => load('load') };
}

// ── The reader ──────────────────────────────────────────────────────────────────────────────────────────

function neighbours(books, book, chapter) {
  const i = books.findIndex((b) => b.code === book);
  const here = books[i];
  const point = (code, n) => ({ book: code, chapter: n, label: `${books.find((b) => b.code === code)?.name ?? code} ${n}` });
  return {
    previous: chapter > 1 ? point(book, chapter - 1) : books[i - 1] ? point(books[i - 1].code, books[i - 1].chapters) : null,
    next: here && chapter < here.chapters ? point(book, chapter + 1) : books[i + 1] ? point(books[i + 1].code, 1) : null,
  };
}

/** The chapter as the reader needs it, built from what is on the phone. */
function fromPhone(meta, translation, book, chapter, verses) {
  const books = meta?.books ?? [];
  const here = books.find((b) => b.code === book);
  return { book, book_name: here?.name ?? book, chapter, chapters: here?.chapters ?? chapter, translation, verses, ...(books.length ? neighbours(books, book, chapter) : { previous: null, next: null }) };
}

/**
 * A chapter for the reader. A copy on the phone shows at once, then the server's answer (with your highlights)
 * replaces it; with no connection the copy stays and `offline` is true. Whatever is read online is kept.
 */
export function useChapter(book, chapter, t) {
  const [state, setState] = useState({ info: null, loading: true, error: null, offline: false });
  const latest = useRef(0);

  const run = useCallback(async () => {
    const id = ++latest.current;
    const fresh = (patch) => id === latest.current && setState((s) => ({ ...s, ...patch }));
    setState((s) => ({ info: s.info?.book === book && s.info?.chapter === chapter ? s.info : null, loading: true, error: null, offline: false }));

    const meta = await store.getMeta();
    const translation = t ?? meta?.translation;
    const local = translation ? await store.getChapter(translation, book, chapter) : null;
    if (local) fresh({ info: fromPhone(meta, translation, book, chapter, local), loading: false });

    try {
      const { data } = await api.get(`/bible/${book}/${chapter}`, { params: { t } });
      const info = data.data;
      fresh({ info, loading: false, error: null, offline: false });
      store.saveChapter(info.translation, book, chapter, info.verses);
      if (!t) store.saveMeta({ translation: info.translation });
    } catch (e) {
      if (local) fresh({ offline: isOffline(e) });
      else fresh({ info: null, loading: false, error: isOffline(e) ? OFFLINE_CHAPTER : errorMessage(e) });
    }
  }, [book, chapter, t]);

  useEffect(() => {
    run();
    return () => {
      latest.current++;
    };
  }, [run]);

  return { chapterInfo: state.info, loading: state.loading, error: state.error, offline: state.offline, retry: run };
}

// ── Passages (reading plans, sermons, search) ───────────────────────────────────────────────────────────

/** A passage built from downloaded chapters when the server cannot be reached. */
async function passageFromPhone(reference, meta) {
  const books = meta?.books ?? [];
  const translation = meta?.translation;
  const segments = translation ? parseReference(reference, books) : [];
  if (!segments.length) return null;

  const sections = [];
  for (const segment of segments) {
    const verses = await store.getChapter(translation, segment.book, segment.chapter);
    sections.push({
      label: segmentLabel(segment, books),
      book: segment.book,
      chapter: segment.chapter,
      error: verses ? null : 'Not on your phone yet. Download this book under “Offline Bible”, or connect to read it.',
      verses: verses ? verses.filter((v) => (!segment.from || v.v >= segment.from) && (!segment.to || v.v <= segment.to)) : [],
    });
  }
  return sections.some((s) => !s.error) ? { title: referenceTitle(segments, books), translation, sections } : null;
}

/** GET /bible/passage with a fallback to earlier answers and downloaded chapters. */
export function usePassage(reference) {
  const [state, setState] = useState({ passage: null, loading: !!reference, error: null, offline: false });

  const run = useCallback(async () => {
    if (!reference) return;
    setState((s) => ({ ...s, loading: true, error: null }));
    try {
      const { data } = await api.get('/bible/passage', { params: { ref: reference } });
      setState({ passage: data.data, loading: false, error: null, offline: false });
      store.savePassage(data.data.translation, reference, data.data);
    } catch (e) {
      if (!isOffline(e)) return setState({ passage: null, loading: false, error: errorMessage(e), offline: false });
      const meta = await store.getMeta();
      const passage = (meta?.translation && (await store.getPassage(meta.translation, reference))) || (await passageFromPhone(reference, meta));
      setState(passage ? { passage, loading: false, error: null, offline: true } : { passage: null, loading: false, error: OFFLINE_CHAPTER.replace('chapter', 'passage'), offline: false });
    }
  }, [reference]);

  useEffect(() => {
    run();
  }, [run]);

  return { ...state, retry: run };
}

// ── Downloading ─────────────────────────────────────────────────────────────────────────────────────────

const sleep = (ms, signal) =>
  new Promise((resolve, reject) => {
    const timer = setTimeout(resolve, ms);
    signal?.addEventListener('abort', () => {
      clearTimeout(timer);
      reject(Object.assign(new Error('Stopped'), { code: 'ERR_CANCELED' }));
    });
  });

/**
 * Downloads one book: asks the server for the chapters it holds (and lets it fetch a few more each time) until
 * nothing is missing. `onChapters(done, total)` reports progress.
 */
export async function downloadBook(translation, bookCode, { signal, onChapters } = {}) {
  let stalled = 0;
  for (;;) {
    const held = await store.chaptersHeld(translation, bookCode);
    const { data } = await api.get(`/bible/download/${bookCode}`, { params: { t: translation, have: held.join(',') }, signal, timeout: 75000 });
    const res = data.data;
    const received = Object.keys(res.chapters).length;
    if (received && !(await store.saveChapters(translation, bookCode, res.chapters))) throw new Error('There is not enough space on this phone to keep the Bible.');
    onChapters?.(res.total - res.missing.length, res.total);
    if (!res.missing.length) return;

    // The server is getting the rest ready (the Bible provider allows only a few chapters at a time): wait and ask again.
    stalled = received || res.fetched ? 0 : stalled + 1;
    if (stalled >= 4) throw new Error('The Bible server is still getting this book ready. Try again in a few minutes.');
    await sleep(received ? 300 : 3000, signal);
  }
}

// One download at a time, kept outside any screen so it carries on while you read or leave the Offline Bible screen.
let job = { running: false, label: '', done: 0, total: 0, error: null, finished: false };
let controller = null;
const listeners = new Set();
const setJob = (patch) => {
  job = { ...job, ...patch };
  listeners.forEach((l) => l());
};

export const useDownloadJob = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => job);

/** Starts downloading `books` (from the Bible index) of a translation, skipping the ones already complete. */
export async function startDownload(translation, books, label) {
  if (job.running) return;
  controller = new AbortController();
  const counts = await store.downloadedCounts(translation);
  const todo = books.filter((b) => (counts[b.code] ?? 0) < b.chapters);
  const total = books.reduce((n, b) => n + b.chapters, 0);
  let base = books.reduce((n, b) => n + Math.min(counts[b.code] ?? 0, b.chapters), 0);
  setJob({ running: true, label, done: base, total, error: null, finished: false });

  try {
    for (const book of todo) {
      const before = Math.min(counts[book.code] ?? 0, book.chapters);
      await downloadBook(translation, book.code, { signal: controller.signal, onChapters: (done) => setJob({ done: base + Math.max(0, done - before), label: `${label} · ${book.name}` }) });
      base += book.chapters - before;
      setJob({ done: base });
    }
    setJob({ running: false, finished: true, label });
  } catch (e) {
    setJob({ running: false, error: e?.code === 'ERR_CANCELED' ? null : isOffline(e) ? 'The connection was lost. Your progress is saved; start again to continue.' : (e?.response ? errorMessage(e) : e.message) });
  } finally {
    controller = null;
  }
}

export const cancelDownload = () => controller?.abort();
