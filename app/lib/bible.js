// Helpers for the Bible screens: highlight colours, "John 3:16–18" style references and the text that is copied or shared.
import { getStored, setStored } from './storage';

/** Highlight colour key → swatch (matches the website). */
export const HIGHLIGHTS = {
  yellow: '#FFE97A',
  green: '#A8E6B8',
  blue: '#B5D6FF',
  pink: '#FFC2DA',
};

/** Verses [16, 17, 18, 20] → "16–18, 20". */
export function verseRanges(numbers) {
  const sorted = [...new Set(numbers)].sort((a, b) => a - b);
  const parts = [];
  let start = null;
  let prev = null;
  for (const n of sorted) {
    if (start === null) start = prev = n;
    else if (n === prev + 1) prev = n;
    else {
      parts.push(start === prev ? `${start}` : `${start}–${prev}`);
      start = prev = n;
    }
  }
  if (start !== null) parts.push(start === prev ? `${start}` : `${start}–${prev}`);
  return parts.join(', ');
}

/** "John 3:16–18" */
export function referenceLabel(bookName, chapter, numbers) {
  return `${bookName} ${chapter}:${verseRanges(numbers)}`;
}

/** The text a member copies or shares: the verses, then the reference and translation. */
export function shareText({ bookName, chapter, verses, translation }) {
  const body = verses.map((v) => v.t).join(' ');
  return `“${body}”\n— ${referenceLabel(bookName, chapter, verses.map((v) => v.v))} (${String(translation).toUpperCase()})`;
}

/** "JHN.3.16" keys for the highlight API. */
export const verseKey = (book, chapter, verse) => `${book}.${chapter}.${verse}`;

const LAST_KEY = 'bible_last_read';
const SIZE_KEY = 'bible_font_size';

export const getLastRead = () => getStored(LAST_KEY, null);
export const setLastRead = (book, chapter, bookName) => setStored(LAST_KEY, { book, chapter, bookName });
export const getFontSize = () => getStored(SIZE_KEY, 18);
export const setFontSize = (size) => setStored(SIZE_KEY, size);
