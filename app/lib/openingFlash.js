// The settings of the opening flash (components/OpeningFlash.jsx), set by the platform admin in Super admin → Settings: whether it shows, how many seconds,
// how fast the photos roll and the two lines of text. The app opens with what it was told the last time (the flash must not wait for the network) and
// asks again in the background, so a change shows the next time the app is opened.
import api from './api';
import { getStored, setStored } from './storage';

const KEY = 'opening_flash_v1';

export const FLASH_DEFAULTS = {
  enabled: true,
  seconds: 6,
  speed: 5,
  title: 'Stay Connected in Faith',
  text: 'Worship, pray, learn and grow together with your church family, wherever you are.',
};

/** What the flash was last told to do (or the defaults the first time). */
export async function storedFlash() {
  const saved = await getStored(KEY, null);

  return { ...FLASH_DEFAULTS, ...(saved && typeof saved === 'object' ? saved : {}) };
}

/** Asks the server for the current settings and keeps them for the next start. */
export async function refreshFlash() {
  try {
    const { data } = await api.get('/app/opening');
    if (data?.data) await setStored(KEY, data.data);
  } catch {
    // Offline: the next start uses what it has.
  }
}
