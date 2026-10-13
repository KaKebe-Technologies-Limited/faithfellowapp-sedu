// The radio bar that stays at the bottom of every screen while the music plays (above the tabs on the main screens):
// the song's cover, title and artist, and a stop button. Tap the bar for the full player.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { FONTS } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { toggleRadio, useRadio } from '../../lib/radio';
import AppText from '../AppText';

/** `bottomInset`: space under the bar (the phone's home indicator) when it is not sitting on the tab bar. */
export default function RadioMiniPlayer({ bottomInset = 0 }) {
  const { phase, track, station } = useRadio();
  if (phase === 'idle' || !track) return null;
  const playing = phase === 'playing';

  return (
    <View style={[styles.bar, { paddingBottom: 8 + bottomInset }]}>
      <Pressable onPress={() => router.push('/radio')} style={styles.left} accessibilityRole="button" accessibilityLabel={`${track.title}${track.artist ? ` by ${track.artist}` : ''}. Open the radio player`}>
        <Image source={{ uri: mediaUrl(track.cover_url) }} style={styles.cover} contentFit="cover" />
        <View style={{ flex: 1 }}>
          <AppText style={styles.title} numberOfLines={1}>
            {track.title}
          </AppText>
          <AppText style={styles.artist} numberOfLines={1}>
            {track.artist || station?.name}
          </AppText>
        </View>
      </Pressable>
      <Pressable onPress={toggleRadio} hitSlop={10} accessibilityRole="button" accessibilityLabel={playing ? 'Stop the radio' : 'Play the radio'} style={styles.action}>
        {phase === 'loading' ? <ActivityIndicator color="#fff" /> : playing ? <View style={styles.stop} /> : <Ionicons name="play" size={26} color="#fff" />}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: '#25252B', paddingTop: 8, paddingHorizontal: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.08)' },
  left: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  cover: { width: 46, height: 46, borderRadius: 8, backgroundColor: '#3A3A44' },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: '#fff' },
  artist: { fontFamily: FONTS.medium, fontSize: 13.5, color: 'rgba(255,255,255,0.7)' },
  action: { width: 46, height: 46, alignItems: 'center', justifyContent: 'center' },
  stop: { width: 24, height: 24, borderRadius: 5, backgroundColor: '#fff' },
});
