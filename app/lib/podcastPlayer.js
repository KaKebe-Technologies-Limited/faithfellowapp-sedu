// The podcast player: plays an episode from the link the podcaster added, keeps playing with the screen locked or the
// app in the background, shows the episode on the lock screen, remembers where you stopped, and moves on to the next
// episode of the list you started from. One player lives here for the whole app, so the episode carries on while you
// take notes, read the Bible or chat; the bar above the tabs and the full player read the same state.
import { useSyncExternalStore } from 'react';
import { Platform } from 'react-native';
import { createAudioPlayer, setAudioModeAsync } from 'expo-audio';
import api, { mediaUrl } from './api';
import { claimAudio, registerAudio } from './audioFocus';
import { readJson, writeJson } from './localFiles';

const PROGRESS_FILE = 'podcasts/progress.json';
export const RATES = [1, 1.25, 1.5, 1.75, 2, 0.75];

let snapshot = {
  phase: 'idle', // idle | loading | playing | paused
  episode: null, // { id, title, audio_url, duration, podcast: { id, name, host, cover_url } }
  queue: [], // the episodes that follow this one
  rate: 1,
  error: null,
};
const listeners = new Set();
const set = (patch) => {
  snapshot = { ...snapshot, ...patch };
  listeners.forEach((l) => l());
};

// The position changes every half second, so it has its own store: only the progress bar redraws that often.
let progress = { position: 0, duration: 0 };
const progressListeners = new Set();
const setProgress = (p) => {
  progress = p;
  progressListeners.forEach((l) => l());
};

/** `{ phase, episode, queue, rate, error }` */
export const usePodcast = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => snapshot);
/** `{ position, duration }` in seconds */
export const usePodcastProgress = () => useSyncExternalStore((l) => (progressListeners.add(l), () => progressListeners.delete(l)), () => progress);

let player = null;
let configured = false;
let pendingSeek = null;
let lastSaved = 0;
let saved = null; // { [episodeId]: seconds }
let watchdog = null;

async function loadSaved() {
  saved ??= (await readJson(PROGRESS_FILE)) ?? {};
  return saved;
}

async function remember(episodeId, seconds) {
  const all = await loadSaved();
  if (seconds > 5) all[episodeId] = Math.floor(seconds);
  else delete all[episodeId];
  // Keep the file small: the last 60 episodes.
  const keys = Object.keys(all);
  if (keys.length > 60) delete all[keys[0]];
  writeJson(PROGRESS_FILE, all);
}

async function configure() {
  if (configured) return;
  configured = true;
  await setAudioModeAsync({ playsInSilentMode: true, shouldPlayInBackground: true, interruptionMode: 'doNotMix' }).catch(() => {});
  player = createAudioPlayer(null, { updateInterval: 500, keepAudioSessionActive: true });
  player.addListener('playbackStatusUpdate', onStatus);
}

function onStatus(status) {
  const episode = snapshot.episode;
  if (!episode) return;
  if (status.isLoaded && pendingSeek != null) {
    const to = pendingSeek;
    pendingSeek = null;
    player.seekTo(to).catch(() => {});
  }
  if (status.isLoaded) {
    const duration = status.duration > 0 ? status.duration : episode.duration || 0;
    setProgress({ position: status.currentTime ?? 0, duration });
    if (Date.now() - lastSaved > 5000 && status.playing) {
      lastSaved = Date.now();
      remember(episode.id, status.currentTime ?? 0);
    }
  }
  if (status.didJustFinish) {
    remember(episode.id, 0);
    return nextEpisode(true);
  }
  if (status.playing) {
    clearTimeout(watchdog);
    if (snapshot.phase !== 'playing') set({ phase: 'playing', error: null });
  } else if (!status.isBuffering && snapshot.phase === 'playing') {
    set({ phase: 'paused' }); // paused from the lock screen, or interrupted by a call
  }
}

function lockScreen(episode) {
  if (Platform.OS === 'web' || !player) return;
  try {
    player.setActiveForLockScreen(true, { title: episode.title, artist: episode.podcast?.name ?? 'Podcast', albumTitle: episode.podcast?.host ?? 'FaithFellows', artworkUrl: mediaUrl(episode.podcast?.cover_url) }, { showSeekForward: true, showSeekBackward: true });
  } catch {
    // Lock screen controls are a nicety; the episode still plays without them.
  }
}

