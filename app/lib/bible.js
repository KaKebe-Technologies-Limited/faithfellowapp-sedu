// Helpers for the Bible screens: highlight colours, "John 3:16–18" style references and the text that is copied or shared.
import { IS_DARK } from '../constants/theme';
import { getStored, setStored } from './storage';

/** Highlight colour key → swatch (matches the website). */
export const HIGHLIGHTS_LIGHT = { yellow: '#FFE97A', green: '#A8E6B8', blue: '#B5D6FF', pink: '#FFC2DA' };
/** The same four colours, deeper, so light text stays easy to read on them. */
export const HIGHLIGHTS_NIGHT = { yellow: '#B79A1C', green: '#2F8F57', blue: '#3F6FB8', pink: '#B5446F' };
/** The colours of the look the app is using. */
export const HIGHLIGHTS = IS_DARK ? HIGHLIGHTS_NIGHT : HIGHLIGHTS_LIGHT;

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

/**
 * A neat, ready-to-paste scripture card for WhatsApp, Facebook, Instagram, X, SMS or email: the reference and
 * translation on top, the words (one verse in quotation marks; several verses numbered on their own lines), and a
 * quiet line saying where it came from. Plain text, so it looks the same wherever it is pasted.
 *
 *   📖 John 3:16–17 (KJV)
 *
 *   16  For God so loved the world…
 *   17  For God sent not his Son…
 *
 *   ✝️ Shared from FaithFellows
 */
export function scriptureMessage({ reference, translation, verses }) {
  const tag = translation ? ` (${String(translation).toUpperCase()})` : '';
  const words = verses.length === 1 ? `“${String(verses[0].t).trim()}”` : verses.map((v) => `${v.v}  ${String(v.t).trim()}`).join('\n');
  return `📖 ${reference}${tag}\n\n${words}\n\n✝️ Shared from FaithFellows`;
}

/** The scripture card for verses picked in a chapter. */
export function shareText({ bookName, chapter, verses, translation }) {
  return scriptureMessage({ reference: referenceLabel(bookName, chapter, verses.map((v) => v.v)), translation, verses });
}

/** "JHN.3.16" keys for the highlight API. */
export const verseKey = (book, chapter, verse) => `${book}.${chapter}.${verse}`;

const LAST_KEY = 'bible_last_read';
const SIZE_KEY = 'bible_font_size';

export const getLastRead = () => getStored(LAST_KEY, null);
export const setLastRead = (book, chapter, bookName) => setStored(LAST_KEY, { book, chapter, bookName });
export const getFontSize = () => getStored(SIZE_KEY, 18);
export const setFontSize = (size) => setStored(SIZE_KEY, size);
