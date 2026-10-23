// The flash at the very start of the app: for a few seconds (the platform admin sets how many, how fast and the words; see lib/openingFlash.js), three
// columns of tall rounded photos roll in opposite directions (up, down, up), with small dark icon badges and sparkles floating over them, the
// FaithFellows name and a line about staying connected in faith. Then it fades away and the app is there. "Skip" opens the app at once. The photos are
// the ones the platform admin added in Super admin → App media (`photos`, a list of image sources): none are built into the app, and the layout does not
// show the flash when there are none. Phones set to reduce motion get still photos and a shorter flash.
import { useEffect, useMemo, useRef, useState } from 'react';
import { AccessibilityInfo, Animated, Easing, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SPACING } from '../constants/theme';
import { FLASH_DEFAULTS } from '../lib/openingFlash';
import AppText from './AppText';
import Logo from './Logo';

const FADE_MS = 400;
const PAGE = '#F8F4F6';
const GAP = 12;
const ASPECT = 0.52; // width / height of one photo
const SPEEDS = [62, 84, 70]; // pixels a second for each column at the normal speed (5 of 10)
const STARTS = [0, 0.55, 0.2]; // where each column starts, as a share of a photo, so the three are staggered

/** Splits the photos into three columns, one after the other, each with at least two photos (a short list is repeated). */
function columnsOf(photos) {
  const list = photos.length >= 6 ? photos : [...photos, ...photos, ...photos].slice(0, Math.max(6, photos.length));
  const columns = [[], [], []];
  list.forEach((photo, i) => columns[i % 3].push(photo));

  return columns;
}

function Pill({ source, width }) {
  const height = Math.round(width / ASPECT);

  return (
    <View style={[styles.pill, { width, height, borderRadius: width / 2, marginBottom: GAP }]}>
      <Image source={source} contentFit="cover" cachePolicy="disk" transition={0} style={{ width, height, borderRadius: width / 2 }} />
    </View>
  );
}

/** One column of photos that keeps rolling: the same list is stacked three times, so when it has rolled by one list it starts again unnoticed. */
function Column({ photos, width, left, up, speed, start, still, region }) {
  const pillHeight = Math.round(width / ASPECT) + GAP;
  const listHeight = photos.length * pillHeight;
  const roll = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (still) return undefined;
    roll.setValue(0);
    const loop = Animated.loop(Animated.timing(roll, { toValue: 1, duration: (listHeight / speed) * 1000, easing: Easing.linear, useNativeDriver: true }));
    loop.start();

    return () => loop.stop();
  }, [roll, listHeight, speed, still]);

  const offset = -start * pillHeight;
  const translateY = roll.interpolate({ inputRange: [0, 1], outputRange: up ? [offset, offset - listHeight] : [offset - listHeight, offset] });
  const copies = Math.max(3, Math.ceil(region / listHeight) + 2);

  return (
    <Animated.View style={{ position: 'absolute', top: 0, left, width, transform: [{ translateY }] }} pointerEvents="none">
      {Array.from({ length: copies }).map((_, c) => photos.map((photo, i) => <Pill key={`${c}-${i}`} source={photo} width={width} />))}
    </Animated.View>
  );
}

/** A small dark badge that floats a little over the photos. */
function Badge({ icon, style, delay = 0, still }) {
  const float = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (still) return undefined;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(float, { toValue: 1, duration: 1500, delay, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
        Animated.timing(float, { toValue: 0, duration: 1500, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
      ]),
    );
    loop.start();

    return () => loop.stop();
  }, [float, delay, still]);

  return (
    <Animated.View style={[styles.badge, style, { transform: [{ translateY: float.interpolate({ inputRange: [0, 1], outputRange: [0, -7] }) }] }]} pointerEvents="none">
      <Ionicons name={icon} size={18} color="#fff" />
    </Animated.View>
  );
}

