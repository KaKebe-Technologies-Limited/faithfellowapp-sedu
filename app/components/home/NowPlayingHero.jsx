// The top of Home, shaped like a radio app: a big LIVE card for the radio (its name, the pulsing LIVE mark and sound bars that dance while you
// listen; never the song or who made it, because the radio is live and everyone just listens) with the glowing play / stop button on the
// card's lower edge, and three small buttons right under the card at the left, on the same line as the play button's lower half: pray, share and
// give, soft white like the Explore tiles (a round icon each on a narrow phone, an icon with its name on a wider one). Press the card for the full player. `useHomeRadio()` says
// whether this can show ('ready'), is still waiting for the radio ('loading') or has no radio to show ('none': the radio is switched off,
// empty, or the phone is offline), so Home can fall back to the slider of highlights.
import { useEffect } from 'react';
import { Image, Pressable, Share, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { useFeatures } from '../../lib/features';
import { selectStation, useRadio, useStationInfo } from '../../lib/radio';
import AppText from '../AppText';
import Equalizer from '../radio/Equalizer';
import LiveBadge from '../radio/LiveBadge';
import RadioButton from '../radio/RadioButton';

const MARK = require('../../assets/logo-mark.png');
const BUTTON = 76;
const ACTIONS = 56; // the height of the row of small buttons (the play button's lower half shares it), kept as an empty space while the radio loads

/** Keeps the radio fresh on Home. Home is the music radio: a podcast channel chosen earlier (and not playing) gives way to it. */
export function useHomeRadio() {
  const features = useFeatures();
  useStationInfo();
  const { phase, track, station, stations, unreachable } = useRadio();
  const music = stations.find((s) => s.kind !== 'talk');
  const moving = phase === 'idle' && station?.kind === 'talk' && !!music;

  useEffect(() => {
    if (moving) selectStation(music.slug, { auto: true });
  }, [moving, music?.slug]);

  if (!features.radio) return 'none';
  if (track && !moving) return 'ready';

  return (station || unreachable) && !moving ? 'none' : 'loading';
}

/** A small soft white button under the card (like the Explore tiles): a coloured icon with its name, or just the icon on a narrow phone. */
function Action({ icon, color, label, onPress, compact }) {
  return (
    <Pressable onPress={onPress} hitSlop={6} style={({ pressed }) => [styles.action, compact ? styles.actionRound : styles.actionPill, pressed && { opacity: 0.85, transform: [{ scale: 0.97 }] }]} accessibilityRole="button" accessibilityLabel={label}>
      <Ionicons name={icon} size={compact ? 18 : 16} color={color} />
      {compact ? null : <AppText style={styles.actionLabel}>{label}</AppText>}
    </Pressable>
  );
}

export default function NowPlayingHero({ state, home }) {
  const { width } = useWindowDimensions();
  const { phase, station, broadcast } = useRadio();
  const height = Math.max(200, Math.min(250, Math.round((width - SPACING.md * 2) * 0.62)));
  const compact = width < 380; // three names and the play button do not fit side by side on a narrow phone

  if (state !== 'ready') {
    return (
      <View style={{ paddingHorizontal: SPACING.md }}>
        <View style={[styles.card, styles.waiting, { height }]} />
        <View style={{ height: ACTIONS }} />
      </View>
    );
  }

  const live = phase === 'playing';
  const name = station?.name ?? 'FaithFellows Radio';
  const tagline = station?.tagline || 'Gospel music, day and night';
  const church = home?.churches?.[0];
  const several = (home?.churches?.length ?? 0) > 1;
  const openPlayer = () => router.push(station?.kind === 'talk' ? { pathname: '/radio', params: { kind: 'talk' } } : '/radio');
  const give = () => (church && !several ? router.push({ pathname: '/give/[slug]', params: { slug: church.slug } }) : router.push('/(tabs)/give'));
  const share = () => Share.share({ message: `Listen to ${name} live: ${tagline.toLowerCase()}, on FaithFellows.` }).catch(() => {});

  return (
    <View style={{ paddingHorizontal: SPACING.md }}>
      <Pressable onPress={openPlayer} style={[styles.card, { height }]} accessibilityRole="button" accessibilityLabel={`${name}, live. Open the radio player`}>
        <LinearGradient colors={['#5B1431', '#BC2649', '#F27EA8']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        <Image source={MARK} resizeMode="contain" pointerEvents="none" style={styles.mark} />
        <View style={styles.glow} pointerEvents="none" />
        <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.55)']} locations={[0.3, 1]} style={StyleSheet.absoluteFill} pointerEvents="none" />

        <View style={styles.top} pointerEvents="none">
          <LiveBadge onAir={live} />
          <Equalizer playing={live} height={26} />
        </View>

        <View style={styles.text} pointerEvents="none">
          <AppText style={styles.kicker} numberOfLines={1}>
            {broadcast ? 'SPECIAL BROADCAST' : live ? "YOU'RE LISTENING LIVE" : 'TAP PLAY TO LISTEN LIVE'}
          </AppText>
          <AppText style={styles.title} numberOfLines={broadcast ? 2 : 1} adjustsFontSizeToFit minimumFontScale={0.75}>
            {broadcast ? broadcast.message || broadcast.title : name}
          </AppText>
          <AppText style={styles.tagline} numberOfLines={2}>
            {broadcast ? `Playing now for everyone on FaithFellows` : tagline}
          </AppText>
        </View>
      </Pressable>

      <View style={[styles.play, { top: height - BUTTON / 2 }]}>
        <RadioButton size={BUTTON} />
      </View>

      <View style={styles.actions}>
        <Action icon="hand-left-outline" color="#E65100" label="Pray" onPress={() => router.push('/prayer')} compact={compact} />
        <Action icon="share-social-outline" color="#1D5FD1" label="Share" onPress={share} compact={compact} />
        <Action icon="heart-outline" color="#12704F" label="Give" onPress={give} compact={compact} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 28, overflow: 'hidden', backgroundColor: '#7D1A39', borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.25)' },
  waiting: { backgroundColor: 'rgba(255,255,255,0.08)', borderColor: 'rgba(255,255,255,0.12)' },
  mark: { position: 'absolute', right: -34, top: -30, width: 250, height: 250, tintColor: '#fff', opacity: 0.13 },
  glow: { position: 'absolute', left: -60, bottom: -70, width: 220, height: 220, borderRadius: 110, backgroundColor: 'rgba(255,255,255,0.1)' },
  top: { position: 'absolute', left: 16, right: 16, top: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  text: { position: 'absolute', left: 18, right: 18, bottom: 18 },
  kicker: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 1.5, color: 'rgba(255,255,255,0.8)', marginBottom: 4 },
  title: { fontFamily: FONTS.bold, fontSize: 25, lineHeight: 30, color: '#fff' },
  tagline: { fontFamily: FONTS.medium, fontSize: 14, lineHeight: 20, color: 'rgba(255,255,255,0.88)', marginTop: 2, marginRight: BUTTON + 22 },
  play: { position: 'absolute', right: SPACING.md + 18 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 10, height: ACTIONS - 10, paddingRight: BUTTON + 30 },
  action: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', backgroundColor: SOFT.glass, borderWidth: 1.5, borderColor: SOFT.glassBorder, ...SOFT_SHADOW },
  actionPill: { height: 36, gap: 6, paddingHorizontal: 13, borderRadius: 18 },
  actionRound: { width: 38, height: 38, borderRadius: 19 },
  actionLabel: { fontFamily: FONTS.semibold, fontSize: 12.5, color: SOFT.ink },
});
