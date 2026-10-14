// Give to a church without leaving the app, in four short steps on one screen: the amount, the mobile money number right
// below it (filled in from your profile; a prompt is sent to that phone and you approve it with your PIN), what the gift is for
// (the reasons the church itself lists), and Give. Staying anonymous, repeating the gift and a note are tucked under "More options".
// The same screen opens from the Give tab, a church page and a campaign (which arrives with ?campaign=<id>).
import { useEffect, useMemo, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, StyleSheet, Switch, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import KeyboardScroll from '../../components/KeyboardScroll';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage, fieldErrors } from '../../lib/api';
import { formatMoney } from '../../lib/format';
import { openPayment } from '../../lib/payments';
import { useFetch } from '../../lib/useFetch';

const REPEAT = [
  { key: '', label: 'Just once' },
  { key: 'weekly', label: 'Weekly' },
  { key: 'monthly', label: 'Monthly' },
  { key: 'quarterly', label: 'Quarterly' },
];

function Chip({ label, on, onPress }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityState={{ selected: on }} style={[styles.chip, on && styles.chipOn]}>
      <AppText style={[styles.chipText, on && { color: COLORS.white }]}>{label}</AppText>
    </Pressable>
  );
}

export default function GiveScreen() {
  const { slug, campaign: campaignParam } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/churches/${slug}/giving`);
  const options = data?.data;

  const [amount, setAmount] = useState('');
  const [phone, setPhone] = useState('');
  const [reasonId, setReasonId] = useState(null);
  const [category, setCategory] = useState(null); // only for a server that has no church reasons yet
  const [campaignId, setCampaignId] = useState(campaignParam ? Number(campaignParam) : null);
  const [method, setMethod] = useState(null);
  const [anonymous, setAnonymous] = useState(false);
  const [repeat, setRepeat] = useState('');
  const [note, setNote] = useState('');
  const [more, setMore] = useState(false);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState(null);
  const [amountError, setAmountError] = useState(null);
  const [phoneError, setPhoneError] = useState(null);

  // Fill in what we already know once the church's options arrive: the phone from your profile, the first reason, the first method.
  useEffect(() => {
    if (!options) return;
    setPhone((p) => p || options.saved_phone || '');
    setMethod((m) => m ?? options.methods?.[0]?.type ?? null);
    if (!campaignParam) {
      setReasonId((r) => r ?? options.reasons?.[0]?.id ?? null);
      setCategory((c) => c ?? (options.reasons?.length ? null : options.categories?.[0]?.key ?? 'offering'));
    }
  }, [options, campaignParam]);

  const numeric = Number(String(amount).replace(/[^\d]/g, '')) || 0;
  const campaign = options?.campaigns?.find((c) => c.id === campaignId);
  const currency = options?.currency ?? 'UGX';
  const chosen = options?.methods?.find((m) => m.type === method) ?? options?.methods?.[0];
  const label = useMemo(() => (numeric ? `Give ${formatMoney(numeric, currency)}` : 'Give'), [numeric, currency]);
  const reasons = options?.reasons ?? [];

  async function pay() {
    const cleanPhone = phone.replace(/[\s-]/g, '');
    let ok = true;
    if (numeric < 500) {
      setAmountError('The smallest gift is UGX 500.');
      ok = false;
    } else setAmountError(null);
    if (chosen?.needs_phone && !new RegExp(chosen.phone_pattern || String.raw`^(\+?256|0)?7\d{8}$`).test(cleanPhone)) {
      setPhoneError('Enter your mobile money number, for example 0772 123 456.');
      ok = false;
    } else setPhoneError(null);
    if (!ok || !chosen) return;

    setBusy(true);
    setProblem(null);
    try {
      const { data: res } = await api.post(`/churches/${slug}/give`, {
        amount: numeric,
        ...(campaignId ? { campaign_id: campaignId } : reasonId ? { reason_id: reasonId } : { category }),
        method: chosen.type,
        phone: chosen.needs_phone ? cleanPhone : undefined,
        is_anonymous: anonymous,
        ...(repeat ? { recurring: repeat } : {}),
        ...(note.trim() ? { note: note.trim() } : {}),
      });
      openPayment(res);
    } catch (e) {
      const fields = fieldErrors(e);
      if (fields.amount) setAmountError(fields.amount);
      if (fields.phone) setPhoneError(fields.phone);
      setProblem(fields.method || fields.campaign_id || fields.category || fields.reason_id || (!fields.amount && !fields.phone ? errorMessage(e) : null));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Give" subtitle={options?.church?.name} />
      <StateView loading={loading} error={error} onRetry={retry}>
        {options ? (
          <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
            <KeyboardScroll contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <View style={styles.churchRow}>
                <ChurchAvatar church={options.church} size={44} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.churchName}>{options.church.name}</AppText>
                  <AppText variant="caption">{campaign ? `Fundraising: ${campaign.title}` : 'Your gift goes straight to the church'}</AppText>
                </View>
              </View>

              <View style={styles.amountCard}>
                <AppText variant="caption" style={{ textAlign: 'center' }}>
                  How much would you like to give?
                </AppText>
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
                    <Chip key={q} label={q.toLocaleString()} on={numeric === q} onPress={() => setAmount(String(q))} />
                  ))}
                </View>

                {chosen?.needs_phone ? (
                  <View style={styles.phoneBox}>
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
                        placeholder="Mobile money number, e.g. 0772 123 456"
                        placeholderTextColor={COLORS.gray500}
                        autoComplete="tel"
                        style={styles.phoneInput}
                        accessibilityLabel="Mobile money number"
                      />
                    </View>
                    {phoneError ? <AppText style={[styles.err, { textAlign: 'left', marginTop: 6 }]}>{phoneError}</AppText> : null}
                    <View style={styles.prompt}>
                      <Ionicons name="phone-portrait-outline" size={18} color={COLORS.primaryDark} />
                      <AppText style={styles.promptText}>A prompt will be sent to this phone. Enter your mobile money PIN to approve it, and the amount is deducted from your mobile money.</AppText>
                    </View>
                    {!options.saved_phone ? (
                      <Pressable onPress={() => router.push('/profile/edit')} accessibilityRole="link">
                        <AppText style={styles.link}>Add your number to your profile to fill this in next time</AppText>
                      </Pressable>
                    ) : null}
                  </View>
                ) : null}
              </View>

              {options.methods.length > 1 ? (
                <>
                  <AppText style={styles.h}>Pay with</AppText>
                  <View style={styles.wrap}>
                    {options.methods.map((m) => (
                      <Chip key={m.type} label={m.label} on={chosen?.type === m.type} onPress={() => setMethod(m.type)} />
                    ))}
                  </View>
                </>
              ) : null}
              {!options.methods.length ? (
                <View style={styles.none}>
                  <Ionicons name="alert-circle-outline" size={22} color={COLORS.warning} />
                  <AppText style={{ flex: 1, color: COLORS.gray900 }}>This church has not switched on online payments yet. Please ask the church office how to give.</AppText>
                </View>
              ) : null}

              <AppText style={[styles.h, { marginTop: options.methods.length > 1 ? 18 : 4 }]}>What is it for?</AppText>
              <View style={styles.wrap}>
                {reasons.length
                  ? reasons.map((r) => (
                      <Chip
                        key={r.id}
                        label={r.name}
                        on={!campaignId && reasonId === r.id}
                        onPress={() => {
                          setReasonId(r.id);
                          setCampaignId(null);
                        }}
                      />
                    ))
                  : options.categories.map((c) => (
                      <Chip
                        key={c.key}
                        label={c.label}
                        on={!campaignId && category === c.key}
                        onPress={() => {
                          setCategory(c.key);
                          setCampaignId(null);
                        }}
                      />
                    ))}
              </View>
              {!campaignId && reasons.find((r) => r.id === reasonId)?.description ? (
                <AppText variant="caption" style={{ marginTop: 8 }}>
                  {reasons.find((r) => r.id === reasonId).description}
                </AppText>
              ) : null}
              {options.campaigns.length ? (
                <>
                  <AppText style={[styles.h, { marginTop: 18 }]}>Or support a fundraiser</AppText>
                  <View style={styles.wrap}>
                    {options.campaigns.map((c) => (
                      <Chip key={c.id} label={c.title} on={campaignId === c.id} onPress={() => setCampaignId(campaignId === c.id ? null : c.id)} />
                    ))}
                  </View>
                </>
              ) : null}

              <Pressable onPress={() => setMore((v) => !v)} style={styles.moreRow} accessibilityRole="button" accessibilityState={{ expanded: more }}>
                <AppText style={styles.moreText}>More options</AppText>
                <Ionicons name={more ? 'chevron-up' : 'chevron-down'} size={18} color={COLORS.primary} />
              </Pressable>
              {more ? (
                <View style={{ gap: 4 }}>
                  <AppText style={styles.h}>How often?</AppText>
                  <View style={styles.wrap}>
                    {REPEAT.map((r) => (
                      <Chip key={r.key} label={r.label} on={repeat === r.key} onPress={() => setRepeat(r.key)} />
                    ))}
                  </View>
                  {repeat ? (
                    <AppText variant="caption" style={{ marginTop: 6 }}>
                      The first gift is taken now. You can pause or stop it any time from the Give tab.
                    </AppText>
                  ) : null}
                  <View style={styles.switchRow}>
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.switchTitle}>Give anonymously</AppText>
                      <AppText variant="caption">Your name will not show on the church's lists.</AppText>
                    </View>
                    <Switch value={anonymous} onValueChange={setAnonymous} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
                  </View>
                  <TextField label="Note (optional)" value={note} onChangeText={setNote} placeholder="A word with your gift" maxLength={255} />
                </View>
              ) : null}

              {problem ? <AppText style={[styles.err, { marginTop: 12 }]}>{problem}</AppText> : null}
              <Button title={label} onPress={pay} loading={busy} disabled={!options.methods.length} style={{ marginTop: SPACING.md }} />
              <View style={styles.secure}>
                <Ionicons name="lock-closed-outline" size={14} color={COLORS.gray700} />
                <AppText variant="caption">Secure payment · you get a receipt by email</AppText>
              </View>

              {options.offline.length ? (
                <View style={styles.offline}>
                  <Ionicons name="information-circle-outline" size={20} color={COLORS.primary} />
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.switchTitle}>Other ways to give</AppText>
                    {options.offline.map((o) => (
                      <AppText key={o.type} variant="caption" style={{ marginTop: 3 }}>
                        <AppText style={{ fontFamily: FONTS.semibold, fontSize: 12 }}>{o.label}</AppText>
                        {o.instructions ? ` — ${o.instructions}` : ''}
                      </AppText>
                    ))}
                  </View>
                </View>
              ) : null}
            </KeyboardScroll>
          </KeyboardAvoidingView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, paddingBottom: 48 },
  churchRow: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: SPACING.md },
  churchName: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  amountCard: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: SPACING.md, paddingBottom: SPACING.md, marginBottom: SPACING.lg, ...SHADOW },
  amountRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 4 },
  cur: { fontFamily: FONTS.semibold, fontSize: 18, color: COLORS.gray700, marginTop: 8 },
  amountInput: { fontFamily: FONTS.bold, fontSize: 44, color: COLORS.black, minWidth: 90, textAlign: 'center', paddingVertical: 4, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  err: { color: COLORS.danger, textAlign: 'center', fontFamily: FONTS.medium, fontSize: 13, marginBottom: 6 },
  quick: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 8, marginTop: 8 },
  phoneBox: { marginTop: SPACING.md, paddingTop: SPACING.md, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.gray300 },
  phoneField: { flexDirection: 'row', alignItems: 'center', gap: 10, borderWidth: 1.5, borderColor: COLORS.gray300, borderRadius: RADIUS.lg, paddingHorizontal: 14 },
  phoneInput: { flex: 1, minHeight: 50, fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  prompt: { flexDirection: 'row', gap: 8, alignItems: 'flex-start', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: 10, marginTop: 10 },
  promptText: { flex: 1, fontFamily: FONTS.medium, fontSize: 12.5, lineHeight: 18, color: COLORS.primaryDark },
  link: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.primary, marginTop: 8 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black, marginBottom: 10 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  moreRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, marginTop: 8 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.primary },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginVertical: SPACING.md },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  secure: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, marginTop: 10 },
  none: { flexDirection: 'row', gap: 10, padding: 14, borderRadius: RADIUS.lg, backgroundColor: '#FFF8E6', alignItems: 'center' },
  offline: { flexDirection: 'row', gap: 10, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14, marginTop: SPACING.lg },
});
