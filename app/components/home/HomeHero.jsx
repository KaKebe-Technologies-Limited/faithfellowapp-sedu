// The top of the home screen: a deep wine panel with a soft curved edge. On it: the FaithFellows mark, the notification and messages
// buttons, and the slider of highlights. A short greeting with a smile shows for ten seconds when the app opens, then folds away so
// it never takes room. The counts come from GET /api/v1/me and refresh each time the screen is shown.
import { useCallback, useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import { firstName, greeting } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';
import AppText from '../AppText';
import Logo from '../Logo';

const WAVE = 46; // height of the curved edge, which is also the space kept under the slider
const GREETING_MS = 10000;
let greeted = false; // once per time the app is opened, not every time you come back to Home

function RoundButton({ icon, count, label, onPress }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.round, pressed && { opacity: 0.8 }]} accessibilityRole="button" accessibilityLabel={count ? `${label}, ${count} new` : label}>
      <Ionicons name={icon} size={21} color="#fff" />
      {count ? (
        <View style={styles.badge}>
          <AppText style={styles.badgeText}>{count > 99 ? '99+' : count}</AppText>
        </View>
      ) : null}
    </Pressable>
  );
}

/** "Good afternoon, Grace 😊" for ten seconds, then it folds away. */
function Hello({ name }) {
  const open = useRef(new Animated.Value(greeted ? 0 : 1)).current;
  const show = useRef(!greeted);

  useEffect(() => {
    if (!show.current) return undefined;
    greeted = true;
    const timer = setTimeout(() => Animated.timing(open, { toValue: 0, duration: 450, easing: Easing.out(Easing.cubic), useNativeDriver: false }).start(), GREETING_MS);

    return () => clearTimeout(timer);
  }, [open]);

  if (!show.current) return null;

  return (
    <Animated.View style={[styles.hello, { opacity: open, height: open.interpolate({ inputRange: [0, 1], outputRange: [0, 40] }), marginTop: open.interpolate({ inputRange: [0, 1], outputRange: [0, 10] }) }]}>
      <AppText style={styles.greeting} numberOfLines={1}>
        {name ? `${greeting()}, ${name} 😊` : `${greeting()} 😊`}
      </AppText>
    </Animated.View>
  );
}

export default function HomeHero({ user, children }) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const me = useFetch('/me');
  const mine = me.data?.data ?? user;

  useFocusEffect(
    useCallback(() => {
      me.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  // The edge: dark on the left reaching further down, rising in the middle and settling on the right, like a soft wave.
  const edge = `M0 ${WAVE + 3} L0 ${WAVE * 0.72} C${width * 0.24} ${WAVE * 0.72} ${width * 0.34} ${WAVE * 0.06} ${width * 0.58} ${WAVE * 0.12} C${width * 0.82} ${WAVE * 0.18} ${width * 0.9} ${WAVE * 0.52} ${width} ${WAVE * 0.44} L${width} ${WAVE + 3} Z`;

  return (
    <View>
      <LinearGradient colors={SOFT.panel} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[styles.panel, { paddingTop: insets.top + SPACING.sm, paddingBottom: WAVE }]}>
        <View style={styles.glowA} />
        <View style={styles.glowB} />

        <View style={styles.top}>
          <Logo size={42} />
          <View style={styles.buttons}>
            <RoundButton icon="notifications-outline" label="Notifications" count={mine?.unread_notifications ?? 0} onPress={() => router.push('/notifications')} />
            <RoundButton icon="chatbubble-ellipses-outline" label="Messages" count={mine?.unread_messages ?? 0} onPress={() => router.push('/chats')} />
          </View>
        </View>

        <Hello name={firstName(mine?.name)} />

        <View style={{ marginTop: 14 }}>{children}</View>

        <Svg width={width} height={WAVE + 3} style={styles.edge} pointerEvents="none">
          <Path d={edge} fill={SOFT.surface} />
        </Svg>
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  panel: { overflow: 'hidden' },
  glowA: { position: 'absolute', right: -70, top: -60, width: 240, height: 240, borderRadius: 120, backgroundColor: 'rgba(255,255,255,0.07)' },
  glowB: { position: 'absolute', left: -90, bottom: 30, width: 220, height: 220, borderRadius: 110, backgroundColor: 'rgba(242,126,168,0.10)' },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md },
  buttons: { flexDirection: 'row', gap: 10 },
  round: { width: 42, height: 42, borderRadius: 21, backgroundColor: SOFT.glassOnDark, borderWidth: 1, borderColor: SOFT.glassOnDarkBorder, alignItems: 'center', justifyContent: 'center' },
  badge: { position: 'absolute', top: -4, right: -4, minWidth: 18, height: 18, borderRadius: 9, paddingHorizontal: 4, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: '#4A0F29' },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10, color: '#fff' },
  hello: { alignItems: 'center', justifyContent: 'center', paddingHorizontal: SPACING.lg, overflow: 'hidden' },
  greeting: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 30, color: '#fff' },
  edge: { position: 'absolute', left: 0, bottom: -3 },
});
