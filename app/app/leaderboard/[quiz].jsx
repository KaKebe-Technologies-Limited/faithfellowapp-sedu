// The records of one quiz: everyone who finished it, ranked by their best run, for today, this week or all time (or only people from your
// churches). People show by the name they like to be called (set in Edit profile). Play it from here to get on the board.
// Data: GET /api/v1/games/records?quiz_id=&period=today|week|all&scope=everyone|church, POST /games/start.
import { useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import api, { errorMessage } from '../../lib/api';
import { timeAgo } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

const PERIODS = [
  { key: 'today', label: 'Today', empty: 'Nobody has finished this quiz today yet. Be the first!' },
  { key: 'week', label: 'This week', empty: 'Nobody has finished this quiz this week yet. Be the first!' },
  { key: 'all', label: 'All time', empty: 'Nobody has finished this quiz yet. Be the first!' },
];
const MEDALS = ['#FFD36B', '#C9D1D9', '#E39B6B'];

export default function LeaderboardScreen() {
  const { quiz } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [period, setPeriod] = useState('week');
  const [scope, setScope] = useState('everyone');
  const [starting, setStarting] = useState(false);
  const records = useFetch('/games/records', { params: { quiz_id: quiz, period, scope } });
  const r = records.data?.data;
  const text = PERIODS.find((p) => p.key === period);

  async function play() {
    setStarting(true);
    try {
      const { data } = await api.post('/games/start', { quiz_id: Number(quiz) });
      router.push({ pathname: '/game/[id]', params: { id: String(data.data.id) } });
    } catch (e) {
      Alert.alert('Could not start', errorMessage(e));
    } finally {
      setStarting(false);
    }
  }

  const top = (r?.rows ?? []).slice(0, 3);
  const rest = (r?.rows ?? []).slice(3);

  return (
    <View style={styles.screen}>
      <ScreenHeader title={r ? `${r.quiz.emoji ?? '🏆'} ${r.quiz.title}` : 'Records'} subtitle="Best run of each player" />
      <View style={styles.tabs}>
        {PERIODS.map((p) => (
          <Pressable key={p.key} onPress={() => setPeriod(p.key)} style={[styles.tab, period === p.key && styles.tabOn]} accessibilityRole="button" accessibilityState={{ selected: period === p.key }}>
            <AppText style={[styles.tabText, period === p.key && { color: COLORS.white }]}>{p.label}</AppText>
          </Pressable>
        ))}
      </View>
      <View style={styles.scopes}>
        {[['everyone', 'Everyone'], ['church', 'My churches']].map(([key, label]) => (
          <Pressable key={key} onPress={() => setScope(key)} style={[styles.scope, scope === key && styles.scopeOn]} accessibilityRole="button" accessibilityState={{ selected: scope === key }}>
            <AppText style={[styles.scopeText, scope === key && { color: COLORS.primary }]}>{label}</AppText>
          </Pressable>
        ))}
      </View>

      <StateView loading={records.loading && !r} error={!r ? records.error : null} onRetry={records.retry}>
        {r ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 120 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={records.refreshing} onRefresh={records.reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            {r.rows.length === 0 ? (
              <View style={styles.empty}>
                <View style={styles.emptyIcon}>
                  <Ionicons name="trophy-outline" size={34} color="#C25B00" />
                </View>
                <AppText style={styles.emptyTitle}>No records yet</AppText>
                <AppText style={styles.emptyText}>{scope === 'church' ? 'Nobody from your churches has finished this quiz in this time.' : text.empty}</AppText>
              </View>
            ) : (
              <>
                <LinearGradient colors={['#2A1170', '#4527A0', '#7C4DFF']} style={styles.podiumCard}>
                  <AppText style={styles.podiumHead}>
                    {r.players} {r.players === 1 ? 'player' : 'players'} · {r.plays} {r.plays === 1 ? 'run' : 'runs'}
                  </AppText>
                  <View style={styles.podium}>
                    {[1, 0, 2].map((idx) => {
                      const row = top[idx];
                      if (!row) return <View key={idx} style={{ flex: 1 }} />;
                      return (
                        <View key={idx} style={{ flex: 1, alignItems: 'center', justifyContent: 'flex-end' }}>
                          {idx === 0 ? <AppText style={{ fontSize: 22 }}>👑</AppText> : null}
                          <AppText style={[styles.podName, row.me && { color: '#FFD36B' }]} numberOfLines={1}>{row.me ? `${row.name} (you)` : row.name}</AppText>
                          <AppText style={styles.podScore}>{row.score}</AppText>
                          <View style={[styles.pod, { height: [110, 84, 64][idx], backgroundColor: MEDALS[idx] }]}>
                            <AppText style={styles.podRank}>{row.rank}</AppText>
                          </View>
                        </View>
                      );
                    })}
                  </View>
                </LinearGradient>

                <View style={styles.list}>
                  {rest.map((row, i) => (
                    <View key={`${row.rank}-${row.name}`} style={[styles.row, i > 0 && styles.divider, row.me && styles.rowMe]}>
                      <AppText style={styles.rank}>{row.rank}</AppText>
                      <View style={{ flex: 1 }}>
                        <AppText style={styles.name} numberOfLines={1}>{row.me ? `${row.name} (you)` : row.name}</AppText>
                        <AppText variant="caption" numberOfLines={1}>{row.correct}/{row.total} right · {row.seconds}s{row.plays > 1 ? ` · ${row.plays} runs` : ''} · {timeAgo(row.at)}</AppText>
                      </View>
                      <AppText style={styles.score}>{row.score}</AppText>
                    </View>
                  ))}
                </View>

                {r.me && r.me.rank > 50 ? (
                  <View style={[styles.row, styles.rowMe, { marginHorizontal: SPACING.md, marginTop: 10, borderRadius: RADIUS.lg }]}>
                    <AppText style={styles.rank}>{r.me.rank}</AppText>
                    <AppText style={[styles.name, { flex: 1 }]}>You</AppText>
                    <AppText style={styles.score}>{r.me.score}</AppText>
                  </View>
                ) : null}
              </>
            )}
            <AppText style={styles.note}>Each person counts once, with their best finished run. Faster correct answers score more.</AppText>
          </ScrollView>
        ) : null}
      </StateView>

      <View style={[styles.bar, { paddingBottom: insets.bottom + 12 }]}>
        {r?.me ? (
          <View style={{ flex: 1 }}>
            <AppText style={styles.barTitle}>You are #{r.me.rank}</AppText>
            <AppText variant="caption">{r.me.score} points · {r.me.correct}/{r.me.total} right</AppText>
          </View>
        ) : (
          <View style={{ flex: 1 }}>
            <AppText style={styles.barTitle}>Not on this board yet</AppText>
            <AppText variant="caption">Finish the quiz to get on it.</AppText>
          </View>
        )}
        <Button title={r?.me ? 'Play again' : 'Play'} loading={starting} onPress={play} style={{ minWidth: 130 }} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: SOFT.surface },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingVertical: 8 },
  tab: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  tabOn: { backgroundColor: COLORS.primary },
  tabText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  scopes: { flexDirection: 'row', gap: 18, paddingHorizontal: SPACING.md + 4, paddingBottom: 8 },
  scope: { paddingVertical: 6, borderBottomWidth: 2, borderBottomColor: 'transparent' },
  scopeOn: { borderBottomColor: COLORS.primary },
  scopeText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: SOFT.muted },
  podiumCard: { marginHorizontal: SPACING.md, marginTop: 4, borderRadius: RADIUS.xl, padding: SPACING.md, paddingBottom: 0, overflow: 'hidden' },
  podiumHead: { fontFamily: FONTS.semibold, fontSize: 13, color: 'rgba(255,255,255,0.85)', textAlign: 'center', marginBottom: 6 },
  podium: { flexDirection: 'row', alignItems: 'flex-end', gap: 8 },
  pod: { width: '100%', borderTopLeftRadius: 12, borderTopRightRadius: 12, alignItems: 'center', paddingTop: 8 },
  podRank: { fontFamily: FONTS.bold, fontSize: 24, color: '#2B1B22' },
  podName: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.white, maxWidth: '100%' },
  podScore: { fontFamily: FONTS.semibold, fontSize: 13, color: '#FFD36B', marginBottom: 4 },
  list: { marginHorizontal: SPACING.md, marginTop: 12, backgroundColor: COLORS.white, borderRadius: 24, overflow: 'hidden', ...SHADOW, shadowOpacity: 0.05 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 16, backgroundColor: COLORS.white },
  rowMe: { backgroundColor: '#FFF6D9' },
  divider: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.gray300 },
  rank: { width: 28, fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary },
  name: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  score: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.primary },
  note: { fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted, textAlign: 'center', marginHorizontal: SPACING.lg, marginTop: 16 },
  empty: { alignItems: 'center', paddingHorizontal: SPACING.xl, paddingTop: 48, gap: 8 },
  emptyIcon: { width: 72, height: 72, borderRadius: 36, backgroundColor: '#FFF3E0', alignItems: 'center', justifyContent: 'center' },
  emptyTitle: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black },
  emptyText: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: SOFT.muted, textAlign: 'center' },
  bar: { position: 'absolute', left: 0, right: 0, bottom: 0, flexDirection: 'row', alignItems: 'center', gap: 12, padding: SPACING.md, backgroundColor: COLORS.white, borderTopLeftRadius: 24, borderTopRightRadius: 24, ...SHADOW, shadowOpacity: 0.1 },
  barTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
});
