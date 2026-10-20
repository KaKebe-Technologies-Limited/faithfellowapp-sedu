// The FaithFellows radio: channels that play round the clock, in a loop, and everyone hears the same thing at the same
// moment. There is the Gospel radio (songs), the Podcast radio (teaching), and a channel for each church the member follows.
// The server says which channels there are (GET /api/v1/radio/stations) and what is on air on one of them and how far
// into it it is (GET /api/v1/radio/stations/{slug}; the Gospel radio is also GET /api/v1/radio); this module plays it, moves
// on to the next track by itself, keeps playing with the screen locked or the app in the background, and shows the track on
// the lock screen. One player lives here for the whole app, so the audio carries on while you move between screens; the home
// card, the bar above the tabs and the full player all read the same state.
import { useEffect, useSyncExternalStore } from 'react';
import { AppState, Platform } from 'react-native';
import { createAudioPlayer, setAudioModeAsync } from 'expo-audio';
import api, { errorMessage, mediaUrl } from './api';
import { claimAudio, registerAudio } from './audioFocus';

let snapshot = {
  phase: 'idle', // idle | loading | playing | paused
  stationId: 'gospel', // the channel that is selected: 'gospel', 'podcast' or 'church-{id}'
  stations: [], // the channels this member can hear: [{ slug, kind, name, tagline, church, songs }]
  station: null, // the selected channel: { enabled, name, tagline, songs, kind, slug, church }
  track: null, // the song that is on
  upcoming: [],
  recent: [],
  error: null,
};
const listeners = new Set();
const set = (patch) => {
  snapshot = { ...snapshot, ...patch };
  listeners.forEach((l) => l());
};

/** The radio's state: `{ phase, station, track, upcoming, recent, error }`. */
export const useRadio = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => snapshot);

let player = null;
let queue = []; // songs that follow the current one
let pendingSeek = null; // seconds to jump to once the song has loaded
let skew = 0; // server clock minus this phone's clock, in ms
let finishedId = null;
let startedLocal = 0; // when the current song was started on this phone (ignores a stale "finished" event from the song before)
let userStopped = true;
let watchdog = null;
let configured = false;

const stationName = () => snapshot.station?.name ?? 'FaithFellows Radio';

async function configure() {
  if (configured) return;
  configured = true;
  // Play with the silent switch on, and keep playing when the app is in the background or the phone is locked.
  await setAudioModeAsync({ playsInSilentMode: true, shouldPlayInBackground: true, interruptionMode: 'doNotMix' }).catch(() => {});
  player = createAudioPlayer(null, { updateInterval: 1000, keepAudioSessionActive: true });
  player.addListener('playbackStatusUpdate', onStatus);
}

function onStatus(status) {
  if (status.isLoaded && pendingSeek != null) {
    const to = pendingSeek;
    pendingSeek = null;
    player.seekTo(to).catch(() => {});
  }
  if (status.didJustFinish && snapshot.track && Date.now() - startedLocal > 4000 && finishedId !== snapshot.track.id + ':' + snapshot.startedAt) {
    finishedId = snapshot.track.id + ':' + snapshot.startedAt;
    advance();
    return;
  }
  if (userStopped) return;
  if (status.playing) {
    clearTimeout(watchdog);
    if (snapshot.phase !== 'playing') set({ phase: 'playing', error: null });
  } else if (!status.isBuffering && snapshot.phase === 'playing') {
    set({ phase: 'paused' }); // an interruption (a call, another app); pressing play rejoins the live radio
  }
}

/** Fetches what is on air on the selected channel. Keeps the clock difference so the position is right even if the phone's clock is off. */
export async function loadStation() {
  const slug = snapshot.stationId;
  const { data } = await api.get(slug === 'gospel' ? '/radio' : `/radio/stations/${slug}`);
  const info = data.data;
  if (slug !== snapshot.stationId) return info; // another channel was chosen while this one was loading
  skew = info.server_time ? Date.parse(info.server_time) - Date.now() : 0;
  set({
    station: { enabled: info.enabled, name: info.name, tagline: info.tagline, songs: info.songs, kind: info.kind ?? 'music', slug: info.slug ?? slug, church: info.church ?? null },
    ...(info.now ? { upcoming: info.now.upcoming, recent: info.now.recent } : { upcoming: [], recent: [] }),
    ...(snapshot.phase === 'idle' || snapshot.phase === 'loading' ? { track: info.now?.track ?? null, startedAt: info.now?.started_at ?? null } : {}),
  });
  return info;
}

/** Fetches the channels this member can hear. An older server only has the Gospel radio, which is fine: the list stays empty. */
export async function loadStations() {
  try {
    const { data } = await api.get('/radio/stations');
    const list = data.data ?? [];
    set({ stations: list });
    // The selected channel is gone or empty (a church was unfollowed, a channel was switched off): move to the first one that has something on.
    if (list.length && !list.some((s) => s.slug === snapshot.stationId)) selectStation(list[0].slug);
  } catch {
    // Keep what we have.
  }
}

