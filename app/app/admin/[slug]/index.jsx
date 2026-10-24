// A church's admin dashboard in the app: what needs attention right now (prayer to approve, new members, reports)
// and shortcuts to everything staff do — only the ones their role allows. Data: GET /api/v1/admin/{church}/dashboard.
import { useCallback } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import ChurchAvatar from '../../../components/ChurchAvatar';
import ScreenHeader from '../../../components/ScreenHeader';
import SectionHeader from '../../../components/SectionHeader';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { can } from '../../../lib/admin';
import { dayAndTime, formatMoney } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

const TOOLS = [
  { key: 'invite', icon: 'link-outline', label: 'Invite link', perm: null, colors: ['#1B9AAA', '#0B3B44'] },
  { key: 'announcements', icon: 'megaphone-outline', label: 'Announcements', perm: 'content.manage', colors: ['#D34268', '#8E1C37'] },
  { key: 'posts', icon: 'images-outline', label: 'Updates & photos', perm: 'content.manage', colors: ['#26A69A', '#00695C'] },
  { key: 'events', icon: 'calendar-outline', label: 'Events & check-in', perm: 'events.manage', colors: ['#7C4DFF', '#4527A0'] },
  { key: 'prayer', icon: 'hand-left-outline', label: 'Prayer queue', perm: 'prayer.moderate', colors: ['#F57C00', '#BF360C'] },
  { key: 'members', icon: 'people-outline', label: 'Members', perm: 'members.manage', colors: ['#00A896', '#00695C'] },
  { key: 'broadcast', icon: 'paper-plane-outline', label: 'Message the church', perm: 'notifications.send', colors: ['#1E88E5', '#0D47A1'] },
  { key: 'games', icon: 'game-controller-outline', label: 'Host a quiz', perm: 'quizzes.manage', colors: ['#AB47BC', '#6A1B9A'] },
  { key: 'live', icon: 'radio-outline', label: 'Live service', perm: 'live.manage', colors: ['#E53935', '#B71C1C'] },
  { key: 'giving', icon: 'cash-outline', label: 'Giving', perm: 'giving.view', colors: ['#43A047', '#1B5E20'] },
  { key: 'publish', icon: 'book-outline', label: 'Devotionals & sermons', perm: 'content.manage', colors: ['#EC407A', '#AD1457'] },
  { key: 'reports', icon: 'flag-outline', label: 'Reports', perm: 'community.moderate', colors: ['#6D4C41', '#3E2723'] },
];

function Stat({ value, label, icon, onPress }) {
  return (
    <Pressable onPress={onPress} disabled={!onPress} style={styles.stat} accessibilityRole={onPress ? 'button' : undefined}>
      <Ionicons name={icon} size={20} color={COLORS.primary} />
      <AppText style={styles.statValue}>{value}</AppText>
      <AppText variant="caption">{label}</AppText>
    </Pressable>
  );
}

