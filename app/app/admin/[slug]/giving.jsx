// Giving at a glance: today, this week, this month and this year, where this month's giving went (by category and
// payment method), and the latest gifts. Recording cash gifts, refunds and payouts stay on the website.
// Data: /api/v1/admin/{church}/giving/summary and …/giving.
import { ScrollView, StyleSheet, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import ScreenHeader from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
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
  const s = summary.data?.data;
  const list = gifts.data?.data ?? [];

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Giving" subtitle="What your church received" />
      <StateView loading={summary.loading && !s} error={!s ? summary.error : null} onRetry={summary.retry}>
        {s ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} showsVerticalScrollIndicator={false}>
            <View style={styles.hero}>
              <AppText style={styles.heroLabel}>This month</AppText>
              <AppText style={styles.heroValue}>{formatMoney(s.month, s.currency)}</AppText>
              <AppText style={styles.heroSub}>{s.gifts_month} gifts · last month {formatMoney(s.last_month, s.currency)}</AppText>
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

            {s.by_category.length ? (
              <View style={styles.card}>
                <AppText style={styles.h}>This month by category</AppText>
                <Bars rows={s.by_category} total={s.month} currency={s.currency} />
              </View>
            ) : null}
            {s.by_method.length ? (
              <View style={styles.card}>
                <AppText style={styles.h}>This month by payment method</AppText>
                <Bars rows={s.by_method} total={s.month} currency={s.currency} />
              </View>
            ) : null}

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
  heroValue: { fontFamily: FONTS.bold, fontSize: 32, color: COLORS.white },
  heroSub: { fontFamily: FONTS.regular, fontSize: 13, color: COLORS.primaryPale },
  grid: { gap: 8, marginTop: 10 },
  stat: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingHorizontal: 14, paddingVertical: 12, ...SHADOW, shadowOpacity: 0.05 },
  statLabel: { fontFamily: FONTS.medium, fontSize: 14, color: COLORS.gray700 },
  statValue: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
  pending: { fontFamily: FONTS.medium, fontSize: 13.5, color: '#C25B00', backgroundColor: '#FFF3E0', borderRadius: RADIUS.md, padding: 12, marginTop: 10 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, marginTop: 12, gap: 12, ...SHADOW, shadowOpacity: 0.05 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  rowLabel: { fontFamily: FONTS.medium, fontSize: 14, color: COLORS.black },
  rowValue: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black },
  track: { height: 8, borderRadius: 4, backgroundColor: COLORS.primaryMist, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: COLORS.primary },
  gift: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, marginTop: 8 },
  giver: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  amount: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
});
