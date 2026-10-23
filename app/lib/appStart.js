// What has happened since the app was opened. For now: whether the opening flash (components/OpeningFlash.jsx) is over, so a pop-up on Home
// (following a church) waits for it instead of appearing on top of the photos.
import { useSyncExternalStore } from 'react';

let flashDone = false;
const listeners = new Set();

export function markFlashDone() {
  flashDone = true;
  listeners.forEach((l) => l());
}

export const useFlashDone = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => flashDone);
