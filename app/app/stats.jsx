// "How I am doing": every figure behind the rings on your profile, for the week, the month or all the time you have been here. Minutes with
// the Bible, chapters, radio and podcasts, scriptures and messages shared, days you opened the app, your streak, and the rest of what you
// have done: highlights, notes, games, prayers and what you have given this year. Private to you. Data: GET /api/v1/me/stats?period=.
import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../components/AppText';
import ProgressRing from '../components/ProgressRing';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import { SOFT, SOFT_SHADOW } from '../constants/soft';
import { formatMoney } from '../lib/format';
import { useFetch } from '../lib/useFetch';

const PERIODS = [
  ['week', 'This week'],
  ['month', 'This month'],
  ['all', 'All time'],
];
const RINGS = { bible: COLORS.primary, listening: COLORS.success, sharing: COLORS.info };

const minutes = (m) => (m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`);

function Row({ icon, color, label, value, last }) {
  return (
    <View style={[styles.row, !last && styles.rowLine]}>
      <View style={[styles.rowIcon, { backgroundColor: `${color}1F` }]}>
        <Ionicons name={icon} size={17} color={color} />
      </View>
      <AppText style={styles.rowLabel}>{label}</AppText>
      <AppText style={styles.rowValue}>{value}</AppText>
    </View>
  );
}

export default function StatsScreen() {
  const [period, setPeriod] = useState('week');
  const { data, loading, refreshing, error, reload, retry } = useFetch(`/me/stats?period=${period}`);
  const s = data?.data;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="My statistics" subtitle="How you are doing in the app" />
      <View style={styles.periods}>
        {PERIODS.map(([key, label]) => (
          <Pressable key={key} onPress={() => setPeriod(key)} style={[styles.period, period === key && styles.periodOn]} accessibilityRole="button" accessibilityState={{ selected: period === key }}>
            <AppText style={[styles.periodText, period === key && { color: COLORS.white }]}>{label}</AppText>
          </Pressable>
        ))}
      </View>
      <StateView loading={loading && !s} error={!s ? error : null} onRetry={retry}>
        {s ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 48 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <View style={styles.rings}>
              {s.rings.map((r) => (
                <View key={r.key} style={styles.ring}>
                  <ProgressRing percent={r.percent} color={RINGS[r.key]} size={84} stroke={9} label={`${r.label}: ${r.percent} percent`} />
                  <AppText style={[styles.ringTitle, { color: RINGS[r.key] }]}>{r.label}</AppText>
                  <AppText style={styles.ringCaption}>{r.unit === 'min' ? `${minutes(r.value)} / ${minutes(r.goal)}` : `${r.value} / ${r.goal}`}</AppText>
                </View>
              ))}
            </View>
            <AppText style={styles.goalNote}>The goals are gentle: about 10 minutes of Bible a day, an hour of listening and a few things shared each week.</AppText>

            <AppText style={styles.h}>In the app</AppText>
            <View style={styles.card}>
              <Row icon="book" color={COLORS.primary} label="Time with the Bible" value={minutes(s.totals.read_minutes)} />
              <Row icon="library" color={COLORS.primary} label="Chapters opened" value={String(s.totals.chapters)} />
              <Row icon="musical-notes" color={COLORS.success} label="Gospel radio" value={minutes(s.totals.radio_minutes)} />
              <Row icon="headset" color={COLORS.success} label="Podcasts" value={minutes(s.totals.podcast_minutes)} />
              <Row icon="bookmark" color={COLORS.info} label="Scriptures shared" value={String(s.totals.shared_scriptures)} />
              <Row icon="sparkles" color={COLORS.info} label="Inspiring messages shared" value={String(s.totals.shared_inspirations)} />
              <Row icon="calendar" color="#6B46C1" label="Days you opened the app" value={String(s.totals.opens)} />
              <Row icon="flame" color="#E8590C" label="Streak" value={`${s.streak.current} ${s.streak.current === 1 ? 'day' : 'days'} · best ${s.streak.best}`} last />
            </View>

            <AppText style={styles.h}>All together</AppText>
            <View style={styles.card}>
              <Row icon="color-wand" color="#B58A00" label="Verses highlighted" value={String(s.other.highlights)} />
              <Row icon="create" color="#B58A00" label="Notes written" value={String(s.other.notes)} />
              <Row icon="hand-left" color="#E65100" label="Prayer requests prayed for" value={String(s.other.prayers)} />
              <Row icon="game-controller" color="#5B3CC4" label="Games played" value={`${s.other.games_played}${s.other.best_score ? ` · best ${s.other.best_score}` : ''}`} />
              <Row icon="heart" color={COLORS.success} label={`Given this year (${s.other.gifts_year} ${s.other.gifts_year === 1 ? 'gift' : 'gifts'})`} value={formatMoney(s.other.given_year, s.other.currency)} />
              <Row icon="business" color={COLORS.primary} label="Churches you follow" value={String(s.other.churches)} />
              <Row icon="people" color={COLORS.info} label="Connections" value={String(s.other.connections)} last />
            </View>
            <AppText style={styles.privacy}>These figures are only for you. Nobody else sees them.</AppText>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: SOFT.surface },
  periods: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 12 },
  period: { paddingHorizontal: 14, paddingVertical: 7, borderRadius: RADIUS.full, backgroundColor: 'rgba(188,38,73,0.09)' },
  periodOn: { backgroundColor: COLORS.primary },
  periodText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  rings: { flexDirection: 'row', justifyContent: 'space-around', paddingHorizontal: SPACING.sm, marginTop: 4 },
  ring: { alignItems: 'center', gap: 6, flex: 1 },
  ringTitle: { fontFamily: FONTS.bold, fontSize: 14 },
  ringCaption: { fontFamily: FONTS.regular, fontSize: 11.5, color: SOFT.muted },
  goalNote: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: SOFT.muted, textAlign: 'center', marginHorizontal: SPACING.lg, marginTop: 14 },
  h: { fontFamily: FONTS.bold, fontSize: 18, color: SOFT.ink, paddingHorizontal: SPACING.md, marginTop: SPACING.lg, marginBottom: 10 },
  card: { marginHorizontal: SPACING.md, backgroundColor: COLORS.white, borderRadius: 24, paddingHorizontal: 14, ...SOFT_SHADOW },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  rowLine: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: 'rgba(120,80,100,0.18)' },
  rowIcon: { width: 32, height: 32, borderRadius: 11, alignItems: 'center', justifyContent: 'center' },
  rowLabel: { flex: 1, fontFamily: FONTS.medium, fontSize: 14, color: COLORS.black },
  rowValue: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary, textAlign: 'right', maxWidth: '45%' },
  privacy: { fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted, textAlign: 'center', marginTop: 18 },
});