/** Plays an episode. `list` is the episodes shown with it, newest first; the one after it in the list plays next. */
export async function playEpisode(episode, list = []) {
  if (snapshot.episode?.id === episode.id && (snapshot.phase === 'paused' || snapshot.phase === 'playing')) return togglePodcast();
  claimAudio('podcast'); // the radio, if it is playing, stops
  const at = list.findIndex((e) => e.id === episode.id);
  set({ phase: 'loading', episode, queue: at >= 0 ? list.slice(at + 1) : [], error: null });
  setProgress({ position: 0, duration: episode.duration || 0 });
  clearTimeout(watchdog);
  watchdog = setTimeout(() => {
    if (snapshot.phase === 'loading') {
      player?.pause();
      set({ phase: 'idle', episode: null, error: 'This episode could not start. Check your connection and try again.' });
    }
  }, 25000);
  try {
    await configure();
    const resumeAt = (await loadSaved())[episode.id] ?? 0;
    pendingSeek = resumeAt > 5 && (!episode.duration || resumeAt < episode.duration - 10) ? resumeAt : null;
    player.replace({ uri: episode.audio_url });
    player.setPlaybackRate(snapshot.rate);
    player.play();
    lockScreen(episode);
    api.post(`/podcast-episodes/${episode.id}/played`).catch(() => {});
  } catch {
    clearTimeout(watchdog);
    set({ phase: 'idle', episode: null, error: 'This episode could not start.' });
  }
}

export function togglePodcast() {
  if (!player || !snapshot.episode) return;
  if (snapshot.phase === 'playing') {
    player.pause();
    remember(snapshot.episode.id, player.currentTime ?? 0);
    set({ phase: 'paused' });
  } else {
    claimAudio('podcast');
    player.play();
    set({ phase: snapshot.phase === 'paused' ? 'playing' : snapshot.phase });
  }
}

export function seekTo(seconds) {
  if (!player || !snapshot.episode) return;
  const to = Math.max(0, Math.min(seconds, progress.duration || seconds));
  setProgress({ ...progress, position: to });
  player.seekTo(to).catch(() => {});
}

export const seekBy = (delta) => seekTo((player?.currentTime ?? progress.position) + delta);

export function setRate(rate) {
  set({ rate });
  try {
    player?.setPlaybackRate(rate);
  } catch {
    // Not every player supports a speed change.
  }
}

/** Cycles 1×, 1.25×, 1.5×, 1.75×, 2×, 0.75×. */
export const cycleRate = () => setRate(RATES[(RATES.indexOf(snapshot.rate) + 1) % RATES.length]);

/** Plays the next episode of the list; with `auto` (the episode ended) it stops quietly when the list is over. */
export function nextEpisode(auto = false) {
  const [next, ...rest] = snapshot.queue;
  if (!next) return auto ? stopPodcast() : seekTo(progress.duration);
  const queue = rest;
  set({ episode: next, queue, phase: 'loading', error: null });
  setProgress({ position: 0, duration: next.duration || 0 });
  loadSaved().then((all) => {
    pendingSeek = all[next.id] > 5 ? all[next.id] : null;
    player.replace({ uri: next.audio_url });
    player.setPlaybackRate(snapshot.rate);
    player.play();
    lockScreen(next);
    api.post(`/podcast-episodes/${next.id}/played`).catch(() => {});
  });
}

/** Back to the start when you are more than a few seconds in; otherwise it only restarts too (episodes are not a queue backwards). */
export const restartEpisode = () => seekTo(0);

export function stopPodcast() {
  clearTimeout(watchdog);
  pendingSeek = null;
  if (snapshot.episode) remember(snapshot.episode.id, player?.currentTime ?? 0);
  try {
    player?.pause();
    if (Platform.OS !== 'web') player?.clearLockScreenControls();
  } catch {
    // Already stopped.
  }
  set({ phase: 'idle', episode: null, queue: [] });
  setProgress({ position: 0, duration: 0 });
}

registerAudio('podcast', () => {
  if (snapshot.phase !== 'idle') stopPodcast();
});
