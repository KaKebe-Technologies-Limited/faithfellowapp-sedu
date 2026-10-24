// First-run introduction: four slides with flat illustrations — meet people of faith, find groups and events,
// grow in the Word, give and pray together. Swipe or tap Next. Shown once; "Skip" and "Get started" both lead to
// the welcome screen and remember that the intro has been seen.
import { useEffect, useMemo, useRef, useState } from 'react';
import { Animated, PanResponder, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../components/AppText';
import Button from '../components/Button';
import Illustration from '../components/Illustration';
import { COLORS, FONTS, SPACING } from '../constants/theme';
import { markOnboarded } from '../lib/onboarding';

const SLIDES = [
  { kind: 'people', title: 'Meet people of faith around you', text: 'Find believers in your church and community, say hello, and walk the journey together.' },
  { kind: 'events', title: 'Discover groups and events', text: 'Join Bible study groups, youth nights and conferences — and keep your tickets in your pocket.' },
  { kind: 'word', title: 'Read, study and play together', text: 'Open the Bible in your language, follow reading plans with a friend and join live quizzes.' },
  { kind: 'give', title: 'Give and pray, right here', text: 'Give by mobile money, follow your campaigns and lift each other up on the prayer wall.' },
];

export default function Onboarding() {
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [index, setIndex] = useState(0);
  const slide = SLIDES[index];
  const last = index === SLIDES.length - 1;

  // Each slide eases in from the side it was swiped from.
  const enter = useRef(new Animated.Value(1)).current;
  const dir = useRef(1);
  useEffect(() => {
    enter.setValue(0);
    Animated.timing(enter, { toValue: 1, duration: 260, useNativeDriver: true }).start();
  }, [index, enter]);

  const go = (to) => {
    if (to < 0 || to >= SLIDES.length) return;
    dir.current = to > index ? 1 : -1;
    setIndex(to);
  };
  const goRef = useRef(go);
  goRef.current = go;

  const pan = useMemo(
    () =>
      PanResponder.create({
        onMoveShouldSetPanResponder: (_, g) => Math.abs(g.dx) > 18 && Math.abs(g.dx) > Math.abs(g.dy) * 1.6,
        onPanResponderRelease: (_, g) => {
          if (g.dx < -45) goRef.current(index + 1);
          else if (g.dx > 45) goRef.current(index - 1);
        },
      }),
    [index],
  );

  async function finish() {
    await markOnboarded();
    router.replace('/(auth)/welcome');
  }

  return (
    <View style={[styles.screen, { paddingTop: insets.top + 8, paddingBottom: insets.bottom + 20 }]}>
      <View style={styles.top}>
        <Pressable onPress={finish} hitSlop={12} accessibilityRole="button" accessibilityLabel="Skip the introduction">
          <AppText style={styles.skip}>{last ? '' : 'Skip'}</AppText>
        </Pressable>
      </View>

      <View style={styles.stage} {...pan.panHandlers}>
        <Animated.View
          key={slide.kind}
          style={[styles.slide, { opacity: enter, transform: [{ translateX: enter.interpolate({ inputRange: [0, 1], outputRange: [dir.current * 48, 0] }) }] }]}
        >
          <View style={styles.art}>
            <Illustration kind={slide.kind} width={Math.min(width - 48, 340)} />
          </View>
          <AppText style={styles.title}>{slide.title}</AppText>
          <AppText style={styles.text}>{slide.text}</AppText>
        </Animated.View>
      </View>

      <View style={styles.dots}>
        {SLIDES.map((s, i) => (
          <Pressable key={s.kind} onPress={() => go(i)} hitSlop={8} accessibilityLabel={`Slide ${i + 1}`}>
            <View style={[styles.dot, i === index && styles.dotOn]} />
          </Pressable>
        ))}
      </View>
      <View style={styles.actions}>
        <Button title={last ? 'Get started' : 'Next'} onPress={last ? finish : () => go(index + 1)} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.white },
  top: { height: 36, alignItems: 'flex-end', paddingHorizontal: SPACING.lg, justifyContent: 'center' },
  skip: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.gray700 },
  stage: { flex: 1, justifyContent: 'center' },
  slide: { alignItems: 'center', paddingHorizontal: SPACING.lg },
  art: { marginBottom: SPACING.lg },
  title: { fontFamily: FONTS.display, fontSize: 27, lineHeight: 34, color: COLORS.black, textAlign: 'center' },
  text: { fontFamily: FONTS.regular, fontSize: 16, lineHeight: 24, color: COLORS.gray700, textAlign: 'center', marginTop: 12, maxWidth: 340 },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 7, marginVertical: SPACING.md },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: COLORS.primaryPale },
  dotOn: { width: 26, backgroundColor: COLORS.primary },
  actions: { paddingHorizontal: SPACING.lg },
});
