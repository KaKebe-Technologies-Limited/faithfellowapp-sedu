// "How am I doing?": the small pieces of activity behind the progress rings on your profile. Minutes with the Bible open, minutes of
// radio and podcasts, scriptures and inspiring messages shared, chapters opened, and how many days you opened the app. Pieces are added
// up on the phone and sent to POST /api/v1/me/activity about once a minute and whenever the app goes to the background; with no
// connection they wait (they are kept on the phone) and go later. Nothing here is shown to anyone else.
import { useCallback, useEffect } from 'react';
import { AppState, Platform, Share } from 'react-native';
import { useFocusEffect } from 'expo-router';
import api from './api';
import { readJson, removeFile, writeJson } from './localFiles';
import { usePodcast } from './podcastPlayer';
import { useRadio } from './radio';

const FILE = 'activity/pending.json';
const TICK = 5; // seconds counted at a time while something is going on
const SECONDS = new Set(['read', 'radio', 'podcast']);

let pending = {}; // `${day}|${kind}` -> { kind, day, seconds, count }
let loaded = null;
let sending = false;
let timer = null;

/** Today on this phone, as YYYY-MM-DD. */
const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const load = () =>
  (loaded ??= readJson(FILE).then((saved) => {
    // Whatever was waiting from an earlier run joins what was counted since.
    for (const [key, value] of Object.entries(saved ?? {})) {
      const mine = pending[key];
      pending[key] = mine ? { ...mine, seconds: mine.seconds + value.seconds, count: mine.count + value.count } : value;
    }
  }));

const persist = () => {
  clearTimeout(timer);
  timer = setTimeout(() => writeJson(FILE, pending), 2000);
};

function add(kind, seconds, count) {
  const day = today();
  const key = `${day}|${kind}`;
  const row = pending[key] ?? { kind, day, seconds: 0, count: 0 };
  pending[key] = { ...row, seconds: row.seconds + seconds, count: row.count + count };
  persist();
}

/** Signing out forgets what was waiting, so the next person on this phone never gets it. */
export function forgetActivity() {
  pending = {};
  clearTimeout(timer);
  removeFile(FILE);
}

export const trackSeconds =(kind, seconds) => SECONDS.has(kind) && seconds > 0 && add(kind, Math.round(seconds), 0);
export const trackCount = (kind, count = 1) => !SECONDS.has(kind) && count > 0 && add(kind, 0, count);

/** Sends what was counted. Safe to call at any time; one request at a time, and failures simply wait for the next try. */
export async function flushActivity() {
  await load();
  if (sending) return;
  const rows = Object.entries(pending);
  if (!rows.length) return;

  sending = true;
  try {
    // Several minutes of one kind can be waiting: send them in pieces the server accepts (an hour at most per piece).
    const events = rows.flatMap(([, r]) => {
      const pieces = [];
      let seconds = r.seconds;
      let count = r.count;
      while (seconds > 0) {
        pieces.push({ kind: r.kind, day: r.day, seconds: Math.min(3600, seconds) });
        seconds -= 3600;
      }
      while (count > 0) {
        pieces.push({ kind: r.kind, day: r.day, count: Math.min(50, count) });
        count -= 50;
      }
      return pieces;
    });
    const sent = Object.fromEntries(rows);
    for (let i = 0; i < events.length; i += 40) {
      await api.post('/me/activity', { events: events.slice(i, i + 40) });
    }
    // Anything counted while the request was on its way stays for the next one.
    for (const [key, row] of Object.entries(sent)) {
      const now = pending[key];
      const left = now ? { ...now, seconds: now.seconds - row.seconds, count: now.count - row.count } : null;
      if (!left || (left.seconds <= 0 && left.count <= 0)) delete pending[key];
      else pending[key] = left;
    }
    writeJson(FILE, pending);
  } catch {
    // No connection, or the session ended: keep everything for later.
  } finally {
    sending = false;
  }
}

/**
 * Mounted once for the signed-in app: counts the minutes of radio and podcasts, counts one "opened today" for the streak, and sends
 * the figures about once a minute and when the app goes to the background.
 */
export function useActivityTracking(user) {
  const radio = useRadio();
  const podcast = usePodcast();

  useEffect(() => {
    if (!user) return undefined;
    load().then(() => trackCount('open'));
    const every = setInterval(flushActivity, 60000);
    const sub = AppState.addEventListener('change', (state) => state !== 'active' && flushActivity());
    return () => {
      clearInterval(every);
      sub.remove();
      flushActivity();
    };
  }, [user?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const radioOn = radio.phase === 'playing';
  const podcastOn = podcast.phase === 'playing';
  useEffect(() => {
    if (!user || (!radioOn && !podcastOn)) return undefined;
    const t = setInterval(() => {
      if (radioOn) trackSeconds('radio', TICK);
      if (podcastOn) trackSeconds('podcast', TICK);
    }, TICK * 1000);
    return () => clearInterval(t);
  }, [user?.id, radioOn, podcastOn]); // eslint-disable-line react-hooks/exhaustive-deps
}

/** Counts the minutes a screen is open and the app is in front: use it in the Bible reader. */
export function useReadingTimer(enabled = true) {
  useFocusEffect(
    useCallback(() => {
      if (!enabled) return undefined;
      const t = setInterval(() => AppState.currentState === 'active' && trackSeconds('read', TICK), TICK * 1000);
      return () => clearInterval(t);
    }, [enabled]),
  );
}

/**
 * Opens the phone's share sheet with `message` and counts it as `kind` ('share_scripture' or 'share_inspiration') when the person
 * actually shared. The web preview has no share sheet, so there the copy that came before it counts.
 */
export async function shareMessage(message, kind) {
  let shared = Platform.OS === 'web';
  if (Platform.OS !== 'web') {
    try {
      const result = await Share.share({ message });
      shared = result.action !== Share.dismissedAction;
    } catch {
      shared = false;
    }
  }
  if (shared) trackCount(kind);

  return shared;
}
