// Reads Bible references on the phone ("Matthew 3-5", "John 3:16-18", "Genesis 1-2; Psalm 1") so plans and
// searches still work with no connection once the chapters are downloaded. It mirrors the server's own parser
// (web/app/Support/Bible/Reference.php) and uses the book names and abbreviations the server sends in GET /bible.

const normalise = (name) =>
  String(name)
    .trim()
    .replace(/[.’']/g, '')
    .toLowerCase()
    .replace(/^(iii|ii|i)\s+(?=[a-z])/, (_, r) => ({ i: '1', ii: '2', iii: '3' })[r] + ' ')
    .replace(/^([123])\s*([a-z])/, '$1 $2')
    .replace(/\s+/g, ' ');

const indexes = new WeakMap();

/** alias → book, built once per book list. */
function indexFor(books) {
  if (!indexes.has(books)) {
    const map = new Map();
    for (const book of books) for (const alias of [book.name, book.code, ...(book.aliases ?? [])]) map.set(normalise(alias), book);
    indexes.set(books, map);
  }
  return indexes.get(books);
}

export function findBook(books, name) {
  const key = normalise(name);
  if (!key) return null;
  const index = indexFor(books);
  return index.get(key) ?? index.get(key.replace(/\.+$/, '')) ?? null;
}

const PART = /^\s*((?:[1-3]|i{1,3})?\s*[A-Za-z][A-Za-z.\s’']*?)?\s*(\d+)?(?::(\d+))?(?:\s*-\s*(\d+)(?::(\d+))?)?\s*$/i;
const range = (from, to) => Array.from({ length: Math.max(0, to - from + 1) }, (_, i) => from + i);

/** `[{ book, chapter, from, to }]` for a reference, or [] when it cannot be read. */
export function parseReference(text, books) {
  const clean = String(text ?? '').replace(/[–—‑]/g, '-').trim();
  if (!clean || !books?.length) return [];

  const segments = [];
  let last = null;
  for (const part of clean.split(/\s*[;,]\s*(?=(?:[1-3]\s*)?[A-Za-z])|\s*;\s*/)) {
    const m = part.match(PART);
    if (!m) continue;
    const book = m[1] && m[1].trim() ? findBook(books, m[1]) : last;
    if (!book) continue;
    last = book;

    const max = book.chapters;
    const chapter = m[2] ? Number(m[2]) : null;
    const verse = m[3] ? Number(m[3]) : null;
    const endA = m[4] ? Number(m[4]) : null;
    const endB = m[5] ? Number(m[5]) : null;
    const whole = (c) => ({ book: book.code, chapter: c, from: null, to: null });

    if (chapter === null) {
      // A whole book: only the short ones are expanded.
      segments.push(...(max > 5 ? [whole(1)] : range(1, max).map(whole)));
      continue;
    }
    const c1 = Math.max(1, Math.min(max, chapter));
    if (verse === null) {
      const lastChapter = endA ? Math.max(c1, Math.min(max, endA)) : c1;
      segments.push(...range(c1, lastChapter).map(whole));
    } else if (endB !== null) {
      const endChapter = Math.max(c1, Math.min(max, endA));
      for (const c of range(c1, endChapter)) segments.push({ book: book.code, chapter: c, from: c === c1 ? verse : 1, to: c === endChapter ? endB : null });
    } else {
      segments.push({ book: book.code, chapter: c1, from: verse, to: endA ?? verse });
    }
  }
  return segments.slice(0, 15);
}

/** "Matthew 3", "John 3:16", "Matthew 5:1–12" */
export function segmentLabel(segment, books) {
  const name = books.find((b) => b.code === segment.book)?.name ?? segment.book;
  let label = `${name} ${segment.chapter}`;
  if (segment.from) label += `:${segment.from}${segment.to && segment.to !== segment.from ? `–${segment.to}` : segment.to === null ? '–' : ''}`;
  return label;
}

/** The whole reference as the server titles it: "Matthew 3–5", or the labels joined with "; ". */
export function referenceTitle(segments, books) {
  const groups = [];
  for (const s of segments) {
    const group = groups.find((g) => g.book === s.book);
    if (group) group.items.push(s);
    else groups.push({ book: s.book, items: [s] });
  }
  return groups
    .map(({ items }) => {
      const whole = items.every((s) => s.from === null);
      const first = items[0];
      const lastItem = items[items.length - 1];
      const contiguous = items.length - 1 === lastItem.chapter - first.chapter;
      if (whole && contiguous && items.length > 1) return `${books.find((b) => b.code === first.book)?.name ?? first.book} ${first.chapter}–${lastItem.chapter}`;
      return items.map((s) => segmentLabel(s, books)).join('; ');
    })
    .join('; ');
}
