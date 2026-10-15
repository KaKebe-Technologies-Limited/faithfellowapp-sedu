// "Inspiration": one short, strong message for this part of the day (morning, mid-morning or evening), on a soft background that
// changes with the hour. The message comes from GET /api/v1/home (`inspiration`) and the super admin looks after the library.
import { Pressable, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { shareMessage } from '../../lib/activity';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import AppText from '../AppText';

const LOOK = {
  morning: { colors: ['#FFF3DD', '#FFE0C7'], ink: '#7A4313', accent: '#D9822B', icon: 'sunny-outline', title: 'Morning inspiration' },
  midmorning: { colors: ['#FFE9F1', '#FBD3E2'], ink: '#7A1832', accent: COLORS.primary, icon: 'flame-outline', title: 'Midday strength' },
  evening: { colors: ['#EFE6FB', '#DCCEF5'], ink: '#3F2570', accent: '#6B46C1', icon: 'moon-outline', title: 'Evening peace' },
};

export default function InspirationCard({ inspiration }) {
  if (!inspiration?.body) return null;
  const look = LOOK[inspiration.slot] ?? LOOK.morning;

  const share = () => shareMessage(`“${inspiration.body}”${inspiration.reference ? ` (${inspiration.reference})` : ''}\n\nFaithFellows`, 'share_inspiration');

  return (
    <View style={styles.wrap}>
      <LinearGradient colors={look.colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={[styles.glow, { backgroundColor: look.accent }]} />
        <View style={styles.top}>
          <View style={[styles.badge, { backgroundColor: look.accent }]}>
            <Ionicons name={look.icon} size={16} color="#fff" />
          </View>
          <AppText style={[styles.kicker, { color: look.accent }]}>{look.title.toUpperCase()}</AppText>
          <View style={{ flex: 1 }} />
          <Pressable onPress={share} hitSlop={10} accessibilityRole="button" accessibilityLabel="Share this message">
            <Ionicons name="share-social-outline" size={20} color={look.ink} />
          </Pressable>
        </View>
        <AppText style={[styles.body, { color: look.ink }]}>{inspiration.body}</AppText>
        {inspiration.reference ? <AppText style={[styles.reference, { color: look.accent }]}>{inspiration.reference}</AppText> : null}
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg, borderRadius: 26, ...SOFT_SHADOW, shadowColor: SOFT.shadow },
  card: { borderRadius: 26, padding: 20, overflow: 'hidden', borderWidth: 1.5, borderColor: '#fff' },
  glow: { position: 'absolute', right: -40, top: -50, width: 150, height: 150, borderRadius: 75, opacity: 0.12 },
  top: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 },
  badge: { width: 28, height: 28, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
  kicker: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.5 },
  body: { fontFamily: FONTS.semibold, fontSize: 20, lineHeight: 29 },
  reference: { fontFamily: FONTS.bold, fontSize: 13.5, marginTop: 10 },
});
