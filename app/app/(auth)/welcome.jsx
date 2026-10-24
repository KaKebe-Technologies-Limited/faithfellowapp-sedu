// The first screen and the way in. A full-bleed photo (people praising, prayer over the Bible, friends in the morning light, the
// cross, and the smiling faces of the church family) cross-fades behind the FaithFellows promise, and the ways in are stacked as plain pills: Continue with Google first,
// then Continue with email (which opens sign in; "Create an account" is one tap away). Terms and Privacy sit at the foot.
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import GoogleButton from '../../components/GoogleButton';
import PendingJoinBanner from '../../components/join/PendingJoinBanner';
import Logo from '../../components/Logo';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { sourceOf, useAppMedia } from '../../lib/appMedia';
import { GOOGLE_ENABLED } from '../../lib/google';

// The photos that cross-fade behind the words: the church scenes, with people of the church (the owner's own photos, cut to the phone's screen) between them.
const PICTURES = [
  require('../../assets/welcome/praise.jpg'),
  require('../../assets/welcome/u1.jpg'),
  require('../../assets/welcome/prayer.jpg'),
  require('../../assets/welcome/u2.jpg'),
  require('../../assets/welcome/fellowship.jpg'),
  require('../../assets/welcome/u3.jpg'),
  require('../../assets/welcome/cross.jpg'),
  require('../../assets/welcome/u4.jpg'),
  require('../../assets/welcome/u5.jpg'),
  require('../../assets/welcome/u6.jpg'),
];

export default function Welcome() {
  const insets = useSafeAreaInsets();
  const [shown, setShown] = useState(0);
  // The photos the platform admin chose (Super admin → App media), else the ones built into the app.
  const { welcome } = useAppMedia();
  const pictures = welcome.length >= 2 ? welcome.map(sourceOf) : PICTURES;

  useEffect(() => {
    const t = setInterval(() => setShown((n) => (n + 1) % pictures.length), 5500);
    return () => clearInterval(t);
  }, [pictures.length]);

  return (
    <View style={styles.screen}>
      <Image source={pictures[shown % pictures.length]} style={StyleSheet.absoluteFill} contentFit="cover" cachePolicy="disk" transition={1000} />
      <LinearGradient colors={['rgba(20,6,12,0.6)', 'rgba(20,6,12,0.12)', 'rgba(20,6,12,0.6)', 'rgba(20,6,12,0.96)']} locations={[0, 0.25, 0.55, 0.92]} style={StyleSheet.absoluteFill} />

      <View style={[styles.top, { paddingTop: insets.top + 16 }]}>
        <Logo size={46} />
        <AppText style={styles.brand}>FaithFellows</AppText>
      </View>

      <View style={[styles.bottom, { paddingBottom: insets.bottom + 22 }]}>
        <AppText style={styles.headline}>Welcome to your church family</AppText>
        <AppText style={styles.sub}>Worship, the Word, prayer and giving, and people who share your faith.</AppText>

        <View style={styles.dots}>
          {pictures.map((_, i) => (
            <View key={i} style={[styles.dot, i === shown && styles.dotOn]} />
          ))}
        </View>

        <PendingJoinBanner dark />

        {GOOGLE_ENABLED ? <GoogleButton /> : null}

        <Pressable onPress={() => router.push('/(auth)/login')} style={({ pressed }) => [styles.email, pressed && { opacity: 0.85 }]} accessibilityRole="button" accessibilityLabel="Continue with email">
          <Ionicons name="mail-outline" size={20} color={COLORS.onColor} />
          <AppText style={styles.emailText}>Continue with email</AppText>
        </Pressable>

        <View style={styles.newRow}>
          <AppText style={styles.newText}>New to FaithFellows?</AppText>
          <Pressable onPress={() => router.push('/(auth)/register')} accessibilityRole="button" hitSlop={8}>
            <AppText style={styles.newLink}>Create an account</AppText>
          </Pressable>
        </View>

        <Pressable onPress={() => router.push('/join')} accessibilityRole="button" hitSlop={8} style={styles.codeRow}>
          <AppText style={styles.codeText}>Have a church code?</AppText>
        </Pressable>

        <AppText style={styles.legal}>
          By continuing you agree to our{' '}
          <AppText style={styles.link} onPress={() => router.push('/legal/terms')}>
            Terms of Service
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
  top: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: SPACING.lg },
  brand: { fontFamily: FONTS.bold, fontSize: 21, color: COLORS.onColor },
  bottom: { flex: 1, justifyContent: 'flex-end', paddingHorizontal: SPACING.lg, gap: 12 },
  headline: { fontFamily: FONTS.display, fontSize: 34, lineHeight: 40, color: COLORS.onColor },
  sub: { fontFamily: FONTS.regular, fontSize: 16, lineHeight: 23, color: 'rgba(255,255,255,0.88)', marginBottom: 2 },
  dots: { flexDirection: 'row', gap: 6, marginBottom: 8 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: 'rgba(255,255,255,0.4)' },
  dotOn: { width: 22, backgroundColor: COLORS.white },
  email: { minHeight: 54, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, borderRadius: RADIUS.full, backgroundColor: 'rgba(255,255,255,0.14)', borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.55)' },
  emailText: { fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.onColor },
  newRow: { flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 6, marginTop: 4 },
  newText: { fontFamily: FONTS.regular, fontSize: 14, color: 'rgba(255,255,255,0.8)' },
  newLink: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.onColor, textDecorationLine: 'underline' },
  codeRow: { alignSelf: 'center', marginTop: 2 },
  codeText: { fontFamily: FONTS.semibold, fontSize: 14, color: 'rgba(255,255,255,0.9)', textDecorationLine: 'underline' },
  legal: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 18, color: 'rgba(255,255,255,0.7)', textAlign: 'center', marginTop: 6 },
  link: { fontFamily: FONTS.semibold, color: COLORS.onColor, textDecorationLine: 'underline' },
});
