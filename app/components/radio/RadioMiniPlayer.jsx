// The radio bar that stays at the bottom of every screen while the radio plays (above the tabs on the main screens): the radio's name
// with the LIVE mark and a stop button (the music radio is live, so no song or artist is shown; a podcast channel names its teaching).
// Tap the bar for the full player.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import LiveBadge from './LiveBadge';
import { router } from 'expo-router';
import { FONTS } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { toggleRadio, useRadio } from '../../lib/radio';
import AppText from '../AppText';

/** `bottomInset`: space under the bar (the phone's home indicator) when it is not sitting on the tab bar. */
const MARK = require('../../assets/logo-mark.png');

export default function RadioMiniPlayer({ bottomInset = 0 }) {
  const { phase, track, station } = useRadio();
  if (phase === 'idle' || !track) return null;
  const playing = phase === 'playing';
  const talk = station?.kind === 'talk';

  return (
    <View style={[styles.bar, { paddingBottom: 8 + bottomInset }]}>
      <Pressable onPress={() => router.push(station?.kind === 'talk' ? { pathname: '/radio', params: { kind: 'talk' } } : '/radio')} style={styles.left} accessibilityRole="button" accessibilityLabel={talk ? `${track.title}${track.artist ? ` by ${track.artist}` : ''}. Open the radio player` : `${station?.name ?? 'FaithFellows Radio'}, live. Open the radio player`}>
        {talk ? <Image source={{ uri: mediaUrl(track.cover_url) }} style={styles.cover} contentFit="cover" /> : <View style={[styles.cover, styles.mark]}><Image source={MARK} style={styles.markImage} contentFit="contain" /></View>}
        <View style={{ flex: 1 }}>
          <AppText style={styles.title} numberOfLines={1}>
            {talk ? track.title : station?.name ?? 'FaithFellows Radio'}
          </AppText>
          {talk ? (
            <AppText style={styles.artist} numberOfLines={1}>
              {track.artist || station?.name}
            </AppText>
          ) : (
            <LiveBadge small onAir={playing} style={{ backgroundColor: 'transparent', paddingHorizontal: 0, paddingVertical: 2 }} />
          )}
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
  mark: { backgroundColor: '#7D1A39', alignItems: 'center', justifyContent: 'center' },
  markImage: { width: 32, height: 32, tintColor: '#fff' },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: '#fff' },
  artist: { fontFamily: FONTS.medium, fontSize: 13.5, color: 'rgba(255,255,255,0.7)' },
  action: { width: 46, height: 46, alignItems: 'center', justifyContent: 'center' },
  stop: { width: 24, height: 24, borderRadius: 5, backgroundColor: '#fff' },
});
