// "Donate" to a fundraiser in one short sheet right on the campaign page: pick or type an amount, check the mobile money number
// (filled in from your profile), tap Donate. A prompt goes to that phone and you approve it with your PIN; the amount is deducted
// from your mobile money. For more choices (repeat, note, another way to pay) "More options" opens the full give screen.
import { useEffect, useState } from 'react';
import { Platform, Pressable, StyleSheet, Switch, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../constants/theme';
import api, { errorMessage, fieldErrors } from '../lib/api';
import { formatMoney } from '../lib/format';
import { openPayment } from '../lib/payments';
import { useFetch } from '../lib/useFetch';
import AppText from './AppText';
import Button from './Button';
import Sheet from './Sheet';
import StateView from './StateView';

export default function DonateSheet({ visible, onClose, church, campaign }) {
  const { data, loading, error, retry } = useFetch(`/churches/${church?.slug}/giving`, { enabled: visible && !!church?.slug });
  const options = data?.data;
  const [amount, setAmount] = useState('');
  const [phone, setPhone] = useState('');
  const [anonymous, setAnonymous] = useState(false);
  const [busy, setBusy] = useState(false);
  const [amountError, setAmountError] = useState(null);
  const [phoneError, setPhoneError] = useState(null);
  const [problem, setProblem] = useState(null);

  useEffect(() => {
    if (options) setPhone((p) => p || options.saved_phone || '');
  }, [options]);

  const numeric = Number(String(amount).replace(/[^\d]/g, '')) || 0;
  const method = options?.methods?.[0];
  const currency = options?.currency ?? campaign?.currency ?? 'UGX';

  async function donate() {
    const cleanPhone = phone.replace(/[\s-]/g, '');
    let ok = true;
    if (numeric < 500) {
      setAmountError('The smallest gift is UGX 500.');
      ok = false;
    } else setAmountError(null);
    if (method?.needs_phone && !new RegExp(method.phone_pattern || String.raw`^(\+?256|0)?7\d{8}$`).test(cleanPhone)) {
      setPhoneError('Enter your mobile money number, for example 0772 123 456.');
      ok = false;
    } else setPhoneError(null);
    if (!ok || !method) return;

    setBusy(true);
    setProblem(null);
    try {
      const { data: res } = await api.post(`/churches/${church.slug}/give`, {
        amount: numeric,
        campaign_id: campaign.id,
        method: method.type,
        phone: method.needs_phone ? cleanPhone : undefined,
        is_anonymous: anonymous,
      });
      onClose();
      openPayment(res);
    } catch (e) {
      const fields = fieldErrors(e);
      if (fields.amount) setAmountError(fields.amount);
      if (fields.phone) setPhoneError(fields.phone);
      setProblem(fields.method || fields.campaign_id || (!fields.amount && !fields.phone ? errorMessage(e) : null));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Sheet visible={visible} onClose={onClose} title={campaign ? `Donate to ${campaign.title}` : 'Donate'} tall>
      <StateView loading={loading} error={error} onRetry={retry}>
        {options ? (
          <View style={{ gap: 12 }}>
            <View style={styles.amountRow}>
              <AppText style={styles.cur}>{currency}</AppText>
              <TextInput
                maxFontSizeMultiplier={MAX_FONT_SCALE}
                value={numeric ? numeric.toLocaleString() : ''}
                onChangeText={(t) => setAmount(t.replace(/[^\d]/g, ''))}
                keyboardType="number-pad"
                placeholder="0"
                placeholderTextColor={COLORS.gray300}
                style={styles.amountInput}
                accessibilityLabel="Amount"
              />
            </View>
            {amountError ? <AppText style={styles.err}>{amountError}</AppText> : null}
            <View style={styles.quick}>
              {options.quick_amounts.map((q) => (
                <Pressable key={q} onPress={() => setAmount(String(q))} style={[styles.chip, numeric === q && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: numeric === q }}>
                  <AppText style={[styles.chipText, numeric === q && { color: COLORS.onColor }]}>{q.toLocaleString()}</AppText>
                </Pressable>
              ))}
            </View>

            {method?.needs_phone ? (
              <View>
                <View style={styles.phoneField}>
                  <Ionicons name="call-outline" size={20} color={COLORS.primary} />
                  <TextInput
                    maxFontSizeMultiplier={MAX_FONT_SCALE}
                    value={phone}
                    onChangeText={(t) => {
                      setPhone(t);
                      setPhoneError(null);
                    }}
                    keyboardType="phone-pad"
                    placeholder="Mobile money number"
                    placeholderTextColor={COLORS.gray500}
                    autoComplete="tel"
                    style={styles.phoneInput}
                    accessibilityLabel="Mobile money number"
                  />
                </View>
                {phoneError ? <AppText style={[styles.err, { textAlign: 'left', marginTop: 6 }]}>{phoneError}</AppText> : null}
                <View style={styles.prompt}>
                  <Ionicons name="phone-portrait-outline" size={18} color={COLORS.primaryInk} />
                  <AppText style={styles.promptText}>A prompt will be sent to this phone. Enter your mobile money PIN to approve it, and the amount is deducted from your mobile money.</AppText>
                </View>
              </View>
            ) : null}

            {!options.methods.length ? <AppText style={styles.err}>This church has not switched on online payments yet.</AppText> : null}

            <View style={styles.switchRow}>
              <AppText style={styles.switchTitle}>Give anonymously</AppText>
              <Switch value={anonymous} onValueChange={setAnonymous} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.onColor} />
            </View>

            {problem ? <AppText style={styles.err}>{problem}</AppText> : null}
            <Button title={numeric ? `Donate ${formatMoney(numeric, currency)}` : 'Donate'} onPress={donate} loading={busy} disabled={!options.methods.length} />
            <Pressable
              onPress={() => {
                onClose();
                router.push({ pathname: '/give/[slug]', params: { slug: church.slug, campaign: String(campaign.id) } });
              }}
              accessibilityRole="button"
              style={{ alignSelf: 'center', padding: 6 }}
            >
              <AppText style={styles.more}>More options</AppText>
            </Pressable>
          </View>
        ) : null}
      </StateView>
    </Sheet>
  );
}

const styles = StyleSheet.create({
  amountRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 },
  cur: { fontFamily: FONTS.semibold, fontSize: 18, color: COLORS.gray700, marginTop: 8 },
  amountInput: { fontFamily: FONTS.bold, fontSize: 40, color: COLORS.black, minWidth: 90, textAlign: 'center', paddingVertical: 2, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  err: { color: COLORS.danger, textAlign: 'center', fontFamily: FONTS.medium, fontSize: 13 },
  quick: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 8 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  phoneField: { flexDirection: 'row', alignItems: 'center', gap: 10, borderWidth: 1.5, borderColor: COLORS.gray300, borderRadius: RADIUS.lg, paddingHorizontal: 14 },
  phoneInput: { flex: 1, minHeight: 50, fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  prompt: { flexDirection: 'row', gap: 8, alignItems: 'flex-start', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: 10, marginTop: 10 },
  promptText: { flex: 1, fontFamily: FONTS.medium, fontSize: 12.5, lineHeight: 18, color: COLORS.primaryInk },
  switchRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.xs },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  more: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primary },
});
