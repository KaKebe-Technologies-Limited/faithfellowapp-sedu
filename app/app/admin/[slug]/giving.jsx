// Giving at a glance: today, this week, this month and this year, where this month's giving went (clustered by what it was given
// for, and by payment method), fees and net, and the latest gifts. The church also writes here the reasons members can give for.
// Full financial records, cash entry, refunds and payouts are on the website.
// Data: /api/v1/admin/{church}/giving/summary, …/giving and …/giving/reasons.
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import ScreenHeader from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../../constants/theme';
import { errorMessage } from '../../../lib/api';
import { adminApi } from '../../../lib/admin';
import { formatMoney, timeAgo } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

function Bars({ rows, total, currency }) {
  return (
    <View style={{ gap: 10 }}>
      {rows.map((r) => (
        <View key={r.key} style={{ gap: 4 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
            <AppText style={styles.rowLabel}>{r.label}</AppText>
            <AppText style={styles.rowValue}>{formatMoney(r.total, currency)}</AppText>
          </View>
          <View style={styles.track}>
            <View style={[styles.fill, { width: `${Math.max(3, total ? (r.total / total) * 100 : 0)}%` }]} />
          </View>
        </View>
      ))}
    </View>
  );
}

export default function AdminGiving() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const summary = useFetch(admin.path('/giving/summary'));
  const gifts = useFetch(admin.path('/giving'));
  const reasons = useFetch(admin.path('/giving/reasons'));
  const s = summary.data?.data;
  const list = gifts.data?.data ?? [];
  const [newReason, setNewReason] = useState('');
  const [adding, setAdding] = useState(false);

  async function addReason() {
    if (!newReason.trim()) return;
    setAdding(true);
    try {
      await admin.post('/giving/reasons', { name: newReason.trim() });
      setNewReason('');
      reasons.reload();
    } catch (e) {
      Alert.alert('Could not add it', errorMessage(e));
    } finally {
      setAdding(false);
    }
  }

  async function toggle(reason, value) {
    try {
      await admin.put(`/giving/reasons/${reason.id}`, { is_active: value });
      reasons.reload();
    } catch (e) {
      Alert.alert('Could not change it', errorMessage(e));
    }
  }

  function remove(reason) {
    Alert.alert(`Remove “${reason.name}”?`, 'Gifts already given keep its name in your records.', [
      { text: 'Keep it', style: 'cancel' },
      {
        text: 'Remove',
        style: 'destructive',
        onPress: async () => {
          try {
            await admin.delete(`/giving/reasons/${reason.id}`);
            reasons.reload();
          } catch (e) {
            Alert.alert('Could not remove it', errorMessage(e));
          }
        },
      },
    ]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Giving" subtitle="What your church received" />
      <StateView loading={summary.loading && !s} error={!s ? summary.error : null} onRetry={summary.retry}>
        {s ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} showsVerticalScrollIndicator={false}>
            <View style={styles.hero}>
              <AppText style={styles.heroLabel}>This month</AppText>
              <AppText style={styles.heroValue}>{formatMoney(s.month, s.currency)}</AppText>
              <AppText style={styles.heroSub}>{s.gifts_month} gifts{s.givers_month ? ` from ${s.givers_month} givers` : ''} · last month {formatMoney(s.last_month, s.currency)}</AppText>
              {s.net_month != null ? <AppText style={styles.heroSub}>Fees {formatMoney(s.fees_month, s.currency)} · net to the church {formatMoney(s.net_month, s.currency)}</AppText> : null}
            </View>
            <View style={styles.grid}>
              {[['Today', s.today], ['This week', s.week], ['This year', s.year]].map(([label, value]) => (
                <View key={label} style={styles.stat}>
                  <AppText style={styles.statLabel}>{label}</AppText>
                  <AppText style={styles.statValue}>{formatMoney(value, s.currency)}</AppText>
                </View>
              ))}
            </View>
            {s.pending ? <AppText style={styles.pending}>{s.pending} {s.pending === 1 ? 'payment is' : 'payments are'} still waiting to be confirmed.</AppText> : null}

            {s.awaiting_payout > 0 ? <AppText style={styles.pending}>{formatMoney(s.awaiting_payout, s.currency)} collected online is waiting to be paid out to the church.</AppText> : null}

            {(s.by_reason ?? s.by_category).length ? (
              <View style={styles.card}>
                <AppText style={styles.h}>This month, by what it was given for</AppText>
                <Bars rows={s.by_reason ?? s.by_category} total={s.month} currency={s.currency} />
              </View>
            ) : null}
            {s.by_method.length ? (
              <View style={styles.card}>
                <AppText style={styles.h}>This month by payment method</AppText>
                <Bars rows={s.by_method} total={s.month} currency={s.currency} />
              </View>
            ) : null}

            <View style={styles.card}>
              <AppText style={styles.h}>What members can give for</AppText>
              <AppText variant="caption" style={{ marginTop: -6 }}>Members choose one of these when they give. Only your church adds to this list.</AppText>
              {(reasons.data?.data ?? []).map((r) => (
                <View key={r.id} style={styles.reasonRow}>
                  <View style={{ flex: 1 }}>
                    <AppText style={[styles.rowLabel, !r.is_active && { color: COLORS.gray500 }]} numberOfLines={1}>{r.name}</AppText>
                    <AppText variant="caption">{r.gifts ? `${r.gifts} gifts · ${formatMoney(r.total, s.currency)}` : 'No gifts yet'}</AppText>
                  </View>
                  <Switch value={r.is_active} onValueChange={(v) => toggle(r, v)} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.onColor} accessibilityLabel={`Show ${r.name} to members`} />
                  <Pressable onPress={() => remove(r)} accessibilityRole="button" accessibilityLabel={`Remove ${r.name}`} style={{ padding: 6 }}>
                    <Ionicons name="trash-outline" size={19} color={COLORS.danger} />
                  </Pressable>
                </View>
              ))}
              <View style={styles.addRow}>
                <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={newReason} onChangeText={setNewReason} placeholder="New reason, e.g. Youth camp" placeholderTextColor={COLORS.gray500} maxLength={60} style={styles.addInput} onSubmitEditing={addReason} accessibilityLabel="New giving reason" />
                <Button title="Add" small loading={adding} disabled={!newReason.trim()} onPress={addReason} />
              </View>
            </View>

            <AppText style={[styles.h, { marginTop: SPACING.lg }]}>Latest gifts</AppText>
            {list.length === 0 ? <AppText variant="caption">No gifts yet.</AppText> : null}
            {list.map((g) => (
              <View key={g.reference} style={styles.gift}>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.giver} numberOfLines={1}>{g.giver}</AppText>
                  <AppText variant="caption" numberOfLines={1}>{[g.campaign ?? g.category, g.method, timeAgo(g.at)].filter(Boolean).join(' · ')}</AppText>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  <AppText style={styles.amount}>{formatMoney(g.amount, g.currency)}</AppText>
                  {g.status !== 'succeeded' ? <AppText variant="caption" style={{ color: COLORS.warning, fontFamily: FONTS.semibold }}>{g.status}</AppText> : null}
                </View>
              </View>
            ))}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  hero: { backgroundColor: COLORS.primaryDark, borderRadius: RADIUS.xl, padding: SPACING.lg, gap: 3 },
  heroLabel: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.primaryPale },
  heroValue: { fontFamily: FONTS.bold, fontSize: 32, color: COLORS.onColor },
  heroSub: { fontFamily: FONTS.regular, fontSize: 13, color: COLORS.primaryPale },
  grid: { gap: 8, marginTop: 10 },
  stat: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingHorizontal: 14, paddingVertical: 12, ...SHADOW, shadowOpacity: 0.05 },
  statLabel: { fontFamily: FONTS.medium, fontSize: 14, color: COLORS.gray700 },
  statValue: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
  pending: { fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.warningInk, backgroundColor: COLORS.warningMist, borderRadius: RADIUS.md, padding: 12, marginTop: 10 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, marginTop: 12, gap: 12, ...SHADOW, shadowOpacity: 0.05 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  rowLabel: { fontFamily: FONTS.medium, fontSize: 14, color: COLORS.black },
  rowValue: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black },
  track: { height: 8, borderRadius: 4, backgroundColor: COLORS.primaryMist, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: COLORS.primary },
  reasonRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 4 },
  addRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  addInput: { flex: 1, minHeight: 44, borderWidth: 1.5, borderColor: COLORS.gray300, borderRadius: RADIUS.md, paddingHorizontal: 12, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  gift: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, marginTop: 8 },
  giver: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  amount: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
});
