// Games: pick one of 25 Bible and faith quizzes and play it at your own pace (a friend who plays later also starts at the first question), share the
// game's PIN or invite the people you have played with by name, join a friend's game with their PIN, answer an invitation, jump into a game your church
// is hosting, and open the records of a quiz (today, this week, all time).
// Data: GET /api/v1/games, GET /games/library, POST /games/start, POST /games/join.
import { useCallback, useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../components/AppText';
import Button from '../components/Button';
import ChurchAvatar from '../components/ChurchAvatar';
import ScreenHeader from '../components/ScreenHeader';
import SectionHeader from '../components/SectionHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../constants/theme';
import { SOFT } from '../constants/soft';
import api, { errorMessage, fieldErrors } from '../lib/api';
import { timeAgo } from '../lib/format';
import { useFetch } from '../lib/useFetch';

const open = (id) => router.push({ pathname: '/game/[id]', params: { id: String(id) } });

function Tile({ emoji, color, size = 48 }) {
  return (
    <View style={[styles.tile, { width: size, height: size, borderRadius: size * 0.32, backgroundColor: `${color}22` }]}>
      <AppText style={{ fontSize: size * 0.5 }} allowFontScaling={false}>{emoji}</AppText>
    </View>
  );
}

export default function GamesScreen() {
  const games = useFetch('/games');
  const library = useFetch('/games/library');
  const [pin, setPin] = useState('');
  const [joining, setJoining] = useState(false);
  const [pinError, setPinError] = useState(null);
  const [category, setCategory] = useState('all');
  const [starting, setStarting] = useState(null);
  const [startError, setStartError] = useState(null);
  const g = games.data?.data;
  const lib = library.data?.data;
  const colors = useMemo(() => Object.fromEntries((lib?.categories ?? []).map((c) => [c.key, c])), [lib]);
  const shown = (lib?.quizzes ?? []).filter((q) => category === 'all' || q.category === category);

  const reloadGames = games.reload;
  useFocusEffect(
    useCallback(() => {
      reloadGames();
      const t = setInterval(reloadGames, 15000);
      return () => clearInterval(t);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  async function join() {
    if (pin.length < 4) {
      setPinError('Enter the 6-digit game PIN.');
      return;
    }
    setJoining(true);
    setPinError(null);
    try {
      const { data: res } = await api.post('/games/join', { pin });
      setPin('');
      open(res.data.id);
    } catch (e) {
      setPinError(fieldErrors(e).pin || errorMessage(e));
    } finally {
      setJoining(false);
    }
  }

  async function start(quiz) {
    if (starting) return;
    setStarting(quiz.id);
    setStartError(null);
    try {
      const { data: res } = await api.post('/games/start', { quiz_id: quiz.id });
      reloadGames();
      open(res.data.id);
    } catch (e) {
      setStartError(errorMessage(e));
    } finally {
      setStarting(null);
    }
  }

  const refresh = () => {
    games.reload();
    library.reload();
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Games" subtitle="Bible quizzes to play with friends" />
      <StateView loading={(games.loading && !g) || (library.loading && !lib)} error={!g ? games.error : null} onRetry={() => { games.retry(); library.retry(); }}>
        {g ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 48 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" refreshControl={<RefreshControl refreshing={games.refreshing} onRefresh={refresh} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <LinearGradient colors={['#7C4DFF', '#4527A0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.join}>
              <View style={styles.joinTop}>
                <Ionicons name="game-controller" size={24} color={COLORS.white} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.joinTitle}>Join a friend’s game</AppText>
                  <AppText style={styles.joinText}>Type the PIN they shared with you.</AppText>
                </View>
              </View>
              <View style={styles.pinRow}>
                <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={pin} onChangeText={(t) => setPin(t.replace(/\D/g, '').slice(0, 8))} placeholder="PIN" placeholderTextColor="rgba(255,255,255,0.55)" keyboardType="number-pad" style={styles.pinInput} maxLength={8} onSubmitEditing={join} accessibilityLabel="Game PIN" />
                <Button title="Join" variant="light" loading={joining} onPress={join} style={{ minWidth: 96 }} />
              </View>
              {pinError ? <AppText style={styles.pinError}>{pinError}</AppText> : null}
            </LinearGradient>

            {g.invites?.length ? (
              <View style={styles.section}>
                <SectionHeader title="Invitations" />
                {g.invites.map((i) => (
                  <Pressable key={i.session_id} onPress={() => open(i.session_id)} style={styles.row} accessibilityRole="button" accessibilityLabel={`Join ${i.quiz}`}>
                    <Tile emoji={i.emoji ?? '🎮'} color={COLORS.primary} />
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title} numberOfLines={1}>{i.quiz}</AppText>
                      <AppText variant="caption" numberOfLines={1}>{i.from ? `${i.from} invited you` : 'You were invited'} · {i.mode === 'self' ? 'play any time this week' : i.status === 'lobby' ? 'waiting to start' : 'in progress'}</AppText>
                    </View>
                    <View style={styles.play}><AppText style={styles.playText}>Join</AppText></View>
                  </Pressable>
                ))}
              </View>
            ) : null}

            {g.hosting?.length ? (
              <View style={styles.section}>
                <SectionHeader title="Your game" />
                {g.hosting.map((h) => (
                  <Pressable key={h.id} onPress={() => open(h.id)} style={styles.row} accessibilityRole="button" accessibilityLabel={`Open your game ${h.quiz}`}>
                    <Tile emoji={h.emoji ?? '🎮'} color="#4527A0" />
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title} numberOfLines={1}>{h.quiz}</AppText>
                      <AppText variant="caption" numberOfLines={1}>PIN {h.pin} · {h.players_count} {h.players_count === 1 ? 'player' : 'players'} · {h.mode === 'self' ? 'open for friends' : h.status === 'lobby' ? 'waiting to start' : 'in progress'}</AppText>
                    </View>
                    <View style={styles.play}><AppText style={styles.playText}>Open</AppText></View>
                  </Pressable>
                ))}
              </View>
            ) : null}

            {g.live?.length ? (
              <View style={styles.section}>
                <SectionHeader title="On now at your churches" />
                {g.live.map((s) => (
                  <Pressable key={s.id} onPress={() => open(s.id)} style={styles.row} accessibilityRole="button" accessibilityLabel={`Join ${s.quiz}`}>
                    <ChurchAvatar church={s.church} size={48} />
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title} numberOfLines={1}>{s.quiz}</AppText>
                      <AppText variant="caption" numberOfLines={1}>{s.church.name} · {s.players_count} playing · {s.status === 'lobby' ? 'starting soon' : 'in progress'}</AppText>
                    </View>
                    <View style={styles.play}><AppText style={styles.playText}>Play</AppText></View>
                  </Pressable>
                ))}
              </View>
            ) : null}

            <View style={styles.section}>
              <SectionHeader title="Play a quiz" />
              <AppText style={styles.hint}>Play at your own pace. Your best score is ranked for today, this week and all time. Share the PIN so friends play the same questions.</AppText>
              {lib ? (
                <>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
                    {[{ key: 'all', label: 'All games' }, ...lib.categories].map((c) => (
                      <Pressable key={c.key} onPress={() => setCategory(c.key)} style={[styles.chip, category === c.key && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: category === c.key }}>
                        <AppText style={[styles.chipText, category === c.key && { color: COLORS.white }]}>{c.label}</AppText>
                      </Pressable>
                    ))}
                  </ScrollView>
                  {startError ? <AppText style={styles.pinErrorDark}>{startError}</AppText> : null}
                  <View style={styles.list}>
                    {shown.map((q, i) => (
                      <View key={q.id} style={[styles.quiz, i > 0 && styles.divider, starting && starting !== q.id && { opacity: 0.55 }]}>
                        <Pressable onPress={() => start(q)} disabled={!!starting} style={styles.quizMain} accessibilityRole="button" accessibilityLabel={`Play ${q.title}`}>
                          <Tile emoji={q.emoji ?? '📖'} color={colors[q.category]?.color ?? COLORS.primary} />
                          <View style={{ flex: 1 }}>
                            <AppText style={styles.title} numberOfLines={1}>{q.title}</AppText>
                            <AppText variant="caption" numberOfLines={2}>{q.description}</AppText>
                            <AppText style={[styles.meta, { color: colors[q.category]?.color ?? COLORS.primary }]}>{q.questions} questions{q.my_best != null ? ` · your best ${q.my_best}` : q.plays ? ` · played ${q.plays}×` : ''}</AppText>
                          </View>
                        </Pressable>
                        <View style={styles.actions}>
                          <Pressable onPress={() => start(q)} disabled={!!starting} style={styles.play} accessibilityRole="button" accessibilityLabel={`Play ${q.title}`}>
                            <AppText style={styles.playText}>{starting === q.id ? '…' : 'Play'}</AppText>
                          </Pressable>
                          <Pressable onPress={() => router.push({ pathname: '/leaderboard/[quiz]', params: { quiz: String(q.id) } })} hitSlop={6} style={styles.records} accessibilityRole="button" accessibilityLabel={`Records of ${q.title}`}>
                            <Ionicons name="trophy-outline" size={14} color="#C25B00" />
                            <AppText style={styles.recordsText}>Records</AppText>
                          </Pressable>
                        </View>
                      </View>
                    ))}
                  </View>
                </>
              ) : library.error ? (
                <AppText style={styles.hint}>{library.error}</AppText>
              ) : null}
            </View>

            {g.mine?.length ? (
              <View style={styles.section}>
                <SectionHeader title="Your scores" />
                {g.mine.map((m) => (
                  <Pressable key={`${m.session_id}`} onPress={() => (m.quiz_id ? router.push({ pathname: '/leaderboard/[quiz]', params: { quiz: String(m.quiz_id) } }) : open(m.session_id))} style={styles.row} accessibilityRole="button" accessibilityLabel={`${m.quiz}: ${m.score} points`}>
                    <View style={styles.trophy}>
                      <Ionicons name="trophy-outline" size={20} color="#C25B00" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title}>{m.quiz}</AppText>
                      <AppText variant="caption">{m.finished === false ? 'Not finished' : `${m.correct} correct`} · {timeAgo(m.at)}</AppText>
                    </View>
                    <AppText style={styles.score}>{m.score}</AppText>
                  </Pressable>
                ))}
              </View>
            ) : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: SOFT.surface },
  section: { marginTop: SPACING.lg },
  join: { marginHorizontal: SPACING.md, borderRadius: RADIUS.xl, padding: SPACING.md + 2, gap: 4 },
  joinTop: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  joinTitle: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.white },
  joinText: { fontFamily: FONTS.regular, fontSize: 13, color: 'rgba(255,255,255,0.85)' },
  pinRow: { flexDirection: 'row', gap: 10, marginTop: 12, alignItems: 'center' },
  pinInput: { flex: 1, minWidth: 0, height: 50, borderRadius: RADIUS.lg, backgroundColor: 'rgba(255,255,255,0.18)', color: COLORS.white, fontFamily: FONTS.bold, fontSize: 22, letterSpacing: 6, textAlign: 'center' },
  pinError: { color: '#FFD7E5', fontFamily: FONTS.semibold, fontSize: 13, marginTop: 8 },
  pinErrorDark: { color: COLORS.danger, fontFamily: FONTS.semibold, fontSize: 13, marginHorizontal: SPACING.md, marginBottom: 8 },
  hint: { fontFamily: FONTS.regular, fontSize: 13.5, color: SOFT.muted, marginHorizontal: SPACING.md, marginTop: -4, marginBottom: 10 },
  chips: { paddingHorizontal: SPACING.md, gap: 8, paddingBottom: 12 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  list: { marginHorizontal: SPACING.md, backgroundColor: COLORS.white, borderRadius: 24, paddingHorizontal: 14, ...SHADOW, shadowOpacity: 0.05 },
  quiz: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  divider: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.gray300 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW, shadowOpacity: 0.05 },
  tile: { alignItems: 'center', justifyContent: 'center' },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  meta: { fontFamily: FONTS.semibold, fontSize: 12, marginTop: 3 },
  play: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 8 },
  playText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.white },
  quizMain: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12, minWidth: 0 },
  actions: { alignItems: 'center', gap: 8 },
  records: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingVertical: 2 },
  recordsText: { fontFamily: FONTS.semibold, fontSize: 12, color: '#C25B00' },
  trophy: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#FFF3E0', alignItems: 'center', justifyContent: 'center' },
  score: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.primary },
});
