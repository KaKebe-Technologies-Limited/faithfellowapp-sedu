// The podcast bar that stays at the bottom of every screen while an episode is loaded (above the tabs on the main
// screens): the cover, the episode and podcast names, play / pause and a close button, with a thin progress line on
// top. Tap the bar for the full player. You can read, write notes or chat while it plays.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { FONTS } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { stopPodcast, togglePodcast, usePodcast, usePodcastProgress } from '../../lib/podcastPlayer';
import AppText from '../AppText';

function Line() {
  const { position, duration } = usePodcastProgress();
  const ratio = duration > 0 ? Math.min(1, position / duration) : 0;

  return (
    <View style={styles.track}>
      <View style={[styles.fill, { width: `${ratio * 100}%` }]} />
    </View>
  );
}

/** `bottomInset`: space under the bar (the phone's home indicator) when it is not sitting on the tab bar. */
export default function PodcastMiniPlayer({ bottomInset = 0 }) {
  const { phase, episode } = usePodcast();
  if (phase === 'idle' || !episode) return null;
  const playing = phase === 'playing';

  return (
    <View style={styles.wrap}>
      <Line />
      <View style={[styles.bar, { paddingBottom: 8 + bottomInset }]}>
        <Pressable onPress={() => router.push('/listen')} style={styles.left} accessibilityRole="button" accessibilityLabel={`${episode.title}, ${episode.podcast?.name}. Open the podcast player`}>
          <Image source={{ uri: mediaUrl(episode.podcast?.cover_url) }} style={styles.cover} contentFit="cover" />
          <View style={{ flex: 1 }}>
            <AppText style={styles.title} numberOfLines={1}>
              {episode.title}
            </AppText>
            <AppText style={styles.sub} numberOfLines={1}>
              {episode.podcast?.name}
            </AppText>
          </View>
        </Pressable>
        <Pressable onPress={togglePodcast} hitSlop={8} accessibilityRole="button" accessibilityLabel={playing ? 'Pause' : 'Play'} style={styles.action}>
          {phase === 'loading' ? <ActivityIndicator color="#fff" /> : <Ionicons name={playing ? 'pause' : 'play'} size={26} color="#fff" />}
        </Pressable>
        <Pressable onPress={stopPodcast} hitSlop={8} accessibilityRole="button" accessibilityLabel="Close the player" style={styles.close}>
          <Ionicons name="close" size={22} color="rgba(255,255,255,0.7)" />
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { backgroundColor: '#25252B' },
  track: { height: 3, backgroundColor: 'rgba(255,255,255,0.12)' },
  fill: { height: 3, backgroundColor: '#FF7A9A' },
  bar: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingTop: 8, paddingHorizontal: 12 },
  left: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  cover: { width: 46, height: 46, borderRadius: 8, backgroundColor: '#3A3A44' },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: '#fff' },
  sub: { fontFamily: FONTS.medium, fontSize: 13.5, color: 'rgba(255,255,255,0.7)' },
  action: { width: 44, height: 46, alignItems: 'center', justifyContent: 'center' },
  close: { width: 34, height: 46, alignItems: 'center', justifyContent: 'center' },
});
