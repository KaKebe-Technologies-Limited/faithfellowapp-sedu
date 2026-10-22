// Running a quiz from the phone: the PIN in big letters for the room, how many have joined, and the controls —
// Start, Show answer, Next question, End. Once started the game moves along by itself (the platform admin sets the seconds per question, then a short
// reveal), so you can also just watch the live scores. The finale sounds when the game is over. Data: /api/v1/admin/{church}/quiz-sessions/{id}.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../../../components/AppText';
import Button from '../../../../components/Button';
import { BackButton } from '../../../../components/ScreenHeader';
import StateView from '../../../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../../../constants/theme';
import { adminApi } from '../../../../lib/admin';
import { errorMessage } from '../../../../lib/api';
import { playGameSound, useGameSound } from '../../../../lib/gameSounds';

const LETTERS = ['A', 'B', 'C', 'D'];

export default function HostGame() {
  const { slug, id } = useLocalSearchParams();
  const admin = adminApi(slug);
  const insets = useSafeAreaInsets();
  useGameSound(); // remembers whether the person wants sounds, and lets the sound players go with the screen
  const [state, setState] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const poll = useCallback(async () => {
    try {
      const { data } = await admin.get(`/quiz-sessions/${id}`);
      setState(data.data);
      setError(null);
    } catch (e) {
      setError(errorMessage(e));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, slug]);

  useEffect(() => {
    poll();
    const t = setInterval(() => poll(), 1000);
    return () => clearInterval(t);
  }, [poll]);

  async function act(action) {
    setBusy(true);
    try {
      const { data } = await admin.post(`/quiz-sessions/${id}/${action}`);
      setState(data.data);
    } catch (e) {
      Alert.alert('Could not do that', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function end() {
    const run = () => act(state?.status === 'lobby' ? 'cancel' : 'end');
    if (Platform.OS === 'web') return run();
    Alert.alert(state?.status === 'lobby' ? 'Cancel this game?' : 'End the game now?', undefined, [{ text: 'Keep going', style: 'cancel' }, { text: state?.status === 'lobby' ? 'Cancel game' : 'End game', style: 'destructive', onPress: run }]);
  }

  const q = state?.question;
  const over = state?.status === 'finished';
  const ran = useRef(false); // the game was seen running: the finale is for a game that just ended, not one opened afterwards
  useEffect(() => {
    if (state && !over) ran.current = true;
    if (over && ran.current) {
      ran.current = false;
      playGameSound('done');
    }
  }, [over, state]);

  return (
    <View style={[styles.screen, { paddingTop: insets.top + 8, paddingBottom: insets.bottom + 12 }]}>
      <LinearGradient colors={['#2A1170', '#4527A0', '#7C4DFF']} style={StyleSheet.absoluteFill} />
      <View style={styles.top}>
        <BackButton onDark />
        <AppText style={styles.quiz} numberOfLines={1}>{state?.quiz ?? 'Quiz'}</AppText>
      </View>
      <StateView loading={!state && !error} error={!state ? error : null} onRetry={poll}>
        {state ? (
          <ScrollView contentContainerStyle={{ gap: 14, paddingTop: 14, paddingBottom: 20 }} showsVerticalScrollIndicator={false}>
            <View style={styles.pinCard}>
              <AppText style={styles.pinLabel}>Join with PIN</AppText>
              <AppText style={styles.pin}>{state.pin}</AppText>
              <AppText style={styles.pinSub}>{state.players_count} {state.players_count === 1 ? 'player' : 'players'} · {over ? 'finished' : state.status === 'lobby' ? 'waiting to start' : `question ${state.index + 1} of ${state.total}`}</AppText>
            </View>

            {state.status === 'lobby' ? (
              <>
                <View style={styles.names}>
                  {state.players.slice(0, 40).map((n, i) => (
                    <View key={`${n}${i}`} style={styles.chip}>
                      <AppText style={styles.chipText}>{n}</AppText>
                    </View>
                  ))}
                </View>
                <Button title="Start the game" variant="light" onPress={() => act('start')} loading={busy} disabled={state.players_count === 0} />
                {state.players_count === 0 ? <AppText style={styles.hint}>Wait for at least one player to join.</AppText> : null}
              </>
            ) : null}

            {(state.status === 'question' || state.status === 'reveal') && q ? (
              <>
                <View style={styles.qCard}>
                  <AppText style={styles.qText}>{q.text}</AppText>
                  <AppText style={styles.hint2}>{state.status === 'question' ? `${Math.ceil(q.remaining_ms / 1000)}s left · ${q.answered_count} answered` : 'Answer shown'}</AppText>
                </View>
                {q.options.map((o, i) => {
                  const right = state.reveal?.correct === i;
                  return (
                    <View key={i} style={[styles.opt, right && styles.optRight]}>
                      <AppText style={styles.letter}>{LETTERS[i]}</AppText>
                      <AppText style={styles.optText}>{o}</AppText>
                      <AppText style={styles.count}>{state.reveal?.counts?.[i] ?? 0}</AppText>
                    </View>
                  );
                })}
                <View style={{ flexDirection: 'row', gap: 10 }}>
                  {state.status === 'question' ? <Button title="Show the answer" variant="light" onPress={() => act('reveal')} loading={busy} style={{ flex: 1 }} /> : <Button title={state.is_last ? 'Finish' : 'Next question'} variant="light" onPress={() => act('next')} loading={busy} style={{ flex: 1 }} />}
                </View>
              </>
            ) : null}

            {state.leaderboard.length ? (
              <View style={styles.board}>
                <AppText style={styles.boardTitle}>{over ? 'Final scores' : 'Scores'}</AppText>
                {state.leaderboard.slice(0, 10).map((r) => (
                  <View key={r.id} style={styles.row}>
                    <AppText style={styles.rank}>{r.rank}</AppText>
                    <AppText style={styles.rowName} numberOfLines={1}>{r.name}</AppText>
                    {r.gained > 0 ? <AppText style={styles.gained}>+{r.gained}</AppText> : null}
                    <AppText style={styles.score}>{r.score}</AppText>
                  </View>
                ))}
              </View>
            ) : null}

            {over ? <Button title="Back to quizzes" variant="light" onPress={() => router.back()} /> : <Button title={state.status === 'lobby' ? 'Cancel the game' : 'End the game'} variant="outline" onPress={end} style={{ borderColor: 'rgba(255,255,255,0.6)' }} />}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#4527A0', paddingHorizontal: SPACING.md },
  top: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  quiz: { flex: 1, fontFamily: FONTS.bold, fontSize: 17, color: COLORS.white },
  pinCard: { backgroundColor: 'rgba(255,255,255,0.14)', borderRadius: RADIUS.xl - 4, padding: SPACING.md, alignItems: 'center', gap: 2 },
  pinLabel: { fontFamily: FONTS.medium, fontSize: 13, color: 'rgba(255,255,255,0.85)' },
  pin: { fontFamily: FONTS.bold, fontSize: 46, letterSpacing: 8, color: '#FFD36B' },
  pinSub: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.white },
  names: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, justifyContent: 'center' },
  chip: { backgroundColor: 'rgba(255,255,255,0.16)', borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6 },
  chipText: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.white },
  hint: { fontFamily: FONTS.regular, fontSize: 13, color: 'rgba(255,255,255,0.8)', textAlign: 'center' },
  hint2: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary, textAlign: 'center', marginTop: 6 },
  qCard: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.md },
  qText: { fontFamily: FONTS.bold, fontSize: 19, lineHeight: 26, color: COLORS.black, textAlign: 'center' },
  opt: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: 'rgba(255,255,255,0.14)', borderRadius: RADIUS.lg, padding: 13 },
  optRight: { backgroundColor: '#1E9E5A' },
  letter: { width: 26, fontFamily: FONTS.bold, fontSize: 16, color: COLORS.white },
  optText: { flex: 1, fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.white },
  count: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.white },
  board: { backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: RADIUS.lg, padding: 12, gap: 6 },
  boardTitle: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.white, marginBottom: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.white, borderRadius: RADIUS.md, paddingHorizontal: 12, paddingVertical: 8 },
  rank: { width: 22, fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  rowName: { flex: 1, fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.black },
  gained: { fontFamily: FONTS.bold, fontSize: 13, color: '#1E9E5A' },
  score: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black, minWidth: 44, textAlign: 'right' },
});
