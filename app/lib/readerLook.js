// How a Bible page looks while you read it: Light, Sepia (warm paper), Night (dark) or Rose (a soft, girlish Bible with a blush page, a serif heading,
// rose verse numbers and little flowers). It is the page of the Bible reader only, so you can read at night in an app that is light, and it is
// remembered on the phone. "Match the app" follows the app's own look (Night in the dark look, Rose in the rose look).
import { useSyncExternalStore } from 'react';
import { IS_DARK, IS_GIRLS } from '../constants/theme';
import { getStored, setStored } from './storage';

const KEY = 'bible_page_look';

export const READER_LOOKS = [
  { key: 'auto', title: 'Match the app' },
  { key: 'light', title: 'Light' },
  { key: 'sepia', title: 'Sepia' },
  { key: 'night', title: 'Night' },
  { key: 'rose', title: 'Rose' },
];

export const PAGES = {
  light: { name: 'light', bg: '#FAFAFA', card: '#FFFFFF', ink: '#2B2B2B', soft: '#727272', accent: '#BC2649', accentSoft: '#FFE9F5', onAccent: '#FFFFFF', night: false, rose: false },
  sepia: { name: 'sepia', bg: '#F4EAD5', card: '#FBF4E4', ink: '#3B2F1E', soft: '#7A6A52', accent: '#9A5B2A', accentSoft: '#EBDDBF', onAccent: '#FFFFFF', night: false, rose: false },
  night: { name: 'night', bg: '#0E0C10', card: '#1B181D', ink: '#E6E0E4', soft: '#A39AA0', accent: '#FF8AA5', accentSoft: '#2A2229', onAccent: '#1A0A10', night: true, rose: false },
  rose: {
    name: 'rose', bg: '#FFF1F7', card: '#FFFFFF', ink: '#3A1A2B', soft: '#8B6B7B', accent: '#D93F7E', accentSoft: '#FFDDEC', onAccent: '#FFFFFF', night: false, rose: true,
    // The four highlight colours, in the Rose page's own soft tints (the same four choices, drawn as peach, mint, lilac and rose).
    highlights: { yellow: '#FFD9A8', green: '#BDEFD5', blue: '#D9C8F7', pink: '#FFC2DA' },
  },
};

let choice = 'auto';
const listeners = new Set();
let loaded = false;

function load() {
  if (loaded) return;
  loaded = true;
  getStored(KEY, 'auto').then((saved) => {
    if (saved !== choice && (saved === 'auto' || PAGES[saved])) {
      choice = saved;
      listeners.forEach((l) => l());
    }
  });
}

export function setReaderLook(key) {
  choice = key === 'auto' || PAGES[key] ? key : 'auto';
  setStored(KEY, choice);
  listeners.forEach((l) => l());
}

/** The page the Bible reader is drawn with, and which choice (including "auto") is picked. */
export function useReaderLook() {
  load();
  const key = useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => choice);
  const resolved = key === 'auto' ? (IS_DARK ? 'night' : IS_GIRLS ? 'rose' : 'light') : key;

  return { key, page: PAGES[resolved] };
}
