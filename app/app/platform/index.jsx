// Platform admin: run FaithFellows from the app with the same sign-in as on the website. What needs attention today (church applications, reports, podcasts
// waiting), then the tools: churches, people, reports, podcast reviews, a notification to everyone and the radio. Only super admins get here (the server
// checks every request). Data: GET /api/v1/platform/overview.
import { useCallback } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Redirect, router, useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { useAuth } from '../../lib/auth';
import { useFetch } from '../../lib/useFetch';

function Tile({ icon, label, value, hint, hot, onPress }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.tile, hot && styles.tileHot, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`${label}${value !== undefined ? `, ${value}` : ''}`}>
      <View style={[styles.tileIcon, hot && { backgroundColor: COLORS.primary }]}>
        <Ionicons name={icon} size={20} color={hot ? COLORS.onColor : COLORS.primary} />
      </View>
      {value !== undefined ? <AppText style={styles.value}>{value}</AppText> : null}
      <AppText style={styles.tileLabel}>{label}</AppText>
      {hint ? (
        <AppText style={styles.hint} numberOfLines={2}>
          {hint}
        </AppText>
      ) : null}
    </Pressable>
  );
}

export default function Platform() {
  const { user } = useAuth();
  const { data, loading, refreshing, error, reload, retry } = useFetch('/platform/overview', { enabled: !!user?.is_super_admin });

  useFocusEffect(
    useCallback(() => {
      if (user?.is_super_admin) reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user?.is_super_admin]),
  );

  if (user && !user.is_super_admin) return <Redirect href="/(tabs)/profile" />;
  const o = data?.data;
  const wait = o?.needs_attention ?? {};

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Platform admin" subtitle="Run FaithFellows from here" />
      <StateView loading={loading} error={error} onRetry={retry}>
        <ScrollView contentContainerStyle={styles.body} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
          {o?.broadcast ? (
            <Pressable onPress={() => router.push('/platform/radio')} style={styles.live} accessibilityRole="button" accessibilityLabel="A broadcast is on every radio">
              <Ionicons name="radio" size={20} color={COLORS.onColor} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.liveTitle}>On every radio now</AppText>
                <AppText style={styles.liveText} numberOfLines={1}>
                  {o.broadcast.title}
                  {o.broadcast.message ? ` · ${o.broadcast.message}` : ''}
                </AppText>
              </View>
              <Ionicons name="chevron-forward" size={18} color={COLORS.onColor} />
            </Pressable>
          ) : null}

          <AppText style={styles.section}>Needs your attention</AppText>
          <View style={styles.grid}>
            <Tile icon="business" label="Church applications" value={wait.applications ?? 0} hot={(wait.applications ?? 0) > 0} onPress={() => router.push({ pathname: '/platform/churches', params: { status: 'pending' } })} />
            <Tile icon="flag" label="Reports" value={wait.reports ?? 0} hint={wait.old_reports ? `${wait.old_reports} waiting over 2 days` : undefined} hot={(wait.reports ?? 0) > 0} onPress={() => router.push('/platform/reports')} />
            <Tile icon="mic" label="Podcasts to review" value={wait.podcasts ?? 0} hot={(wait.podcasts ?? 0) > 0} onPress={() => router.push('/platform/podcasts')} />
          </View>

          <AppText style={styles.section}>The platform</AppText>
          <View style={styles.grid}>
            <Tile icon="people" label="People" value={o?.users ?? 0} hint={`${o?.new_users_this_week ?? 0} joined this week`} onPress={() => router.push('/platform/people')} />
            <Tile icon="home" label="Churches" value={o?.churches?.active ?? 0} hint={o?.churches?.suspended ? `${o.churches.suspended} suspended` : 'active'} onPress={() => router.push({ pathname: '/platform/churches', params: { status: 'active' } })} />
          </View>

          <AppText style={styles.section}>Reach everyone</AppText>
          <View style={styles.grid}>
            <Tile icon="notifications" label="Notify everyone" hint={`${o?.phones ?? 0} phones have the app`} onPress={() => router.push('/platform/notify')} />
            <Tile icon="radio" label="Radio broadcast" hint="Put a song or teaching on every radio" onPress={() => router.push('/platform/radio')} />
          </View>

          <AppText style={styles.foot}>Billing, settings, database updates and the other tools stay on the website, at faithfellows.com/super-admin.</AppText>
        </ScrollView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  body: { padding: SPACING.md, paddingBottom: 48 },
  section: { fontFamily: FONTS.display, fontSize: 18, color: COLORS.black, marginTop: 18, marginBottom: 10 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  tile: { width: '47.5%', backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, gap: 3, minHeight: 112, ...SHADOW },
  tileHot: { borderWidth: 1.5, borderColor: COLORS.primaryPale },
  tileIcon: { width: 36, height: 36, borderRadius: 12, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  value: { fontFamily: FONTS.bold, fontSize: 26, lineHeight: 30, color: COLORS.black },
  tileLabel: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.black },
  hint: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 16, color: COLORS.gray700 },
  live: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primary, borderRadius: RADIUS.lg, padding: 14 },
  liveTitle: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.onColor },
  liveText: { fontFamily: FONTS.regular, fontSize: 13, color: 'rgba(255,255,255,0.9)' },
  foot: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray700, textAlign: 'center', marginTop: 26, paddingHorizontal: 12 },
});
