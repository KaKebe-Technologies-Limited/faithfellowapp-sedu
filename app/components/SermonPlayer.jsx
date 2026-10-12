// Mini audio player for a sermon (expo-audio): play/pause, back 15 s, forward 30 s and a progress bar.
// Mount it with key={sermon.id} so each sermon gets its own player.
import { useEffect } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAudioPlayer, useAudioPlayerStatus } from 'expo-audio';
import { COLORS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { formatDuration } from '../lib/format';
import AppText from './AppText';

export default function SermonPlayer({ sermon, onClose }) {
  const player = useAudioPlayer(mediaUrl(sermon.audio_url));
  const status = useAudioPlayerStatus(player);

  useEffect(() => {
    player.play();
  }, [player]);

  const duration = status.duration || sermon.duration_seconds || 0;
  const progress = duration ? Math.min(1, (status.currentTime || 0) / duration) : 0;
  const skip = (seconds) => player.seekTo(Math.max(0, Math.min(duration || Infinity, (status.currentTime || 0) + seconds)));

  return (
    <View style={styles.bar}>
      <View style={styles.track}>
        <View style={[styles.fill, { width: `${progress * 100}%` }]} />
      </View>
      <View style={styles.row}>
        <View style={styles.text}>
          <AppText variant="label" numberOfLines={1} style={{ color: COLORS.white }}>
            {sermon.title}
          </AppText>
          <AppText variant="caption" style={{ color: COLORS.primaryPale }}>
            {formatDuration(status.currentTime || 0)} / {formatDuration(duration)}
          </AppText>
        </View>
        <Pressable onPress={() => skip(-15)} hitSlop={8} accessibilityLabel="Back 15 seconds">
          <Ionicons name="play-back" size={22} color={COLORS.white} />
        </Pressable>
        <Pressable
          onPress={() => (status.playing ? player.pause() : player.play())}
          style={styles.play}
          accessibilityLabel={status.playing ? 'Pause' : 'Play'}
        >
          <Ionicons name={status.playing ? 'pause' : 'play'} size={22} color={COLORS.primary} />
        </Pressable>
        <Pressable onPress={() => skip(30)} hitSlop={8} accessibilityLabel="Forward 30 seconds">
          <Ionicons name="play-forward" size={22} color={COLORS.white} />
        </Pressable>
        <Pressable onPress={onClose} hitSlop={8} accessibilityLabel="Close player">
          <Ionicons name="close" size={22} color={COLORS.primaryPale} />
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { backgroundColor: COLORS.primaryDark, borderRadius: RADIUS.lg, padding: SPACING.md, ...SHADOW },
  track: { height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.25)', marginBottom: SPACING.sm, overflow: 'hidden' },
  fill: { height: 4, backgroundColor: COLORS.white },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  text: { flex: 1 },
  play: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
});
