// A church's join link opened in the app (faithfellows://join/GRACE7KQ, or the link from a message). It shows the church. Signed out: "Create account
// and join" keeps the church until the account is made, then it is followed for the person. Signed in: one tap follows it. Data: GET /join/{code},
// POST /join/{code}/follow.
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import Logo from '../../components/Logo';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { lookupChurch, rememberJoin } from '../../lib/join';

export default function JoinScreen() {
  const { code } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const { ready, token, user, setUser } = useAuth();
  const [info, setInfo] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    setInfo(null);
    try {
      setInfo(await lookupChurch(String(code ?? '')));
    } catch (e) {
      setError(e?.response?.status === 404 ? 'We could not find a church with this code. Check it and try again.' : errorMessage(e));
    }
  }, [code]);

  useEffect(() => {
    load();
  }, [load]);

  // Signed out: keep the church, so the account made next joins it (even if the app is closed in between).
  useEffect(() => {
    if (info && ready && !token) rememberJoin(info, true);
  }, [info, ready, token]);

  const church = info?.church;
  const following = !!church && !!user?.churches?.some((c) => c.id === church.id);
  const leave = () => (router.canGoBack() ? router.back() : router.replace('/'));

  async function follow() {
    setBusy(true);
    try {
      const { data } = await api.post(`/join/${info.code}/follow`);
      try {
        const me = await api.get('/me');
        setUser(me.data.data ?? me.data);
      } catch {
        // The profile refreshes by itself soon.
      }
      router.replace({ pathname: '/church/[slug]', params: { slug: church.slug } });
      Alert.alert(data.status === 'pending' ? 'Request sent' : `Welcome to ${church.name}`, data.message);
    } catch (e) {
      Alert.alert('We could not add you', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const meta = church ? [church.city, church.denomination, church.followers ? `${church.followers} ${church.followers === 1 ? 'follower' : 'followers'}` : null].filter(Boolean).join(' · ') : '';

  return (
    <LinearGradient colors={SOFT.panel} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ flex: 1 }}>
      <ScrollView contentContainerStyle={[styles.scroll, { paddingTop: insets.top + SPACING.lg, paddingBottom: insets.bottom + SPACING.lg }]} showsVerticalScrollIndicator={false}>
        <View style={{ alignItems: 'center', marginBottom: SPACING.lg }}>
          <Logo size={56} />
        </View>

        <View style={styles.card}>
          {!info && !error ? (
            <View style={{ paddingVertical: 40 }}>
              <ActivityIndicator size="large" color={COLORS.primary} />
            </View>
          ) : null}

          {error ? (
            <>
              <AppText style={styles.title}>This church link is not working</AppText>
              <AppText style={styles.text}>{error}</AppText>
              <Button title="Try another code" onPress={() => router.replace('/join')} style={styles.button} />
              <Button title="Try again" variant="outline" onPress={load} style={styles.button} />
              <Pressable onPress={leave} hitSlop={10} style={styles.later} accessibilityRole="button">
                <AppText style={styles.laterText}>Not now</AppText>
              </Pressable>
            </>
          ) : null}

          {church ? (
            <>
              <View style={{ alignItems: 'center' }}>
                <ChurchAvatar church={church} size={92} />
              </View>
              <AppText style={styles.kicker}>YOU ARE INVITED</AppText>
              <AppText style={styles.title}>{church.name}</AppText>
              {meta ? <AppText style={styles.meta}>{meta}</AppText> : null}
              {church.tagline ? <AppText style={styles.text}>{church.tagline}</AppText> : null}

              {!ready ? (
                <ActivityIndicator color={COLORS.primary} style={{ marginTop: SPACING.lg }} />
              ) : !token ? (
                <>
                  <Button title="Create account and join" onPress={() => router.push('/(auth)/register')} style={styles.button} />
                  <Button title="I already have an account" variant="outline" onPress={() => router.push('/(auth)/login')} style={styles.button} />
                  <AppText style={styles.note}>{info.requires_approval ? `${church.name} approves new members, so your request goes to them once you have an account.` : 'You will follow this church as soon as your account is ready.'}</AppText>
                </>
              ) : following ? (
                <>
                  <AppText style={[styles.text, { color: COLORS.success, fontFamily: FONTS.semibold }]}>You already follow this church.</AppText>
                  <Button title="Open the church" onPress={() => router.replace({ pathname: '/church/[slug]', params: { slug: church.slug } })} style={styles.button} />
                </>
              ) : (
                <>
                  <Button title="Follow this church" loading={busy} onPress={follow} style={styles.button} />
                  {info.requires_approval ? <AppText style={styles.note}>{church.name} approves new members, so your request will be sent to them.</AppText> : null}
                </>
              )}

              {ready ? (
                <Pressable onPress={leave} hitSlop={10} style={styles.later} accessibilityRole="button">
                  <AppText style={styles.laterText}>Not now</AppText>
                </Pressable>
              ) : null}
            </>
          ) : null}
        </View>
      </ScrollView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  scroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: SPACING.lg },
  card: { backgroundColor: '#fff', borderRadius: 28, padding: SPACING.lg, width: '100%', maxWidth: 460, alignSelf: 'center' },
  kicker: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.6, color: COLORS.primary, textAlign: 'center', marginTop: SPACING.md },
  title: { fontFamily: FONTS.bold, fontSize: 24, lineHeight: 30, color: COLORS.black, textAlign: 'center', marginTop: 6 },
  meta: { fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.gray700, textAlign: 'center', marginTop: 6 },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray700, textAlign: 'center', marginTop: 10 },
  button: { marginTop: SPACING.md },
  note: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray700, textAlign: 'center', marginTop: SPACING.md },
  later: { alignSelf: 'center', marginTop: SPACING.md, padding: 6 },
  laterText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.gray700 },
});
