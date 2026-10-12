// Top of the home screen: today's date, "Good evening, Grace" and the member's avatar (opens Profile).
import { Pressable, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { firstName, greeting, initials, longDate } from '../../lib/format';
import AppText from '../AppText';

export default function HomeHeader({ user }) {
  const insets = useSafeAreaInsets();
  const name = firstName(user?.name);
  const avatar = mediaUrl(user?.avatar_url);

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
      <Pressable onPress={() => router.push('/(tabs)/profile')} accessibilityRole="button" accessibilityLabel="Open your profile">
        {avatar ? (
          <Image source={{ uri: avatar }} style={styles.avatar} contentFit="cover" />
        ) : (
          <View style={[styles.avatar, styles.avatarFallback]}>
            <AppText style={{ fontFamily: FONTS.bold, fontSize: 15, color: COLORS.white }}>{initials(user?.name)}</AppText>
          </View>
        )}
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md, paddingHorizontal: SPACING.md, paddingBottom: SPACING.md, backgroundColor: COLORS.canvas },
  text: { flex: 1 },
  date: { fontFamily: FONTS.semibold, letterSpacing: 0.8, color: COLORS.gray700, marginBottom: 2 },
  greeting: { fontSize: 26, lineHeight: 32 },
  avatar: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.gray100 },
  avatarFallback: { backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
});
