// The top of Home, shaped like a radio app: a big card with the song that is on air (its cover, the title and artist over it) and the
// glowing play / stop button sitting on the card's lower edge, and three small round buttons under it: what comes next, share the
// radio and give. Press the card for the full player. `useHomeRadio()` says whether this can show ('ready'), is still waiting for the
// radio ('loading') or has no radio to show ('none': the radio is switched off, empty, or the phone is offline), so Home can fall
// back to the slider of highlights.
import { useEffect } from 'react';
import { Pressable, Share, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import { mediaUrl } from '../../lib/api';
import { useFeatures } from '../../lib/features';
import { selectStation, useRadio, useStationInfo } from '../../lib/radio';
import AppText from '../AppText';
import RadioButton from '../radio/RadioButton';

const BUTTON = 76;
const ACTIONS = 86; // the height of the row of round buttons, kept as an empty space while the radio loads

/** Keeps the radio fresh on Home. Home is the music radio: a podcast channel chosen earlier (and not playing) gives way to it. */
export function useHomeRadio() {
  const features = useFeatures();
  useStationInfo();
  const { phase, track, station, stations, unreachable } = useRadio();
  const music = stations.find((s) => s.kind !== 'talk');
  const moving = phase === 'idle' && station?.kind === 'talk' && !!music;

  useEffect(() => {
    if (moving) selectStation(music.slug);
  }, [moving, music?.slug]);

  if (!features.radio) return 'none';
  if (track && !moving) return 'ready';

  return (station || unreachable) && !moving ? 'none' : 'loading';
}

function Action({ icon, label, onPress }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.action, pressed && { opacity: 0.8 }]} accessibilityRole="button" accessibilityLabel={label}>
      <View style={styles.round}>
        <Ionicons name={icon} size={22} color="#fff" />
      </View>
      <AppText style={styles.actionLabel}>{label}</AppText>
    </Pressable>
  );
}

export default function NowPlayingHero({ state, home }) {
  const { width } = useWindowDimensions();
  const { phase, track, station, upcoming } = useRadio();
  const height = Math.min(300, Math.round((width - SPACING.md * 2) * 0.78));

  if (state !== 'ready' || !track) {
    return (
      <View style={{ paddingHorizontal: SPACING.md }}>
        <View style={[styles.card, styles.waiting, { height }]} />
        <View style={{ height: ACTIONS }} />
      </View>
    );
  }

  const live = phase === 'playing';
  const name = station?.name ?? 'FaithFellows Radio';
  const next = upcoming?.[0];
  const church = home?.churches?.[0];
  const several = (home?.churches?.length ?? 0) > 1;
  const openPlayer = () => router.push(station?.kind === 'talk' ? { pathname: '/radio', params: { kind: 'talk' } } : '/radio');
  const give = () => (church && !several ? router.push({ pathname: '/give/[slug]', params: { slug: church.slug } }) : router.push('/(tabs)/give'));
  const share = () => Share.share({ message: `Listen to ${name}: ${(station?.tagline ?? 'Gospel music, day and night').toLowerCase()}, on FaithFellows.` }).catch(() => {});

  return (
    <View style={{ paddingHorizontal: SPACING.md }}>
      <Pressable onPress={openPlayer} style={[styles.card, { height }]} accessibilityRole="button" accessibilityLabel={`${track.title}${track.artist ? ` by ${track.artist}` : ''} on ${name}. Open the radio player`}>
        <LinearGradient colors={['#5B1431', COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        {track.cover_url ? <Image source={{ uri: mediaUrl(track.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} /> : null}
        <LinearGradient colors={['rgba(20,6,12,0.25)', 'rgba(20,6,12,0)', 'rgba(20,6,12,0.32)', 'rgba(20,6,12,0.88)']} locations={[0, 0.25, 0.55, 1]} style={StyleSheet.absoluteFill} />

        <View style={styles.status}>
          <View style={[styles.dot, live && { backgroundColor: '#5CFF9A' }]} />
          <AppText style={styles.statusText}>{live ? 'ON AIR' : 'LISTEN LIVE'}</AppText>
        </View>

        <View style={styles.text} pointerEvents="none">
          <AppText style={styles.kicker} numberOfLines={1}>
            {name.toUpperCase()}
          </AppText>
          <AppText style={styles.title} numberOfLines={2}>
            {track.title}
          </AppText>
          {track.artist ? (
            <AppText style={styles.artist} numberOfLines={1}>
              {track.artist}
            </AppText>
          ) : null}
          {next ? (
            <AppText style={styles.next} numberOfLines={1}>
              Up next · {next.title}
            </AppText>
          ) : null}
        </View>
      </Pressable>

      <View style={[styles.play, { top: height - BUTTON / 2 }]}>
        <RadioButton size={BUTTON} />
      </View>

      <View style={styles.actions}>
        <Action icon="time-outline" label="Up next" onPress={openPlayer} />
        <Action icon="share-social-outline" label="Share" onPress={share} />
        <Action icon="heart-outline" label="Give" onPress={give} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 28, overflow: 'hidden', backgroundColor: COLORS.primaryDark, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.25)' },
  waiting: { backgroundColor: 'rgba(255,255,255,0.08)', borderColor: 'rgba(255,255,255,0.12)' },
  status: { position: 'absolute', left: 16, top: 16, flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: 'rgba(20,6,12,0.45)', borderRadius: 999, paddingHorizontal: 11, paddingVertical: 5 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#FF7A9A' },
  statusText: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.3, color: '#fff' },
  text: { position: 'absolute', left: 18, right: BUTTON + 38, bottom: 18 },
  kicker: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 1.5, color: 'rgba(255,255,255,0.78)', marginBottom: 4 },
  title: { fontFamily: FONTS.bold, fontSize: 24, lineHeight: 29, color: '#fff' },
  artist: { fontFamily: FONTS.medium, fontSize: 15, lineHeight: 21, color: 'rgba(255,255,255,0.9)', marginTop: 2 },
  next: { fontFamily: FONTS.medium, fontSize: 12, lineHeight: 17, color: 'rgba(255,255,255,0.68)', marginTop: 5 },
  play: { position: 'absolute', right: SPACING.md + 18 },
  actions: { flexDirection: 'row', gap: 20, marginTop: 16, height: ACTIONS - 16 },
  action: { alignItems: 'center', width: 56 },
  round: { width: 52, height: 52, borderRadius: 26, backgroundColor: SOFT.glassOnDark, borderWidth: 1, borderColor: SOFT.glassOnDarkBorder, alignItems: 'center', justifyContent: 'center' },
  actionLabel: { fontFamily: FONTS.semibold, fontSize: 11.5, color: 'rgba(255,255,255,0.82)', marginTop: 6 },
});
