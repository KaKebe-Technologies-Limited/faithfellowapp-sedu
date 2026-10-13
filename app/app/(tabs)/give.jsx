// Give tab: everything about giving, inside the app. Start a gift to any church you follow, support a
// campaign, see this year's total, manage repeating gifts and pledges, and open receipts for past gifts.
// Data: GET /giving/summary, /giving, /campaigns. Giving itself is the in-app screen /give/[slug].
import { useCallback } from 'react';
import { Alert, FlatList, Platform, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import SectionHeader from '../../components/SectionHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, mediaUrl } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { formatDate, formatMoney } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';
import { Image } from 'expo-image';

const STATUS = {
  succeeded: { label: 'Received', color: COLORS.success },
  pending: { label: 'Pending', color: COLORS.warning },
  processing: { label: 'Processing', color: COLORS.warning },
  failed: { label: 'Failed', color: COLORS.danger },
  refunded: { label: 'Refunded', color: COLORS.gray700 },
};

const EVERY = { weekly: 'every week', monthly: 'every month', quarterly: 'every 3 months' };

function confirm(title, message, action, run) {
  if (Platform.OS === 'web') return run();
  Alert.alert(title, message, [{ text: 'Not now', style: 'cancel' }, { text: action, style: 'destructive', onPress: run }]);
}

export default function GiveTab() {
  const { user } = useAuth();
  const summary = useFetch('/giving/summary');
  const gifts = useFetch('/giving');
  const campaigns = useFetch('/campaigns');
  const s = summary.data?.data;
  const list = gifts.data?.data ?? [];
  const churches = user?.churches ?? [];

  // Come back to fresh numbers after giving.
  useFocusEffect(
    useCallback(() => {
      summary.reload();
      gifts.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  async function setRecurring(r, status) {
    try {
      await api.put(`/recurring/${r.id}`, { status });
      summary.reload();
    } catch (e) {
      Alert.alert('Could not update', errorMessage(e));
    }
  }

  async function cancelPledge(p) {
    try {
      await api.delete(`/pledges/${p.id}`);
      summary.reload();
    } catch (e) {
      Alert.alert('Could not cancel', errorMessage(e));
    }
  }

  const header = (
    <View>
      <LinearGradient colors={[COLORS.primaryLight, COLORS.primaryDark]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <Ionicons name="heart" size={26} color={COLORS.white} />
        <AppText style={styles.heroTitle}>Give with joy</AppText>
        <AppText style={styles.heroVerse}>“God loves a cheerful giver.” — 2 Corinthians 9:7</AppText>
        {s ? (
          <View style={styles.heroTotal}>
            <AppText style={styles.heroTotalLabel}>Given in {s.year}</AppText>
            <AppText style={styles.heroTotalValue}>{formatMoney(s.year_total, s.currency)}</AppText>
          </View>
        ) : null}
      </LinearGradient>

      <SectionHeader title="Give to a church" />
      {churches.length ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
          {churches.map((c) => (
            <Pressable key={c.id} onPress={() => router.push({ pathname: '/give/[slug]', params: { slug: c.slug } })} style={styles.church} accessibilityRole="button" accessibilityLabel={`Give to ${c.name}`}>
              <ChurchAvatar church={c} size={54} />
              <AppText style={styles.churchName} numberOfLines={2}>
                {c.name}
              </AppText>
              <View style={styles.giveChip}>
                <AppText style={styles.giveChipText}>Give</AppText>
              </View>
            </Pressable>
          ))}
        </ScrollView>
      ) : (
        <Pressable onPress={() => router.push('/(tabs)/churches')} style={styles.empty} accessibilityRole="button">
          <Ionicons name="search-outline" size={22} color={COLORS.primary} />
          <AppText style={{ flex: 1, color: COLORS.gray900 }}>Follow a church to give to it. Tap to find one.</AppText>
        </Pressable>
      )}

      {campaigns.data?.data?.length ? (
        <View style={{ marginTop: SPACING.md }}>
          <SectionHeader title="Campaigns" actionLabel="See all" onAction={() => router.push('/campaigns')} />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
            {campaigns.data.data.slice(0, 6).map((c) => {
              const pct = Math.max(0, Math.min(100, Math.round(c.percent)));
              return (
                <Pressable key={c.id} onPress={() => router.push({ pathname: '/campaign/[id]', params: { id: c.id } })} style={styles.campaign} accessibilityRole="button" accessibilityLabel={`Campaign: ${c.title}`}>
                  <View style={styles.campaignCover}>
                    <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
                    {c.cover_url ? <Image source={{ uri: mediaUrl(c.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
                  </View>
                  <View style={{ padding: 12, gap: 6 }}>
                    <AppText style={styles.campaignTitle} numberOfLines={2}>
                      {c.title}
                    </AppText>
                    <View style={styles.track}>
                      <View style={[styles.fill, { width: `${Math.max(pct, 3)}%` }]} />
                    </View>
                    <AppText variant="caption">{pct}% of {formatMoney(c.goal, c.currency)}</AppText>
                  </View>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>
      ) : null}

      {s?.recurring?.length ? (
        <View style={{ marginTop: SPACING.md }}>
          <SectionHeader title="Repeating gifts" />
          {s.recurring.map((r) => (
            <View key={r.id} style={styles.row}>
              <ChurchAvatar church={r.church} size={40} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.rowTitle}>{formatMoney(r.amount, r.currency)} {EVERY[r.frequency] ?? r.frequency}</AppText>
                <AppText variant="caption" numberOfLines={1}>{r.church.name} · {r.status === 'paused' ? 'Paused' : `next ${formatDate(r.next_run_on)}`}</AppText>
              </View>
              <Pressable onPress={() => setRecurring(r, r.status === 'paused' ? 'active' : 'paused')} style={styles.small} accessibilityRole="button">
                <AppText style={styles.smallText}>{r.status === 'paused' ? 'Resume' : 'Pause'}</AppText>
              </Pressable>
              <Pressable onPress={() => confirm('Stop this gift?', 'It will not be charged again.', 'Stop', () => setRecurring(r, 'cancelled'))} hitSlop={8} accessibilityLabel="Stop repeating gift">
                <Ionicons name="close-circle-outline" size={22} color={COLORS.gray500} />
              </Pressable>
            </View>
          ))}
        </View>
      ) : null}

      {s?.pledges?.length ? (
        <View style={{ marginTop: SPACING.md }}>
          <SectionHeader title="My pledges" />
          {s.pledges.map((p) => {
            const pct = p.amount ? Math.min(100, Math.round((p.paid / p.amount) * 100)) : 0;
            return (
              <View key={p.id} style={[styles.row, { alignItems: 'flex-start' }]}>
                <ChurchAvatar church={p.church} size={40} />
                <View style={{ flex: 1, gap: 4 }}>
                  <AppText style={styles.rowTitle}>{formatMoney(p.amount, p.currency)}{p.campaign ? ` · ${p.campaign.title}` : ''}</AppText>
                  <View style={styles.track}>
                    <View style={[styles.fill, { width: `${Math.max(pct, 2)}%` }]} />
                  </View>
                  <AppText variant="caption">{formatMoney(p.paid, p.currency)} paid · {pct}%</AppText>
                </View>
                <View style={{ gap: 8, alignItems: 'flex-end' }}>
                  <Pressable onPress={() => router.push({ pathname: '/give/[slug]', params: { slug: p.church.slug, ...(p.campaign ? { campaign: String(p.campaign.id) } : {}) } })} style={styles.small} accessibilityRole="button">
                    <AppText style={styles.smallText}>Pay</AppText>
                  </Pressable>
                  <Pressable onPress={() => confirm('Cancel this pledge?', 'You will no longer get reminders for it.', 'Cancel pledge', () => cancelPledge(p))} hitSlop={8}>
                    <AppText variant="caption" style={{ textDecorationLine: 'underline' }}>Cancel</AppText>
                  </Pressable>
                </View>
              </View>
            );
          })}
        </View>
      ) : null}

      <View style={{ marginTop: SPACING.md }}>
        <SectionHeader title="My gifts" />
      </View>
    </View>
  );

  return (
    <StateView loading={gifts.loading && summary.loading} error={gifts.error && summary.error ? gifts.error : null} onRetry={() => { gifts.retry(); summary.retry(); campaigns.retry(); }}>
      <FlatList
        data={list}
        keyExtractor={(g) => g.reference}
        contentContainerStyle={{ paddingBottom: 32 }}
        ListHeaderComponent={header}
        ListEmptyComponent={
          <View style={styles.none}>
            <Ionicons name="receipt-outline" size={28} color={COLORS.primary} />
            <AppText style={{ fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black }}>No gifts yet</AppText>
            <AppText style={{ textAlign: 'center', color: COLORS.gray700 }}>When you give, your receipts and history will appear here.</AppText>
          </View>
        }
        refreshControl={<RefreshControl refreshing={gifts.refreshing} onRefresh={() => { gifts.reload(); summary.reload(); campaigns.reload(); }} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
        renderItem={({ item: g }) => {
          const st = STATUS[g.status] ?? { label: g.status, color: COLORS.gray700 };
          const receipt = g.status === 'succeeded' || g.status === 'refunded';
          return (
            <Pressable
              onPress={() => (receipt ? router.push({ pathname: '/receipt/[reference]', params: { reference: g.reference } }) : g.status === 'pending' || g.status === 'processing' ? router.push({ pathname: '/pay/[reference]', params: { reference: g.reference } }) : null)}
              style={styles.row}
              accessibilityRole="button"
            >
              <ChurchAvatar church={g.church} size={40} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.rowTitle}>{formatMoney(g.amount, g.currency)}</AppText>
                <AppText variant="caption" numberOfLines={1}>{[g.church?.name, g.campaign?.title ?? g.category].filter(Boolean).join(' · ')}</AppText>
                <AppText variant="caption">{formatDate(g.paid_at ?? g.created_at)}</AppText>
              </View>
              <View style={[styles.pill, { backgroundColor: `${st.color}1F` }]}>
                <AppText variant="caption" style={{ color: st.color, fontFamily: FONTS.semibold }}>{st.label}</AppText>
              </View>
            </Pressable>
          );
        }}
      />
    </StateView>
  );
}

const styles = StyleSheet.create({
  hero: { margin: SPACING.md, borderRadius: RADIUS.xl, padding: SPACING.lg, gap: 4 },
  heroTitle: { fontFamily: FONTS.bold, fontSize: 24, color: COLORS.white, marginTop: 4 },
  heroVerse: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 14, lineHeight: 20, color: COLORS.primaryPale },
  heroTotal: { marginTop: 14, backgroundColor: 'rgba(255,255,255,0.16)', borderRadius: RADIUS.lg, padding: 12 },
  heroTotalLabel: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.primaryPale },
  heroTotalValue: { fontFamily: FONTS.bold, fontSize: 26, color: COLORS.white },
  rail: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 6 },
  church: { width: 118, alignItems: 'center', gap: 6, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, ...SHADOW },
  churchName: { fontFamily: FONTS.semibold, fontSize: 13, lineHeight: 17, color: COLORS.black, textAlign: 'center', minHeight: 34 },
  giveChip: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 6 },
  giveChipText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.white },
  empty: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14, marginHorizontal: SPACING.md },
  campaign: { width: 232, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, overflow: 'hidden', ...SHADOW },
  campaignCover: { height: 100, backgroundColor: COLORS.primaryDark },
  campaignTitle: { fontFamily: FONTS.bold, fontSize: 14.5, lineHeight: 19, color: COLORS.black },
  track: { height: 7, borderRadius: 4, backgroundColor: COLORS.primaryMist, overflow: 'hidden' },
  fill: { height: 7, borderRadius: 4, backgroundColor: COLORS.primary },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW },
  rowTitle: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  small: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 7 },
  smallText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary },
  pill: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: RADIUS.full },
  none: { alignItems: 'center', gap: 6, padding: SPACING.xl },
});
