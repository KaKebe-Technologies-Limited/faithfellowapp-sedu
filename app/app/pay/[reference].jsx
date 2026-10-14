// Waiting screen after a payment starts. Mobile money: "approve the prompt on your phone", checked every 3
// seconds. Card: the secure checkout opens in the in-app browser first, then we confirm here. Ends in a
// thank-you with the receipt, or a clear reason and a way to try again — never a dead end.
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Animated, Easing, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, mediaUrl } from '../../lib/api';
import { formatMoney } from '../../lib/format';

const POLL_MS = 3000;
const GIVE_UP_AFTER_MS = 3 * 60 * 1000;

function Pulse({ children }) {
  const scale = useRef(new Animated.Value(1)).current;
  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(scale, { toValue: 1.12, duration: 900, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
        Animated.timing(scale, { toValue: 1, duration: 900, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [scale]);
  return <Animated.View style={[styles.ring, { transform: [{ scale }] }]}>{children}</Animated.View>;
}

export default function PayScreen() {
  const { reference, redirect, church: churchParam, logo, amount: amountParam, currency: currencyParam, gateway } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [donation, setDonation] = useState(null);
  const [error, setError] = useState(null);
  const [timedOut, setTimedOut] = useState(false);
  const started = useRef(Date.now());
  const opened = useRef(false);

  const check = useCallback(async () => {
    try {
      const { data } = await api.get(`/giving/${reference}`);
      setDonation(data.data);
      setError(null);
      return data.data.status;
    } catch (e) {
      setError(errorMessage(e));
      return null;
    }
  }, [reference]);

  // Card payments: open the secure checkout page once, in the in-app browser sheet.
  useEffect(() => {
    if (!redirect || opened.current) return;
    opened.current = true;
    WebBrowser.openBrowserAsync(mediaUrl(String(redirect))).finally(() => check());
  }, [redirect, check]);

  useEffect(() => {
    let alive = true;
    let timer;
    async function tick() {
      const status = await check();
      if (!alive) return;
      if (['succeeded', 'failed', 'refunded'].includes(status)) return;
      if (Date.now() - started.current > GIVE_UP_AFTER_MS) {
        setTimedOut(true);
        return;
      }
      timer = setTimeout(tick, POLL_MS);
    }
    tick();
    return () => {
      alive = false;
      clearTimeout(timer);
    };
  }, [check]);

  const status = donation?.status;
  const church = donation?.church ?? (churchParam ? { name: String(churchParam), logo_url: logo ? String(logo) : null } : null);
  const amount = donation ? donation.amount : amountParam ? Number(amountParam) : null;
  const currency = donation?.currency ?? (currencyParam ? String(currencyParam) : 'UGX');
  const viaIotec = gateway === 'iotec';
  const done = status === 'succeeded';
  const failed = status === 'failed' || status === 'refunded';

  function retry() {
    started.current = Date.now();
    setTimedOut(false);
    setDonation(null);
    check();
  }

  function finish() {
    router.replace('/(tabs)');
  }

  return (
    <View style={[styles.screen, { paddingTop: insets.top + 24, paddingBottom: insets.bottom + 24 }]}>
      <View style={styles.center}>
        {done ? (
          <View style={[styles.ring, styles.okRing]}>
            <Ionicons name="checkmark" size={54} color={COLORS.white} />
          </View>
        ) : failed ? (
          <View style={[styles.ring, styles.failRing]}>
            <Ionicons name="close" size={54} color={COLORS.white} />
          </View>
        ) : (
          <Pulse>
            <Ionicons name="phone-portrait-outline" size={44} color={COLORS.primary} />
          </Pulse>
        )}

        <AppText style={styles.title}>{done ? 'Thank you!' : failed ? 'Payment did not go through' : redirect ? 'Finishing your payment…' : 'Approve on your phone'}</AppText>

        {church ? (
          <View style={styles.summary}>
            <ChurchAvatar church={church} size={40} />
            <View style={{ flex: 1 }}>
              <AppText style={styles.amount}>{amount != null ? formatMoney(amount, currency) : church.name}</AppText>
              <AppText variant="caption" numberOfLines={1}>
                {amount != null ? ['to', church.name, donation?.campaign?.title].filter(Boolean).join(' ') : donation?.campaign?.title}
              </AppText>
            </View>
          </View>
        ) : null}
        {viaIotec && !done && !failed ? (
          <View style={styles.secure}>
            <Ionicons name="shield-checkmark" size={15} color={COLORS.success} />
            <AppText style={styles.secureText}>Secured by ioTec Pay</AppText>
          </View>
        ) : null}

        {done ? (
          <AppText style={styles.message}>Your gift was received. May God bless you for your generosity.</AppText>
        ) : failed ? (
          <AppText style={styles.message}>{donation?.failure_reason || 'The payment was cancelled or declined. Nothing was charged.'}</AppText>
        ) : timedOut ? (
          <AppText style={styles.message}>We have not heard back yet. If you approved the prompt, it can take a minute. You can check again.</AppText>
        ) : (
          <>
            <AppText style={styles.message}>{redirect ? 'Complete the payment on the secure page, then come back here.' : `${viaIotec ? 'ioTec Pay sent' : 'A prompt was sent'} a prompt to your phone${church ? ` for your gift to ${church.name}` : ''}. Enter your PIN to confirm. This page updates by itself.`}</AppText>
            <ActivityIndicator color={COLORS.primary} style={{ marginTop: 18 }} />
          </>
        )}
        {error && !donation ? <AppText style={[styles.message, { color: COLORS.danger }]}>{error}</AppText> : null}
      </View>

      <View style={styles.actions}>
        {done ? (
          <>
            <Button title="View receipt" onPress={() => router.replace({ pathname: '/receipt/[reference]', params: { reference } })} />
            <Button title="Done" variant="outline" onPress={finish} />
          </>
        ) : failed ? (
          <>
            <Button title="Try again" onPress={() => router.back()} />
            <Button title="Back home" variant="outline" onPress={finish} />
          </>
        ) : timedOut ? (
          <>
            <Button title="Check again" onPress={retry} />
            <Button title="Back home" variant="outline" onPress={finish} />
          </>
        ) : (
          <Button title="Back home" variant="outline" onPress={finish} />
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas, paddingHorizontal: SPACING.lg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  ring: { width: 112, height: 112, borderRadius: 56, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  okRing: { backgroundColor: COLORS.success },
  failRing: { backgroundColor: COLORS.danger },
  title: { fontFamily: FONTS.bold, fontSize: 24, lineHeight: 30, color: COLORS.black, textAlign: 'center', marginTop: 24 },
  message: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray700, textAlign: 'center', marginTop: 10, maxWidth: 320 },
  summary: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginTop: 20, alignSelf: 'stretch', ...SHADOW },
  amount: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black },
  actions: { gap: 10 },
  secure: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 12 },
  secureText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.success },
});
