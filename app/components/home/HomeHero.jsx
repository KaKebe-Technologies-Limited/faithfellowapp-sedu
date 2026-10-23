// The top of the home screen: a deep wine panel with clean rounded corners at the bottom. On it: the FaithFellows mark and name, the
// notification and messages buttons, and the radio card (or the slider of highlights). A short greeting with a smile shows for ten
// seconds when the app opens, then folds away so it never takes room. The counts come from GET /api/v1/me and refresh each time the
// screen is shown.
import { useCallback, useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import { firstName, greeting } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';
import AppText from '../AppText';
import Logo from '../Logo';

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

export default function HomeHero({ user, churches, children }) {
  const insets = useSafeAreaInsets();
  const me = useFetch('/me');
  const mine = me.data?.data ?? user;
  const followed = churches ?? mine?.churches ?? [];
  const church = followed[0] ? `${followed[0].name}${followed.length > 1 ? ` +${followed.length - 1}` : ''}` : null; // the church's name under the app's, so the app feels like theirs

  useFocusEffect(
    useCallback(() => {
      me.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  return (
    <View style={{ marginBottom: SPACING.md + 4 }}>
      <LinearGradient colors={SOFT.panel} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[styles.panel, { paddingTop: insets.top + SPACING.sm }]}>
        <View style={styles.glowA} />
        <View style={styles.glowB} />

        <View style={styles.top}>
          <View style={styles.brand}>
            <Logo size={42} />
            {/* The church's name is the big one at the top, so the app feels like the church's own; FaithFellows is under it. Without a church it is the title. */}
            <View style={{ flexShrink: 1 }}>
              <AppText style={[styles.name, church && styles.churchName]} numberOfLines={2} accessibilityRole="header">
                {church ?? 'FaithFellows'}
              </AppText>
              {church ? (
                <AppText style={styles.church} numberOfLines={1}>
                  FaithFellows
                </AppText>
              ) : null}
            </View>
          </View>
          <View style={styles.buttons}>
            <RoundButton icon="notifications-outline" label="Notifications" count={mine?.unread_notifications ?? 0} onPress={() => router.push('/notifications')} />
            <RoundButton icon="chatbubble-ellipses-outline" label="Messages" count={mine?.unread_messages ?? 0} onPress={() => router.push('/chats')} />
          </View>
        </View>

        <Hello name={firstName(mine?.name)} />

        <View style={{ marginTop: 14 }}>{children}</View>
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  panel: { overflow: 'hidden', paddingBottom: 16, borderBottomLeftRadius: 34, borderBottomRightRadius: 34 },
  glowA: { position: 'absolute', right: -70, top: -60, width: 240, height: 240, borderRadius: 120, backgroundColor: 'rgba(255,255,255,0.07)' },
  glowB: { position: 'absolute', left: -90, bottom: 30, width: 220, height: 220, borderRadius: 110, backgroundColor: 'rgba(242,126,168,0.10)' },
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md },
  brand: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 10, paddingRight: 10 },
  name: { flexShrink: 1, fontFamily: FONTS.bold, fontSize: 21, letterSpacing: 0.2, color: '#fff' },
  churchName: { fontSize: 19, lineHeight: 23 },
  church: { fontFamily: FONTS.semibold, fontSize: 12, marginTop: -1, letterSpacing: 0.4, color: 'rgba(255,255,255,0.72)' },
  buttons: { flexDirection: 'row', gap: 10 },
  round: { width: 42, height: 42, borderRadius: 21, backgroundColor: SOFT.glassOnDark, borderWidth: 1, borderColor: SOFT.glassOnDarkBorder, alignItems: 'center', justifyContent: 'center' },
  badge: { position: 'absolute', top: -4, right: -4, minWidth: 18, height: 18, borderRadius: 9, paddingHorizontal: 4, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: '#4A0F29' },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10, color: '#fff' },
  hello: { alignItems: 'center', justifyContent: 'center', paddingHorizontal: SPACING.lg, overflow: 'hidden' },
  greeting: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 30, color: '#fff' },
});
