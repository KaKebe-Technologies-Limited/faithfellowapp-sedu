// Right above Explore on Home: the name of the member's church, so the app feels like their own. A soft white card with the church's logo,
// "Your church", its name and town, and a small "Live now" mark while it is on air; a tap opens the church's page. A member who follows more than
// one church sees the first, with "+2 more" that opens the list of churches. Nothing shows for a member who follows no church.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';

export default function ChurchStrip({ churches = [] }) {
  const church = churches[0];
  if (!church) return null;
  const more = churches.length - 1;

  return (
    <View style={styles.wrap}>
      <Pressable onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: church.slug } })} style={({ pressed }) => [styles.card, pressed && { opacity: 0.88, transform: [{ scale: 0.99 }] }]} accessibilityRole="button" accessibilityLabel={`Your church, ${church.name}. Open its page`}>
        <ChurchAvatar church={church} size={48} />
        <View style={styles.text}>
          <AppText style={styles.kicker}>YOUR CHURCH</AppText>
          <AppText style={styles.name} numberOfLines={2}>
            {church.name}
          </AppText>
          {church.city ? (
            <AppText style={styles.city} numberOfLines={1}>
              {church.city}
            </AppText>
          ) : null}
        </View>
        {church.is_live ? (
          <View style={styles.live}>
            <View style={styles.dot} />
            <AppText style={styles.liveText}>Live now</AppText>
          </View>
        ) : (
          <Ionicons name="chevron-forward" size={20} color={SOFT.muted} />
        )}
      </Pressable>
      {more > 0 ? (
        <Pressable onPress={() => router.push('/churches')} hitSlop={8} style={styles.more} accessibilityRole="button" accessibilityLabel={`${more} more churches you follow`}>
          <AppText style={styles.moreText}>+{more} more {more === 1 ? 'church' : 'churches'}</AppText>
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: SOFT.glass, borderWidth: 1.5, borderColor: SOFT.glassBorder, borderRadius: 24, paddingVertical: 12, paddingHorizontal: 14, ...SOFT_SHADOW },
  text: { flex: 1, minWidth: 0 },
  kicker: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 1.4, color: COLORS.primary },
  name: { fontFamily: FONTS.bold, fontSize: 17.5, lineHeight: 23, color: SOFT.ink },
  city: { fontFamily: FONTS.regular, fontSize: 13, color: SOFT.muted },
  live: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: 'rgba(188,38,73,0.1)', borderRadius: 14, paddingHorizontal: 10, paddingVertical: 6 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: COLORS.primary },
  liveText: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.primary },
  more: { alignSelf: 'flex-end', marginTop: 8, marginRight: 6 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.primary },
});
