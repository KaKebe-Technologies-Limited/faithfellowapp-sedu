// Welcome screen: a full-bleed picture (worship, sunrise, the cross — it cross-fades every few seconds) behind
// the FaithFellows promise and the ways in: create an account, sign in, or Google when it is switched on.
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import GoogleButton from '../../components/GoogleButton';
import Logo from '../../components/Logo';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { GOOGLE_ENABLED } from '../../lib/google';

const PICTURES = [require('../../assets/welcome/worship.jpg'), require('../../assets/welcome/sunrise.jpg'), require('../../assets/welcome/cross.jpg')];

export default function Welcome() {
  const insets = useSafeAreaInsets();
  const [shown, setShown] = useState(0);

  useEffect(() => {
    const t = setInterval(() => setShown((n) => (n + 1) % PICTURES.length), 5000);
    return () => clearInterval(t);
  }, []);

  return (
    <View style={styles.screen}>
      <Image source={PICTURES[shown]} style={StyleSheet.absoluteFill} contentFit="cover" transition={900} />
      <LinearGradient colors={['rgba(20,6,12,0.55)', 'rgba(20,6,12,0.1)', 'rgba(20,6,12,0.55)', 'rgba(20,6,12,0.94)']} locations={[0, 0.28, 0.6, 1]} style={StyleSheet.absoluteFill} />

      <View style={[styles.top, { paddingTop: insets.top + 16 }]}>
        <Logo size={44} />
        <AppText style={styles.brand}>FaithFellows</AppText>
      </View>

      <View style={[styles.bottom, { paddingBottom: insets.bottom + 24 }]}>
        <AppText style={styles.headline}>Your church family, in your pocket</AppText>
        <AppText style={styles.sub}>Worship, the Word, prayer and giving — and people who share your faith.</AppText>

        <View style={styles.dots}>
          {PICTURES.map((_, i) => (
            <View key={i} style={[styles.dot, i === shown && styles.dotOn]} />
          ))}
        </View>

        {GOOGLE_ENABLED ? <GoogleButton /> : null}
        <Button title="Create your account" onPress={() => router.push('/(auth)/register')} />
        <Pressable onPress={() => router.push('/(auth)/login')} style={styles.signIn} accessibilityRole="button">
          <AppText style={styles.signInText}>I already have an account</AppText>
        </Pressable>

        <AppText style={styles.legal}>
          By continuing you agree to our{' '}
          <AppText style={styles.link} onPress={() => router.push('/legal/terms')}>
            Terms
          </AppText>{' '}
          and{' '}
          <AppText style={styles.link} onPress={() => router.push('/legal/privacy')}>
            Privacy Policy
          </AppText>
          .
        </AppText>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.primaryDark },
  top: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: SPACING.lg },
  brand: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.white },
  bottom: { flex: 1, justifyContent: 'flex-end', paddingHorizontal: SPACING.lg, gap: 12 },
  headline: { fontFamily: FONTS.bold, fontSize: 34, lineHeight: 40, color: COLORS.white },
  sub: { fontFamily: FONTS.regular, fontSize: 16, lineHeight: 23, color: 'rgba(255,255,255,0.86)', marginBottom: 4 },
  dots: { flexDirection: 'row', gap: 6, marginBottom: 6 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: 'rgba(255,255,255,0.4)' },
  dotOn: { width: 22, backgroundColor: COLORS.white },
  signIn: { minHeight: 50, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.7)', alignItems: 'center', justifyContent: 'center' },
  signInText: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.white },
  legal: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 18, color: 'rgba(255,255,255,0.7)', textAlign: 'center', marginTop: 4 },
  link: { fontFamily: FONTS.semibold, color: COLORS.white, textDecorationLine: 'underline' },
});
