// The sounds of a quiz game: a bright chime for a right answer, a soft low "uh-oh" for a wrong one (or time running out), and a finale when the game is
// over. They are short files of our own in assets/sounds, played on their own small players. Starting a game stops the radio or a podcast that was playing
// (only one thing plays at a time, as everywhere in the app), and a speaker button on the game screen turns the sounds off; it is remembered.
import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { createAudioPlayer } from 'expo-audio';
import { claimAudio, registerAudio } from './audioFocus';
import { getStored, setStored } from './storage';

const FILES = {
  correct: require('../assets/sounds/correct.wav'),
  wrong: require('../assets/sounds/wrong.wav'),
  done: require('../assets/sounds/done.wav'),
};
const KEY = 'game_sound_v1';

let on = true; // the person's choice
let loaded = false;
const listeners = new Set();
const players = {};

const emit = () => listeners.forEach((l) => l());

async function load() {
  if (loaded) return;
  loaded = true;
  on = (await getStored(KEY, true)) !== false;
  emit();
}

function player(name) {
  if (!players[name]) players[name] = createAudioPlayer(FILES[name]);

  return players[name];
}

/** Plays one of the game sounds from the start, if sounds are on. Never allowed to break the game. */
export function playGameSound(name) {
  if (!on || !FILES[name]) return;
  try {
    const p = player(name);
    p.seekTo(0);
    p.play();
  } catch {
    // A sound is a nicety; the game goes on without it.
  }
}

/** Stops the effects and lets their players go. */
export function stopGameSounds() {
  Object.keys(players).forEach((name) => {
    try {
      players[name].pause();
      players[name].remove();
    } catch {
      // Already gone.
    }
    delete players[name];
  });
}

registerAudio('game', stopGameSounds);

/**
 * For a game screen: `{ on, toggle }` for the speaker button, and the radio or podcast that was playing stops while sounds are on, so a chime never fights
 * the music or pauses it halfway. The players are let go when the screen closes.
 */
export function useGameSound() {
  const enabled = useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => on);
  const [ready, setReady] = useState(loaded);

  useEffect(() => {
    load().then(() => setReady(true));

    return () => stopGameSounds();
  }, []);

  useEffect(() => {
    if (ready && enabled) claimAudio('game');
  }, [ready, enabled]);

  const toggle = useCallback(() => {
    on = !on;
    setStored(KEY, on);
    emit();
    if (on) playGameSound('correct'); // a little sample, so the person hears what they turned on
    else stopGameSounds();
  }, []);

  return { on: enabled, toggle };
}
