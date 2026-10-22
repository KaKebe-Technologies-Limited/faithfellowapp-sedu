// The songs and teaching in a church's own channel, played one by one from the church's page. They play in the app's one player (the one the podcasts and
// sermons use), so they keep going with the screen locked, show on the lock screen, and the bar above the tabs controls them. The next one in the list
// plays after it.
import { mediaUrl } from './api';
import { playEpisode, togglePodcast, usePodcast } from './podcastPlayer';

/** A track of the church's channel in the shape the player knows: the artist (or the church) stands where a podcast's name does. */
export function trackEpisode(t, church) {
  return {
    id: `track-${t.id}`,
    source: 'track',
    title: t.title,
    audio_url: mediaUrl(t.stream_url),
    duration: t.duration || 0,
    summary: church?.name ?? null,
    podcast: { id: null, name: t.artist || church?.name || 'Church audio', host: t.artist ? church?.name : null, cover_url: t.cover_url },
  };
}

/** Plays a track now. The tracks after it in `list` play next. */
export const playChurchTrack = (t, list, church) => playEpisode(trackEpisode(t, church), (list ?? [t]).map((x) => trackEpisode(x, church)));

/** Whether this track is the one in the player, and whether it is playing right now. */
export function useChurchTrack(t) {
  const { episode, phase } = usePodcast();
  const current = Boolean(t) && episode?.id === `track-${t.id}` && phase !== 'idle';

  return { current, playing: current && phase === 'playing', loading: current && phase === 'loading', toggle: togglePodcast };
}
