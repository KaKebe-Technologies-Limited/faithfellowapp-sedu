// Shown at the top of the home screen when the member does not follow a church yet (or none has a
// banner photo): a friendly magenta card that points to the directory.
import { Pressable, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import AppText from '../AppText';
import Logo from '../Logo';

export default function WelcomeBanner() {
  return (
    <View style={styles.wrap}>
      <LinearGradient colors={[COLORS.primaryLight, COLORS.primary, COLORS.primaryDark]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={styles.glow} />
        <Logo size={58} />
        <AppText style={styles.title}>Welcome to FaithFellows</AppText>
        <AppText style={styles.text}>Follow up to three churches and their sermons, events, prayers and updates all arrive right here.</AppText>
        <Pressable onPress={() => router.push('/churches')} accessibilityRole="button" style={styles.button}>
          <AppText style={styles.buttonText}>Find churches</AppText>
        </Pressable>
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.md },
  card: { borderRadius: RADIUS.xl, padding: SPACING.lg, overflow: 'hidden', ...SHADOW, shadowOpacity: 0.16 },
  glow: { position: 'absolute', right: -50, top: -50, width: 180, height: 180, borderRadius: 90, backgroundColor: 'rgba(255,255,255,0.12)' },
  title: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 28, color: COLORS.onColor, marginTop: SPACING.md },
  text: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 21, color: 'rgba(255,255,255,0.88)', marginTop: 6 },
  button: { alignSelf: 'flex-start', backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 20, paddingVertical: 11, marginTop: SPACING.md },
  buttonText: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
});