function Sparkle({ style, size = 18, delay = 0, still }) {
  const pulse = useRef(new Animated.Value(still ? 1 : 0)).current;

  useEffect(() => {
    if (still) return undefined;
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 900, delay, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0.25, duration: 900, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
      ]),
    );
    loop.start();

    return () => loop.stop();
  }, [pulse, delay, still]);

  return (
    <Animated.Text allowFontScaling={false} pointerEvents="none" style={[styles.sparkle, { fontSize: size, opacity: pulse, transform: [{ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.7, 1.1] }) }] }, style]}>
      ✦
    </Animated.Text>
  );
}

export default function OpeningFlash({ photos, seconds = FLASH_DEFAULTS.seconds, speed = FLASH_DEFAULTS.speed, title = FLASH_DEFAULTS.title, text = FLASH_DEFAULTS.text, onDone }) {
  const { width, height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [region, setRegion] = useState(0); // the height of the area the photos roll in
  const [still, setStill] = useState(false);
  const enter = useRef(new Animated.Value(0)).current;
  const fade = useRef(new Animated.Value(1)).current;
  const progress = useRef(new Animated.Value(0)).current;
  const finished = useRef(false);
  const columns = useMemo(() => columnsOf(photos), [photos]);

  const showMs = Math.max(2, seconds) * 1000; // how long the photos roll before the flash starts to fade
  const pace = Math.max(1, Math.min(10, speed)) / 5;
  const pillWidth = Math.floor((width - SPACING.md * 2 - GAP * 2) / 3);
  const lefts = [0, 1, 2].map((i) => SPACING.md + i * (pillWidth + GAP));

  function finish() {
    if (finished.current) return;
    finished.current = true;
    Animated.timing(fade, { toValue: 0, duration: FADE_MS, easing: Easing.out(Easing.quad), useNativeDriver: true }).start(() => onDone?.());
  }

  useEffect(() => {
    let off = false;
    AccessibilityInfo.isReduceMotionEnabled?.().then((on) => !off && setStill(!!on)).catch(() => {});

    return () => {
      off = true;
    };
  }, []);

  useEffect(() => {
    Animated.timing(enter, { toValue: 1, duration: 650, easing: Easing.out(Easing.cubic), useNativeDriver: true }).start();
    const wait = still ? Math.min(showMs, 2800) : showMs;
    Animated.timing(progress, { toValue: 1, duration: wait, easing: Easing.linear, useNativeDriver: false }).start();
    const timer = setTimeout(finish, wait);

    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [still]);

  const rise = enter.interpolate({ inputRange: [0, 1], outputRange: [26, 0] });
  const small = height < 700;

  return (
    <Animated.View style={[StyleSheet.absoluteFill, styles.page, { opacity: fade, paddingTop: insets.top + 10, paddingBottom: Math.max(insets.bottom, 12) + 14 }]} accessibilityViewIsModal accessibilityLabel="Welcome to FaithFellows">
      <View style={styles.glowA} pointerEvents="none" />
      <View style={styles.glowB} pointerEvents="none" />

      <View style={styles.top}>
        <View style={styles.brand}>
          <Logo size={34} plain />
          <AppText style={styles.brandName}>FaithFellows</AppText>
        </View>
        <Pressable onPress={finish} hitSlop={12} style={styles.skip} accessibilityRole="button" accessibilityLabel="Skip the welcome">
          <AppText style={styles.skipText}>Skip</AppText>
        </Pressable>
      </View>
      <View style={styles.track}>
        <Animated.View style={[styles.fill, { width: progress.interpolate({ inputRange: [0, 1], outputRange: ['0%', '100%'] }) }]} />
      </View>

      <Animated.View style={[styles.region, { opacity: enter, transform: [{ translateY: rise }] }]} onLayout={(e) => setRegion(Math.round(e.nativeEvent.layout.height))}>
        {region > 0
          ? columns.map((column, i) => <Column key={i} photos={column} width={pillWidth} left={lefts[i]} up={i !== 1} speed={SPEEDS[i] * pace} start={STARTS[i]} still={still} region={region} />)
          : null}

        <LinearGradient colors={[PAGE, 'rgba(248,244,246,0)']} style={styles.fadeTop} pointerEvents="none" />
        <LinearGradient colors={['rgba(248,244,246,0)', PAGE]} style={styles.fadeBottom} pointerEvents="none" />

        <Badge icon="musical-notes" still={still} style={{ left: lefts[1] + pillWidth / 2 - 19, top: region * 0.3 }} />
        <Badge icon="hand-left" still={still} delay={500} style={{ left: lefts[0] + pillWidth / 2 - 19, top: region * 0.58 }} />
        <Badge icon="heart" still={still} delay={1000} style={{ left: lefts[2] + pillWidth / 2 - 19, top: region * 0.6 }} />
        <Sparkle still={still} size={22} style={{ left: 6, top: region * 0.16, color: '#F2B58C' }} />
        <Sparkle still={still} size={30} delay={400} style={{ right: 4, top: region * 0.4, color: '#7FC8BD' }} />
        <Sparkle still={still} size={16} delay={800} style={{ left: lefts[1] + pillWidth + 1, top: region * 0.82, color: '#F2B58C' }} />
      </Animated.View>

      <Animated.View style={[styles.text, { opacity: enter, transform: [{ translateY: rise }] }]}>
        <AppText style={[styles.title, small && { fontSize: 24, lineHeight: 30 }]}>{title}</AppText>
        <AppText style={[styles.subtitle, small && { fontSize: 14, lineHeight: 20 }]}>{text}</AppText>
      </Animated.View>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  page: { backgroundColor: PAGE, zIndex: 100, elevation: 100 },
  glowA: { position: 'absolute', right: -90, top: -70, width: 280, height: 280, borderRadius: 140, backgroundColor: 'rgba(188,38,73,0.06)' },
  glowB: { position: 'absolute', left: -110, bottom: 120, width: 260, height: 260, borderRadius: 130, backgroundColor: 'rgba(242,126,168,0.10)' },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md },
  brand: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  brandName: { fontFamily: FONTS.bold, fontSize: 18, color: '#2B1B22' },
  skip: { paddingHorizontal: 14, paddingVertical: 7, borderRadius: 16, backgroundColor: 'rgba(43,27,34,0.06)' },
  skipText: { fontFamily: FONTS.semibold, fontSize: 13, color: '#6F5F68' },
  track: { height: 3, borderRadius: 2, marginHorizontal: SPACING.md, marginTop: 12, backgroundColor: 'rgba(188,38,73,0.14)', overflow: 'hidden' },
  fill: { height: 3, borderRadius: 2, backgroundColor: COLORS.primary },
  region: { flex: 1, marginTop: 14, overflow: 'hidden' },
  fadeTop: { position: 'absolute', left: 0, right: 0, top: 0, height: 46 },
  fadeBottom: { position: 'absolute', left: 0, right: 0, bottom: 0, height: 90 },
  pill: { backgroundColor: '#EADDE3', shadowColor: '#A67C8E', shadowOpacity: 0.3, shadowRadius: 12, shadowOffset: { width: 0, height: 6 }, elevation: 4 },
  badge: { position: 'absolute', width: 38, height: 38, borderRadius: 13, backgroundColor: '#3A0C22', alignItems: 'center', justifyContent: 'center', shadowColor: '#F27EA8', shadowOpacity: 0.7, shadowRadius: 14, shadowOffset: { width: 0, height: 0 }, elevation: 8 },
  sparkle: { position: 'absolute' },
  text: { paddingHorizontal: SPACING.lg, paddingTop: 6, alignItems: 'center' },
  title: { fontFamily: FONTS.bold, fontSize: 28, lineHeight: 34, color: '#2B1B22', textAlign: 'center' },
  subtitle: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: '#6F5F68', textAlign: 'center', marginTop: 8 },
});