/** Switches channel. If the radio was playing, the new channel starts playing at once. */
export async function selectStation(slug) {
  if (!slug || slug === snapshot.stationId) return;
  const wasPlaying = snapshot.phase === 'playing' || snapshot.phase === 'loading';
  if (wasPlaying) stopRadio();
  queue = [];
  set({ stationId: slug, station: null, track: null, startedAt: null, upcoming: [], recent: [], error: null });
  try {
    await loadStation();
  } catch {
    // The screen shows the channel as quiet until it can be reached.
  }
  if (wasPlaying) playRadio();
}

function lockScreen(track) {
  if (Platform.OS === 'web' || !player) return;
  const metadata = { title: track.title, artist: track.artist || stationName(), albumTitle: stationName(), artworkUrl: mediaUrl(track.cover_url) };
  try {
    player.setActiveForLockScreen(true, metadata, { showSeekForward: false, showSeekBackward: false, isLiveStream: true });
  } catch {
    // Lock screen controls are a nicety; the music still plays without them.
  }
}

/** Starts a song, `offset` seconds in. */
function startTrack(track, offset = 0, startedAt = null) {
  finishedId = null;
  startedLocal = Date.now();
  pendingSeek = offset > 2 ? offset : null;
  set({ track, startedAt: startedAt ?? new Date(Date.now() + skew - offset * 1000).toISOString() });
  player.replace({ uri: mediaUrl(track.stream_url) });
  player.play();
  lockScreen(track);
}

/** The song ended: start the next one at once (no network needed), then check the clock in the background. */
async function advance() {
  if (userStopped) return;
  let next = queue.shift();
  if (!next) {
    try {
      const info = await loadStation();
      next = info.now?.track ?? null;
      queue = [...(info.now?.upcoming ?? [])];
      if (next) return startTrack(next, info.now.offset, info.now.started_at);
    } catch {
      return set({ phase: 'idle', error: 'The radio lost its connection. Press play to listen again.' });
    }
  }
  if (!next) return stop();
  startTrack(next, 0);
  resync();
}

/** Compares with the station clock: same song and nearly the same second is fine; otherwise jump to where the station is. */
async function resync() {
  if (userStopped || !player) return;
  try {
    const info = await loadStation();
    if (userStopped || !info.now) return;
    const current = snapshot.track;
    queue = [...info.now.upcoming];
    if (!current || current.id !== info.now.track.id || info.now.started_at !== snapshot.startedAt) {
      const drift = current && current.id === info.now.track.id ? Math.abs((player.currentTime ?? 0) - info.now.offset) : Infinity;
      if (drift > 15) startTrack(info.now.track, info.now.offset, info.now.started_at);
    }
  } catch {
    // Offline for a moment: the song keeps playing and the next one is already known.
  }
}

/** Joins the live radio and plays. */
export async function playRadio() {
  if (snapshot.phase === 'loading') return;
  claimAudio('radio'); // a podcast that is playing stops
  userStopped = false;
  set({ phase: 'loading', error: null });
  clearTimeout(watchdog);
  watchdog = setTimeout(() => {
    if (snapshot.phase === 'loading') {
      stopRadio();
      set({ error: 'The radio could not start. Check your connection and try again.' });
    }
  }, 25000);
  try {
    await configure();
    const info = await loadStation();
    if (!info.enabled || !info.now) {
      userStopped = true;
      clearTimeout(watchdog);
      return set({ phase: 'idle', error: 'This channel is quiet right now. Try again soon.' });
    }
    queue = [...info.now.upcoming];
    const elapsed = (Date.now() + skew - Date.parse(info.server_time)) / 1000;
    startTrack(info.now.track, info.now.offset + Math.max(0, elapsed), info.now.started_at);
  } catch (e) {
    userStopped = true;
    clearTimeout(watchdog);
    set({ phase: 'idle', error: e?.response ? errorMessage(e) : 'The radio needs an internet connection.' });
  }
}

export function stopRadio() {
  userStopped = true;
  clearTimeout(watchdog);
  pendingSeek = null;
  try {
    player?.pause();
    if (Platform.OS !== 'web') player?.clearLockScreenControls();
  } catch {
    // Already stopped.
  }
  set({ phase: 'idle' });
}

registerAudio('radio', () => {
  if (snapshot.phase !== 'idle') stopRadio();
});

export const toggleRadio = () => (snapshot.phase === 'idle' || snapshot.phase === 'paused' ? playRadio() : stopRadio());

/** Mount once in the signed-in app: re-checks the station clock whenever the app comes back to the front. */
export function useRadioLifecycle() {
  useEffect(() => {
    const sub = AppState.addEventListener('change', (s) => s === 'active' && snapshot.phase === 'playing' && resync());
    return () => sub.remove();
  }, []);
}

/** Keeps the station card fresh while it is on screen and nothing is playing. */
export function useStationInfo() {
  useEffect(() => {
    let alive = true;
    const run = () => {
      if (!alive) return;
      loadStations();
      if (snapshot.phase === 'idle') loadStation().catch(() => {});
    };
    run();
    const timer = setInterval(run, 60000);
    return () => {
      alive = false;
      clearInterval(timer);
    };
  }, []);
}
