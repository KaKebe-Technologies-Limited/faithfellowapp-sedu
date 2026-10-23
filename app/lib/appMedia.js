// The app's own pictures, controlled by the platform admin in Super admin → App media: the opening photos, the photos behind the welcome screen and the
// default photos behind the Home banner slider. An empty list means "use the photos built into the app". The app opens with the lists it was given the
// last time, whose pictures were already fetched then (so nothing waits for the network), and asks again in the background for the next start.
import { useSyncExternalStore } from 'react';
import { Image } from 'expo-image';
import api, { mediaUrl } from './api';
import { readJson, writeJson } from './localFiles';

const FILE = 'app-media.json';

// Each list: [{ id, url, title?, subtitle? }]
let snapshot = { opening: [], welcome: [], banner: [] };
const listeners = new Set();
const set = (patch) => {
  snapshot = { ...snapshot, ...patch };
  listeners.forEach((l) => l());
};

export const useAppMedia = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => snapshot);

/** Reads what the phone kept the last time. */
export async function loadAppMedia() {
  const saved = await readJson(FILE);
  if (saved?.ready) set({ opening: saved.opening ?? [], welcome: saved.welcome ?? [], banner: saved.banner ?? [] });
}

/** Asks the server for the current lists, fetches the pictures, and keeps the lists for the next start. */
export async function refreshAppMedia() {
  try {
    const { data } = await api.get('/app/media');
    const media = data.data;
    const urls = [...media.opening, ...media.welcome, ...media.banner].map((p) => mediaUrl(p.url));
    const fetched = await Promise.all(urls.map((u) => Image.prefetch(u, 'disk').catch(() => false)));
    // Only a list whose pictures are all on the phone is kept, so the next start never shows a hole.
    if (fetched.every(Boolean)) await writeJson(FILE, { opening: media.opening, welcome: media.welcome, banner: media.banner, ready: true });
  } catch {
    // Offline: the next start uses what it has.
  }
}

/** A picture of a list as an image source. */
export const sourceOf = (photo) => ({ uri: mediaUrl(photo.url) });
