// Top of the home screen: today's date, "Good evening, Grace", a notification bell with its unread count, and the
// member's photo (opens Me). The counts come from GET /api/v1/me and refresh each time the screen is shown.
import { useCallback } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SHADOW, SPACING } from '../../constants/theme';
import { firstName, greeting, longDate } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';
import AppText from '../AppText';
import Avatar from '../Avatar';

export default function HomeHeader({ user }) {
  const insets = useSafeAreaInsets();
  const me = useFetch('/me');
  const name = firstName(user?.name);
  const unread = me.data?.data?.unread_notifications ?? 0;
  const mine = me.data?.data ?? user;

  useFocusEffect(
    useCallback(() => {
      me.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  return (
    <View style={[styles.wrap, { paddingTop: insets.top + SPACING.sm }]}>
      <View style={styles.text}>
        <AppText variant="caption" style={styles.date}>
          {longDate()}
        </AppText>
        <AppText variant="title" style={styles.greeting} numberOfLines={1} adjustsFontSizeToFit>
          {name ? `${greeting()}, ${name}` : greeting()}
        </AppText>
      </View>
      <Pressable onPress={() => router.push('/notifications')} style={styles.bell} accessibilityRole="button" accessibilityLabel={unread ? `Notifications, ${unread} new` : 'Notifications'}>
        <Ionicons name="notifications-outline" size={22} color={COLORS.black} />
        {unread ? (
          <View style={styles.badge}>
            <AppText style={styles.badgeText}>{unread > 99 ? '99+' : unread}</AppText>
          </View>
        ) : null}
      </Pressable>
      <Pressable onPress={() => router.push('/(tabs)/profile')} accessibilityRole="button" accessibilityLabel="Open your profile">
        <Avatar person={mine} size={42} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: SPACING.md, paddingBottom: SPACING.md, backgroundColor: COLORS.canvas },
  text: { flex: 1 },
  date: { fontFamily: FONTS.semibold, letterSpacing: 0.8, color: COLORS.gray700, marginBottom: 2 },
  greeting: { fontSize: 24, lineHeight: 30 },
  bell: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center', ...SHADOW, shadowOpacity: 0.05 },
  badge: { position: 'absolute', top: -3, right: -3, minWidth: 18, height: 18, borderRadius: 9, paddingHorizontal: 4, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: COLORS.canvas },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10, color: COLORS.white },
});
