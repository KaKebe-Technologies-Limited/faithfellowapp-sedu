// Give to a church without leaving the app: pick or type an amount, say what it is for (tithe, offering,
// a campaign…), choose to stay anonymous or repeat the gift, then pay with mobile money or card. The same
// screen opens from the Give tab, a church page and a campaign (which arrives with ?campaign=<id>).
import { useMemo, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, StyleSheet, Switch, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import PayForm from '../../components/PayForm';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import KeyboardScroll from '../../components/KeyboardScroll';
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
  const [category, setCategory] = useState('offering');
  const [campaignId, setCampaignId] = useState(campaignParam ? Number(campaignParam) : null);
  const [anonymous, setAnonymous] = useState(false);
  const [repeat, setRepeat] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState(null);
  const [amountError, setAmountError] = useState(null);

  const numeric = Number(String(amount).replace(/[^\d]/g, '')) || 0;
  const campaign = options?.campaigns?.find((c) => c.id === campaignId);
  const currency = options?.currency ?? 'UGX';
  const label = useMemo(() => (numeric ? `Give ${formatMoney(numeric, currency)}` : 'Give'), [numeric, currency]);

  async function pay({ method, phone }) {
    if (numeric < 500) {
      setAmountError('The smallest gift is UGX 500.');
      return;
    }
    setAmountError(null);
    setBusy(true);
    setProblem(null);
    try {
      const { data: res } = await api.post(`/churches/${slug}/give`, {
        amount: numeric,
        ...(campaignId ? { campaign_id: campaignId } : { category }),
        method,
        phone,
        is_anonymous: anonymous,
        ...(repeat ? { recurring: repeat } : {}),
        ...(note.trim() ? { note: note.trim() } : {}),
      });
      openPayment(res);
    } catch (e) {
      const fields = fieldErrors(e);
      if (fields.amount) setAmountError(fields.amount);
      setProblem(fields.phone || fields.method || fields.campaign_id || fields.category || (!fields.amount ? errorMessage(e) : null));
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
                <ChurchAvatar church={options.church} size={46} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.churchName}>{options.church.name}</AppText>
                  <AppText variant="caption">{campaign ? `For ${campaign.title}` : 'Your gift goes straight to the church'}</AppText>
                </View>
              </View>

              <View style={styles.amountCard}>
                <AppText variant="caption" style={{ textAlign: 'center' }}>
                  Amount
                </AppText>
                <View style={styles.amountRow}>
                  <AppText style={styles.cur}>{currency}</AppText>
                  <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE}
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
              </View>

              <AppText style={styles.h}>What is it for?</AppText>
              <View style={styles.wrap}>
                {options.categories.map((c) => (
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
              {options.campaigns.length ? (
                <>
                  <AppText style={[styles.h, { marginTop: 14 }]}>Or support a campaign</AppText>
                  <View style={styles.wrap}>
                    {options.campaigns.map((c) => (
                      <Chip key={c.id} label={c.title} on={campaignId === c.id} onPress={() => setCampaignId(campaignId === c.id ? null : c.id)} />
                    ))}
                  </View>
                </>
              ) : null}

              <AppText style={[styles.h, { marginTop: 18 }]}>How often?</AppText>
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

              <View style={{ marginTop: 6 }}>
                <PayForm methods={options.methods} savedPhone={options.saved_phone} submitLabel={label} loading={busy} error={problem} onSubmit={pay} />
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
  amountCard: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: SPACING.md, paddingBottom: 14, marginBottom: SPACING.lg, ...SHADOW },
  amountRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 4 },
  cur: { fontFamily: FONTS.semibold, fontSize: 18, color: COLORS.gray700, marginTop: 8 },
  amountInput: { fontFamily: FONTS.bold, fontSize: 44, color: COLORS.black, minWidth: 90, textAlign: 'center', paddingVertical: 4, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  err: { color: COLORS.danger, textAlign: 'center', fontFamily: FONTS.medium, fontSize: 13, marginBottom: 6 },
  quick: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 8, marginTop: 8 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black, marginBottom: 10 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginVertical: SPACING.md },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  offline: { flexDirection: 'row', gap: 10, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14, marginTop: SPACING.lg },
});
