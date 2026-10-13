// The one player bar of the app: the podcast episode when one is loaded, otherwise the gospel radio while it plays.
// Used above the tabs and at the bottom of every other screen.
import { usePodcast } from '../lib/podcastPlayer';
import PodcastMiniPlayer from './podcast/PodcastMiniPlayer';
import RadioMiniPlayer from './radio/RadioMiniPlayer';

export default function NowPlayingBar({ bottomInset = 0 }) {
  const { phase } = usePodcast();

  return phase !== 'idle' ? <PodcastMiniPlayer bottomInset={bottomInset} /> : <RadioMiniPlayer bottomInset={bottomInset} />;
}
