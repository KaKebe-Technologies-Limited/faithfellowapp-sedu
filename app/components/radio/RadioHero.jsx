// The radio card on the Home screen: the song that is on air with its cover, "ON AIR", and the big play / stop button.
// Tap the picture for the full player (what is next, what was just played). Hidden when the radio is off or has no songs.
import { Pressable, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { useRadio, useStationInfo } from '../../lib/radio';
import AppText from '../AppText';
import RadioButton from './RadioButton';

export function RoundIconButton({ icon, label, onPress }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} style={styles.round}>
      <Ionicons name={icon} size={22} color="#fff" />
    </Pressable>
  );
}

export default function RadioHero() {
  useStationInfo();
  const { station, track, phase } = useRadio();
  if (!station?.enabled || !track) return null;
  const live = phase === 'playing' || phase === 'loading';

  return (
    <View style={styles.wrap}>
      <View style={styles.head}>
        <View style={[styles.dot, live && { backgroundColor: '#FF5E7E' }]} />
        <AppText style={styles.onAir}>{live ? 'ON AIR' : 'LISTEN LIVE'}</AppText>
        <AppText style={styles.station} numberOfLines={1}>
          {station.name}
        </AppText>
      </View>

      <Pressable onPress={() => router.push('/radio')} style={styles.card} accessibilityRole="button" accessibilityLabel={`${track.title}${track.artist ? ` by ${track.artist}` : ''}. Open the player`}>
        <Image source={{ uri: mediaUrl(track.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} />
        <LinearGradient colors={['rgba(0,0,0,0)', 'rgba(0,0,0,0.72)']} style={styles.shade} />
        <View style={styles.titles}>
          <AppText style={styles.title} numberOfLines={1}>
            {track.title}
          </AppText>
          {track.artist ? (
            <AppText style={styles.artist} numberOfLines={1}>
              {track.artist}
            </AppText>
          ) : null}
        </View>
      </Pressable>

      <View style={styles.controls}>
        <RoundIconButton icon="time-outline" label="Last played" onPress={() => router.push('/radio')} />
        <RoundIconButton icon="musical-notes-outline" label="Up next" onPress={() => router.push('/radio')} />
        <RoundIconButton icon="share-social-outline" label="Share the radio" onPress={() => Share.share({ message: `Listen to ${station.name}: gospel music, day and night, on FaithFellows.` }).catch(() => {})} />
        <RadioButton size={76} style={styles.big} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginHorizontal: SPACING.md, marginTop: SPACING.md, marginBottom: SPACING.sm, backgroundColor: '#1B1B21', borderRadius: 26, padding: 12, paddingBottom: 14 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 6, paddingBottom: 10 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#8A8A93' },
  onAir: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.2, color: '#fff' },
  station: { flex: 1, fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.6)' },
  card: { height: 190, borderRadius: 20, overflow: 'hidden', backgroundColor: '#2A2A32', justifyContent: 'flex-end' },
  shade: { ...StyleSheet.absoluteFillObject, top: '35%' },
  titles: { padding: 14, paddingRight: 100 },
  title: { fontFamily: FONTS.bold, fontSize: 18, color: '#fff' },
  artist: { fontFamily: FONTS.medium, fontSize: 14.5, color: 'rgba(255,255,255,0.72)', marginTop: 1 },
  controls: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 12, paddingHorizontal: 4 },
  round: { width: 52, height: 52, borderRadius: 26, backgroundColor: '#2E2E36', alignItems: 'center', justifyContent: 'center' },
  big: { position: 'absolute', right: 8, top: -52 },
});