export default function AdminDashboard() {
  const { slug } = useLocalSearchParams();
  const { data, loading, refreshing, error, reload, retry } = useFetch(`/admin/${slug}/dashboard`);
  const d = data?.data;
  const go = (key) => router.push(`/admin/${slug}/${key}`);

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  const attention = d
    ? [
        d.stats.pending_prayers ? { icon: 'hand-left-outline', text: `${d.stats.pending_prayers} prayer ${d.stats.pending_prayers === 1 ? 'request' : 'requests'} waiting for approval`, to: 'prayer' } : null,
        d.stats.private_prayers ? { icon: 'lock-closed-outline', text: `${d.stats.private_prayers} private ${d.stats.private_prayers === 1 ? 'request' : 'requests'} to reply to`, to: 'prayer' } : null,
        d.stats.pending_members ? { icon: 'person-add-outline', text: `${d.stats.pending_members} ${d.stats.pending_members === 1 ? 'person' : 'people'} waiting to join`, to: 'members' } : null,
        d.stats.open_reports ? { icon: 'flag-outline', text: `${d.stats.open_reports} reported ${d.stats.open_reports === 1 ? 'item' : 'items'} to review`, to: 'reports' } : null,
        d.live ? { icon: 'radio-outline', text: `You are live: ${d.live.title}`, to: 'live' } : null,
      ].filter(Boolean)
    : [];

  return (
    <View style={styles.screen}>
      <ScreenHeader title={d?.church.name ?? 'Admin'} subtitle={d ? (d.is_admin ? 'Church admin' : 'Staff') : undefined} />
      <StateView loading={loading && !d} error={!d ? error : null} onRetry={retry}>
        {d ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <LinearGradient colors={[COLORS.primaryLight, COLORS.primaryDark]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
              <ChurchAvatar church={d.church} size={48} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.heroName}>{d.church.name}</AppText>
                <AppText style={styles.heroSub}>{d.stats.members} members · {d.stats.new_this_month} new this month</AppText>
              </View>
            </LinearGradient>

            <View style={styles.stats}>
              {d.stats.giving_month != null ? <Stat icon="cash-outline" value={formatMoney(d.stats.giving_month, d.stats.currency)} label={`given this month (${d.stats.gifts_month} gifts)`} onPress={() => go('giving')} /> : null}
              {d.stats.upcoming_events != null ? <Stat icon="calendar-outline" value={d.stats.upcoming_events} label="upcoming events" onPress={() => go('events')} /> : null}
            </View>

            {attention.length ? (
              <View style={{ marginTop: SPACING.md }}>
                <SectionHeader title="Needs your attention" />
                {attention.map((a) => (
                  <Pressable key={a.text} onPress={() => go(a.to)} style={styles.attention} accessibilityRole="button">
                    <View style={styles.attIcon}>
                      <Ionicons name={a.icon} size={20} color={COLORS.primary} />
                    </View>
                    <AppText style={styles.attText}>{a.text}</AppText>
                    <Ionicons name="chevron-forward" size={16} color={COLORS.primary} />
                  </Pressable>
                ))}
              </View>
            ) : null}

            {d.next_event ? (
              <Pressable onPress={() => router.push({ pathname: '/admin/[slug]/event/[id]', params: { slug, id: String(d.next_event.id) } })} style={styles.next} accessibilityRole="button">
                <AppText style={styles.nextLabel}>Next event</AppText>
                <AppText style={styles.nextTitle}>{d.next_event.title}</AppText>
                <AppText variant="caption">{dayAndTime(d.next_event.starts_at)} · {d.next_event.going} going — tap to open the check-in list</AppText>
              </Pressable>
            ) : null}

            <View style={{ marginTop: SPACING.md }}>
              <SectionHeader title="Manage" />
              <View style={styles.grid}>
                {TOOLS.filter((t) => !t.perm || can(d.permissions, t.perm)).map((t) => (
                  <Pressable key={t.key} onPress={() => go(t.key)} style={({ pressed }) => [styles.tileWrap, pressed && { opacity: 0.92 }]} accessibilityRole="button" accessibilityLabel={t.label}>
                    <LinearGradient colors={t.colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.tile}>
                      <Ionicons name={t.icon} size={24} color={COLORS.onColor} />
                      <AppText style={styles.tileLabel}>{t.label}</AppText>
                    </LinearGradient>
                  </Pressable>
                ))}
              </View>
              <AppText variant="caption" style={{ textAlign: 'center', marginTop: 8 }}>Church settings, roles, billing and reports are on the website.</AppText>
            </View>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  hero: { flexDirection: 'row', alignItems: 'center', gap: 14, marginHorizontal: SPACING.md, borderRadius: RADIUS.xl, padding: SPACING.md },
  heroName: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.onColor },
  heroSub: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.primaryPale },
  stats: { flexDirection: 'row', gap: 12, paddingHorizontal: SPACING.md, marginTop: 12 },
  stat: { flex: 1, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 3, ...SHADOW, shadowOpacity: 0.05 },
  statValue: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black },
  attention: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 12, marginHorizontal: SPACING.md, marginBottom: 8 },
  attIcon: { width: 38, height: 38, borderRadius: 13, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  attText: { flex: 1, fontFamily: FONTS.semibold, fontSize: 14, lineHeight: 19, color: COLORS.primaryInk },
  next: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, marginHorizontal: SPACING.md, marginTop: SPACING.md, gap: 3, ...SHADOW, shadowOpacity: 0.05 },
  nextLabel: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 0.8, color: COLORS.primary, textTransform: 'uppercase' },
  nextTitle: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, paddingHorizontal: SPACING.md },
  tileWrap: { width: '47.8%' },
  tile: { height: 92, borderRadius: RADIUS.xl - 4, padding: 13, justifyContent: 'space-between', ...SHADOW },
  tileLabel: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.onColor },
});
