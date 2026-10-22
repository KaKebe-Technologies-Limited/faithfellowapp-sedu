// The church a person is about to join, shown on the welcome, sign-in and create-account screens: "You're joining Grace Community Church". A church
// the app only guessed from a link opened a moment ago asks first ("Are you joining ...?"); a church from a link or a typed code can be removed.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, RADIUS } from '../../constants/theme';
import { confirmJoin, forgetJoin, usePendingJoin } from '../../lib/join';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';

export default function PendingJoinBanner({ dark = false, style }) {
  const { pending } = usePendingJoin();
  if (!pending) return null;
  const { church, confirmed } = pending;

  return (
    <View style={[styles.box, dark ? styles.dark : styles.light, style]} accessibilityRole="summary">
      <View style={styles.row}>
        <ChurchAvatar church={church} size={42} />
        <View style={{ flex: 1 }}>
          <AppText style={[styles.title, dark && { color: '#fff' }]} numberOfLines={2}>
            {confirmed ? `You're joining ${church.name}` : `Are you joining ${church.name}?`}
          </AppText>
          <AppText style={[styles.sub, dark && { color: 'rgba(255,255,255,0.78)' }]} numberOfLines={2}>
            {confirmed ? 'You will follow it as soon as your account is ready.' : 'It looks like you came from their link.'}
          </AppText>
        </View>
        {confirmed ? (
          <Pressable onPress={forgetJoin} hitSlop={10} accessibilityRole="button" accessibilityLabel={`Do not join ${church.name}`}>
            <Ionicons name="close-circle" size={24} color={dark ? 'rgba(255,255,255,0.7)' : COLORS.gray500} />
          </Pressable>
        ) : null}
      </View>
      {!confirmed ? (
        <View style={styles.answers}>
          <Pressable onPress={confirmJoin} style={[styles.answer, styles.yes]} accessibilityRole="button" accessibilityLabel={`Yes, join ${church.name}`}>
            <AppText style={styles.yesText}>Yes, join</AppText>
          </Pressable>
          <Pressable onPress={forgetJoin} style={[styles.answer, dark ? styles.noDark : styles.no]} accessibilityRole="button" accessibilityLabel="No, not this church">
            <AppText style={[styles.noText, dark && { color: '#fff' }]}>No</AppText>
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: { borderRadius: RADIUS.lg, padding: 12 },
  light: { backgroundColor: COLORS.primaryMist },
  dark: { backgroundColor: 'rgba(255,255,255,0.14)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.35)' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  title: { fontFamily: FONTS.bold, fontSize: 15, lineHeight: 20, color: COLORS.black },
  sub: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 17, color: COLORS.gray700, marginTop: 1 },
  answers: { flexDirection: 'row', gap: 10, marginTop: 10 },
  answer: { flex: 1, minHeight: 40, borderRadius: RADIUS.full, alignItems: 'center', justifyContent: 'center' },
  yes: { backgroundColor: COLORS.primary },
  yesText: { fontFamily: FONTS.bold, fontSize: 14, color: '#fff' },
  no: { backgroundColor: '#fff', borderWidth: 1, borderColor: COLORS.gray300 },
  noDark: { borderWidth: 1, borderColor: 'rgba(255,255,255,0.5)' },
  noText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.gray700 },
});
