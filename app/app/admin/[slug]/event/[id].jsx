// One event, for the people running it: who is coming, how many have arrived, and door check-in — scan a guest's
// QR ticket with the camera, type the ticket code, or tap "Check in" next to their name.
// Data: /api/v1/admin/{church}/events/{id} and …/check-in.
import { useRef, useState } from 'react';
import { Alert, Modal, Platform, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../../../components/AppText';
import Avatar from '../../../../components/Avatar';
import Button from '../../../../components/Button';
import EventForm from '../../../../components/EventForm';
import ScreenHeader from '../../../../components/ScreenHeader';
import StateView from '../../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../../../constants/theme';
import { adminApi } from '../../../../lib/admin';
import { errorMessage } from '../../../../lib/api';
import { dayAndTime, formatMoney } from '../../../../lib/format';
import { useFetch } from '../../../../lib/useFetch';

export default function AdminEvent() {
  const { slug, id } = useLocalSearchParams();
  const admin = adminApi(slug);
  const insets = useSafeAreaInsets();
  const { data, loading, error, retry, reload } = useFetch(admin.path(`/events/${id}`));
  const e = data?.data;
  const [code, setCode] = useState('');
  const [result, setResult] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [editing, setEditing] = useState(false);
  const [permission, requestPermission] = useCameraPermissions();
  const lastScan = useRef({ value: '', at: 0 });

  async function checkIn(value) {
    try {
      const { data: res } = await admin.post(`/events/${id}/check-in`, { code: value });
      setResult({ ok: true, message: res.message });
      await reload();
    } catch (err) {
      setResult({ ok: false, message: err.response?.data?.message ?? errorMessage(err) });
    }
  }

  function onScanned({ data: value }) {
    const now = Date.now();
    if (value === lastScan.current.value && now - lastScan.current.at < 3000) return;
    lastScan.current = { value, at: now };
    checkIn(value);
  }

  async function openScanner() {
    if (Platform.OS === 'web') {
      Alert.alert('Scanning needs a phone', 'Type the ticket code instead, or open the app on your phone to scan QR tickets.');
      return;
    }
    if (!permission?.granted) {
      const r = await requestPermission();
      if (!r.granted) return;
    }
    setResult(null);
    setScanning(true);
  }

  function remove() {
    const run = async () => {
      await admin.delete(`/events/${id}`).catch(() => {});
      router.back();
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this event?', e.title, [{ text: 'Keep', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: run }]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title={e?.title ?? 'Event'} subtitle={e ? dayAndTime(e.starts_at) : undefined} right={e ? (
        <View style={{ flexDirection: 'row', gap: 16, alignItems: 'center' }}>
          <Pressable onPress={() => setEditing(true)} hitSlop={8} accessibilityLabel="Edit event"><Ionicons name="create-outline" size={23} color={COLORS.primary} /></Pressable>
          <Pressable onPress={remove} hitSlop={8} accessibilityLabel="Delete event"><Ionicons name="trash-outline" size={22} color={COLORS.gray700} /></Pressable>
        </View>
      ) : null} />
      {e ? <EventForm visible={editing} onClose={() => setEditing(false)} slug={slug} event={e} onSaved={reload} /> : null}
      <StateView loading={loading && !e} error={!e ? error : null} onRetry={retry}>
        {e ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <View style={styles.stats}>
              {[[e.stats.going, 'going'], [e.stats.checked_in, 'arrived'], [e.stats.pending, 'unpaid']].map(([v, l]) => (
                <View key={l} style={styles.stat}>
                  <AppText style={styles.statValue} numberOfLines={1} adjustsFontSizeToFit>{v}</AppText>
                  <AppText variant="caption">{l}</AppText>
                </View>
              ))}
            </View>
            {e.is_paid ? <AppText style={styles.paidIn}>Paid in so far: {formatMoney(e.stats.revenue)}</AppText> : null}

            <View style={styles.door}>
              <AppText style={styles.h}>Door check-in</AppText>
              <Button title="Scan QR tickets" onPress={openScanner} />
              <View style={styles.codeRow}>
                <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={code} onChangeText={(t) => setCode(t.toUpperCase())} placeholder="Or type the code" placeholderTextColor={COLORS.gray500} autoCapitalize="characters" style={styles.codeInput} onSubmitEditing={() => { checkIn(code); setCode(''); }} />
                <Button title="Check in" small onPress={() => { checkIn(code); setCode(''); }} disabled={!code.trim()} />
              </View>
              {result ? (
                <View style={[styles.result, { backgroundColor: result.ok ? '#EAF7EF' : '#FDECEC' }]}>
                  <Ionicons name={result.ok ? 'checkmark-circle' : 'close-circle'} size={22} color={result.ok ? COLORS.success : COLORS.danger} />
                  <AppText style={[styles.resultText, { color: result.ok ? COLORS.success : COLORS.danger }]}>{result.message}</AppText>
                </View>
              ) : null}
            </View>

            <AppText style={[styles.h, { marginTop: SPACING.lg }]}>Guest list ({e.attendees.length})</AppText>
            {e.attendees.length === 0 ? <AppText variant="caption">Nobody has reserved a seat yet.</AppText> : null}
            {e.attendees.map((a) => (
              <View key={a.id} style={styles.guest}>
                <Avatar person={a} size={40} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.name}>{a.name}</AppText>
                  <AppText variant="caption">{a.quantity} {a.quantity === 1 ? 'guest' : 'guests'}{a.status === 'pending_payment' ? ' · not paid yet' : ''}{a.code ? ` · ${a.code}` : ''}</AppText>
                </View>
                {a.checked_in ? (
                  <View style={styles.here}>
                    <Ionicons name="checkmark" size={14} color={COLORS.success} />
                    <AppText style={styles.hereText}>Here</AppText>
                  </View>
                ) : a.status === 'going' ? (
                  <Pressable onPress={() => checkIn(a.code)} style={styles.checkBtn} accessibilityRole="button" accessibilityLabel={`Check in ${a.name}`}>
                    <AppText style={styles.checkText}>Check in</AppText>
                  </Pressable>
                ) : null}
              </View>
            ))}
          </ScrollView>
        ) : null}
      </StateView>

      <Modal visible={scanning} animationType="slide" onRequestClose={() => setScanning(false)}>
        <View style={styles.camWrap}>
          <CameraView style={StyleSheet.absoluteFill} facing="back" barcodeScannerSettings={{ barcodeTypes: ['qr'] }} onBarcodeScanned={onScanned} />
          <View style={[styles.camTop, { paddingTop: insets.top + 10 }]}>
            <Pressable onPress={() => setScanning(false)} style={styles.camClose} accessibilityRole="button" accessibilityLabel="Close scanner">
              <Ionicons name="close" size={24} color={COLORS.white} />
            </Pressable>
            <AppText style={styles.camTitle}>Point at a guest's QR ticket</AppText>
          </View>
          <View style={styles.frame} />
          <View style={[styles.camBottom, { paddingBottom: insets.bottom + 20 }]}>
            {result ? (
              <View style={[styles.camResult, { backgroundColor: result.ok ? COLORS.success : COLORS.danger }]}>
                <AppText style={styles.camResultText}>{result.message}</AppText>
              </View>
            ) : (
              <AppText style={styles.camHint}>{e?.stats.checked_in ?? 0} of {e?.stats.going ?? 0} have arrived</AppText>
            )}
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  stats: { flexDirection: 'row', gap: 8 },
  paidIn: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primaryDark, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: 12, marginTop: 8, textAlign: 'center' },
  stat: { flex: 1, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingVertical: 12, alignItems: 'center', gap: 2, ...SHADOW, shadowOpacity: 0.05 },
  statValue: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black },
  door: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.md, gap: 12, marginTop: SPACING.md, ...SHADOW, shadowOpacity: 0.05 },
  h: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  codeRow: { flexDirection: 'row', gap: 10, alignItems: 'center' },
  codeInput: { flex: 1, minWidth: 0, height: 46, borderRadius: RADIUS.md, borderWidth: 1.5, borderColor: COLORS.gray300, paddingHorizontal: 14, fontFamily: FONTS.bold, fontSize: 15, letterSpacing: 1, color: COLORS.black },
  result: { flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: RADIUS.md, padding: 12 },
  resultText: { flex: 1, fontFamily: FONTS.semibold, fontSize: 14.5 },
  guest: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, marginTop: 8 },
  name: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  here: { flexDirection: 'row', alignItems: 'center', gap: 3, backgroundColor: '#EAF7EF', borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 5 },
  hereText: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.success },
  checkBtn: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  checkText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.white },
  camWrap: { flex: 1, backgroundColor: '#000' },
  camTop: { position: 'absolute', top: 0, left: 0, right: 0, flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: SPACING.md, paddingBottom: 12, backgroundColor: 'rgba(0,0,0,0.45)' },
  camClose: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.2)', alignItems: 'center', justifyContent: 'center' },
  camTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.white },
  frame: { position: 'absolute', alignSelf: 'center', top: '30%', width: 240, height: 240, borderRadius: 24, borderWidth: 3, borderColor: COLORS.white },
  camBottom: { position: 'absolute', bottom: 0, left: 0, right: 0, alignItems: 'center', paddingHorizontal: SPACING.md },
  camHint: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.white, backgroundColor: 'rgba(0,0,0,0.55)', paddingHorizontal: 18, paddingVertical: 10, borderRadius: RADIUS.full },
  camResult: { borderRadius: RADIUS.lg, paddingHorizontal: 18, paddingVertical: 14, alignSelf: 'stretch' },
  camResultText: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.white, textAlign: 'center' },
});
