// Listening to a sermon without video: the audio plays in the app's one player (the same one the podcasts use), so it keeps playing with the screen
// locked or while you read, write notes or chat, shows on the lock screen, remembers where you stopped, and the bar above the tabs controls it.
// Nobody needs YouTube to hear a sermon that has an audio recording.
import { mediaUrl } from './api';
import { playEpisode, togglePodcast, usePodcast } from './podcastPlayer';

/** A sermon in the shape the player knows: the preacher (or the church) stands where a podcast's name does. */
export function sermonEpisode(s) {
  return {
    id: `sermon-${s.id}`,
    source: 'sermon',
    sermon_id: s.id,
    title: s.title,
    audio_url: mediaUrl(s.audio_url),
    duration: s.duration_seconds || 0,
    summary: [s.scripture, s.church?.name].filter(Boolean).join(' · ') || null,
    podcast: { id: null, name: s.preacher || s.church?.name || 'Sermon', host: s.preacher ? s.church?.name : null, cover_url: s.thumbnail_url },
  };
}

/** Plays a sermon's audio now (or pauses it when it is the one playing). The sermons after it in `list` that have audio play next. */
export function playSermonAudio(s, list = []) {
  if (!s?.audio_url) return;
  return playEpisode(sermonEpisode(s), list.filter((x) => x.audio_url).map(sermonEpisode));
}

/** Whether this sermon is the one in the player, and whether it is playing right now. */
export function useSermonAudio(s) {
  const { episode, phase } = usePodcast();
  const current = Boolean(s) && episode?.id === `sermon-${s.id}` && phase !== 'idle';

  return { current, playing: current && phase === 'playing', loading: current && phase === 'loading', toggle: togglePodcast };
}
