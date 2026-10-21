// Listening to a sermon on its own page: one big "Listen" button, and once it plays a small player with a bar you can drag, back 15 / play / forward 30,
// the speed, and a way to open the full player. The sound comes from the app's one player, so it carries on when you leave this page, lock the phone
// or open another app. It needs no video and no YouTube.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { formatDuration } from '../../lib/format';
import { cycleRate, seekBy, seekTo, usePodcast, usePodcastProgress } from '../../lib/podcastPlayer';
import { playSermonAudio, useSermonAudio } from '../../lib/sermonAudio';
import AppText from '../AppText';
import Scrubber from '../podcast/Scrubber';

export default function SermonAudioCard({ sermon }) {
  const { current, playing, loading, toggle } = useSermonAudio(sermon);
  const { rate } = usePodcast();
  const { position, duration } = usePodcastProgress();

  if (!current) {
    return (
      <Pressable onPress={() => playSermonAudio(sermon)} style={({ pressed }) => [styles.listen, pressed && { opacity: 0.92 }]} accessibilityRole="button" accessibilityLabel="Listen to the sermon">
        <View style={styles.listenIcon}>
          <Ionicons name="headset" size={24} color={COLORS.primary} />
        </View>
        <View style={{ flex: 1 }}>
          <AppText style={styles.listenTitle}>Listen to the sermon</AppText>
          <AppText style={styles.listenHint}>
            {sermon.duration_seconds ? `${formatDuration(sermon.duration_seconds)} · ` : ''}Plays in the background, no video needed
          </AppText>
        </View>
        <Ionicons name="play-circle" size={40} color={COLORS.primary} />
      </Pressable>
    );
  }

  const total = duration || sermon.duration_seconds || 0;

  return (
    <View style={styles.card}>
      <View style={styles.top}>
        <Ionicons name={playing ? 'musical-notes' : 'headset'} size={16} color={COLORS.primaryPale} />
        <AppText style={styles.state}>{loading ? 'Getting the sermon ready…' : playing ? 'Playing now' : 'Paused'}</AppText>
        <Pressable onPress={() => router.push('/listen')} hitSlop={8} style={styles.full} accessibilityRole="button" accessibilityLabel="Open the full player">
          <AppText style={styles.fullText}>Full player</AppText>
          <Ionicons name="chevron-up" size={14} color={COLORS.primaryPale} />
        </Pressable>
      </View>

      <Scrubber position={position} duration={total} onSeek={seekTo} onStep={seekBy} />
      <View style={styles.times}>
        <AppText style={styles.time}>{formatDuration(position)}</AppText>
        <AppText style={styles.time}>{total ? `-${formatDuration(Math.max(0, total - position))}` : ''}</AppText>
      </View>

      <View style={styles.controls}>
        <Pressable onPress={cycleRate} hitSlop={8} style={styles.rate} accessibilityRole="button" accessibilityLabel={`Speed ${rate} times. Tap to change`}>
          <AppText style={styles.rateText}>{rate}×</AppText>
        </Pressable>
        <Pressable onPress={() => seekBy(-15)} hitSlop={10} accessibilityRole="button" accessibilityLabel="Back 15 seconds">
          <Ionicons name="play-back" size={26} color="#fff" />
        </Pressable>
        <Pressable onPress={toggle} style={styles.play} accessibilityRole="button" accessibilityLabel={playing ? 'Pause' : 'Play'}>
          <Ionicons name={loading ? 'hourglass-outline' : playing ? 'pause' : 'play'} size={30} color={COLORS.primary} style={playing || loading ? null : { marginLeft: 3 }} />
        </Pressable>
        <Pressable onPress={() => seekBy(30)} hitSlop={10} accessibilityRole="button" accessibilityLabel="Forward 30 seconds">
          <Ionicons name="play-forward" size={26} color="#fff" />
        </Pressable>
        <View style={styles.rate} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  listen: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.xl - 4, padding: 16 },
  listenIcon: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  listenTitle: { fontFamily: FONTS.bold, fontSize: 16.5, color: COLORS.primary },
  listenHint: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray900, marginTop: 1 },
  card: { backgroundColor: COLORS.primaryDark, borderRadius: RADIUS.xl - 4, padding: SPACING.md, ...SHADOW },
  top: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 4 },
  state: { flex: 1, fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primaryPale },
  full: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  fullText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.primaryPale },
  times: { flexDirection: 'row', justifyContent: 'space-between' },
  time: { fontFamily: FONTS.medium, fontSize: 12, color: COLORS.primaryPale },
  controls: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 8, paddingHorizontal: 4 },
  rate: { width: 48, alignItems: 'center' },
  rateText: { fontFamily: FONTS.bold, fontSize: 14, color: '#fff' },
  play: { width: 64, height: 64, borderRadius: 32, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
});
