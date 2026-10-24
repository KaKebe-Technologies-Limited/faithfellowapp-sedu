// Playing a quiz on your phone. The screen asks the server for the game's state about once a second:
// the lobby (the host shares the PIN and invites people they have played with by name), each question with four big answers
// (the time per question is set by the platform admin and comes with the game's state), the reveal (right answer, points, how many chose each option)
// and the final results. A chime sounds for a right answer, a soft low note for a wrong one (or time running out) and a finale when the game is over;
// the speaker button at the top turns the sounds off.
// Two kinds of game: a church's live game, where the host moves everyone along together, and the games members start (state.mode === 'self'), where
// everyone plays at their own pace: you start at the first question whenever you join, answering shows the result at once and moves on after a moment
// (or when you press Next), and a finished run counts in the quiz's records (today, this week, all time).
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import { BackButton } from '../../components/ScreenHeader';
import Sheet from '../../components/Sheet';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { playGameSound, useGameSound } from '../../lib/gameSounds';

const OPTION_COLORS = ['#E21B3C', '#1368CE', '#D89E00', '#26890C'];
const LETTERS = ['A', 'B', 'C', 'D'];

function Board({ rows, limit = 5, light = false, progress = false, total = 0 }) {
  return (
    <View style={{ gap: 6 }}>
      {rows.slice(0, limit).map((r) => (
        <View key={r.id} style={[styles.boardRow, r.me && styles.boardMe, light && { backgroundColor: 'rgba(255,255,255,0.14)' }]}>
          <AppText style={[styles.rank, light && { color: COLORS.onColor }]}>{r.rank}</AppText>
          <AppText style={[styles.boardName, light && { color: COLORS.onColor }]} numberOfLines={1}>
            {r.me ? `${r.name} (you)` : r.name}
          </AppText>
          {progress ? <AppText style={styles.progressChip}>{r.done ? 'Done ✓' : r.at ? `Q${r.at}/${total}` : 'Waiting'}</AppText> : null}
          {r.gained > 0 ? <AppText style={styles.gained}>+{r.gained}</AppText> : null}
          <AppText style={[styles.boardScore, light && { color: COLORS.onColor }]}>{r.score}</AppText>
        </View>
      ))}
    </View>
  );
}

export default function GameScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [state, setState] = useState(null);
  const [error, setError] = useState(null);
  const [picked, setPicked] = useState({}); // question index → option chosen
  const [left, setLeft] = useState(0);
  const received = useRef(0);
  const finished = useRef(false);
  const slow = useRef(false); // your own run is over but friends may still be playing: the board is refreshed every few seconds, not every second
  const tick = useRef(0);
  const acted = useRef(0); // when the last answer, Next or Start came back
  const [people, setPeople] = useState(null); // the invite sheet: null = closed, otherwise the list
  const [busy, setBusy] = useState(false);
  const sound = useGameSound();
  const sounded = useRef(''); // which moment already made its sound, so a poll does not repeat it
  const playedLive = useRef(false); // the game was seen running, so the finale is for a game that just ended, not for one opened afterwards to read the results

  const poll = useCallback(async () => {
    const asked = Date.now();
    try {
      const { data } = await api.get(`/games/${id}`);
      if (asked < acted.current) return; // you answered or pressed Next while this was on its way: it is older than what the screen shows
      received.current = Date.now();
      setState(data.data);
      setError(null);
      const self = data.data.mode === 'self';
      finished.current = data.data.status === 'cancelled' || (data.data.status === 'finished' && !self);
      slow.current = data.data.status === 'finished' && self;
    } catch (e) {
      setError(errorMessage(e));
    }
  }, [id]);

  useEffect(() => {
    poll();
    const t = setInterval(() => {
      tick.current += 1;
      if (!finished.current && (!slow.current || tick.current % 5 === 0)) poll();
    }, 1000);
    return () => clearInterval(t);
  }, [poll]);

  // The sounds: once per revealed question (right or wrong, and time running out counts as wrong) and once when the game is over.
  useEffect(() => {
    if (!state) return;
    if (['lobby', 'question', 'reveal'].includes(state.status)) playedLive.current = true;
    if (state.status === 'reveal' && state.me) {
      const moment = `reveal-${state.index}`;
      if (sounded.current !== moment) {
        sounded.current = moment;
        playGameSound(state.me.correct ? 'correct' : 'wrong');
      }
    } else if (state.status === 'finished' && playedLive.current && sounded.current !== 'done') {
      sounded.current = 'done';
      playGameSound('done');
    }
  }, [state]);

  // A smooth countdown between polls.
  useEffect(() => {
    const t = setInterval(() => {
      const passed = Date.now() - received.current;
      if (state?.status === 'question' && state.question) setLeft(Math.max(0, state.question.remaining_ms - passed));
      else if (state?.status === 'reveal' && state.mode === 'self') setLeft(Math.max(0, (state.next_in_ms ?? 0) - passed));
      else setLeft(0);
    }, 100);
    return () => clearInterval(t);
  }, [state]);

  async function answer(option) {
    if (!state || picked[state.index] !== undefined || state.status !== 'question') return;
    setPicked((p) => ({ ...p, [state.index]: option }));
    try {
      const { data } = await api.post(`/games/${id}/answer`, { option, question: state.index });
      // In your own run the result comes back with the answer, so it shows at once, without waiting for anybody.
      if (data.data) {
        received.current = acted.current = Date.now();
        setState(data.data);
      }
    } catch (e) {
      Alert.alert('Not counted', errorMessage(e));
      poll();
    }
  }

  // "Next": leave the answer screen and go on to the next question (or to the results after the last one).
  async function next() {
    if (busy) return;
    setBusy(true);
    try {
      const { data } = await api.post(`/games/${id}/next`);
      received.current = acted.current = Date.now();
      setState(data.data);
    } catch (e) {
      Alert.alert('Could not go on', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  // Another go at the same quiz: a new game, so it starts at the first question and is a new run for the records.
  async function playAgain() {
    setBusy(true);
    try {
      const { data } = await api.post('/games/start', { quiz_id: state.quiz_id });
      router.replace(`/game/${data.data.id}`);
    } catch (e) {
      Alert.alert('Could not start', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function share() {
    try {
      await Share.share({
        message: self
          ? `Can you beat me at “${state.quiz}” on FaithFellows? Open the app → Games and pick the quiz, or type the PIN ${state.pin}. We will all be ranked in the records.`
          : `Join my FaithFellows game “${state.quiz}”! Open the FaithFellows app → Games → type the PIN ${state.pin}.`,
      });
    } catch {
      // the share sheet was closed
    }
  }

  async function begin() {
    setBusy(true);
    try {
      const { data } = await api.post(`/games/${id}/begin`);
      received.current = acted.current = Date.now();
      setState(data.data);
    } catch (e) {
      Alert.alert('Could not start', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function cancelGame() {
    Alert.alert(self ? 'Close this game?' : 'Cancel this game?', self ? 'Friends will no longer be able to join with the PIN. Runs already finished still count in the records.' : 'Everyone waiting will be taken out of it.', [
      { text: 'Keep it', style: 'cancel' },
      {
        text: 'Cancel game',
        style: 'destructive',
        onPress: async () => {
          try {
            await api.post(`/games/${id}/cancel`);
            router.replace('/games');
          } catch (e) {
            Alert.alert('Could not cancel', errorMessage(e));
          }
        },
      },
    ]);
  }

  async function openPeople() {
    setPeople([]);
    try {
      const { data } = await api.get('/games/friends', { params: { session: id } });
      setPeople(data.data);
    } catch (e) {
      setPeople(null);
      Alert.alert('Could not load your people', errorMessage(e));
    }
  }

  async function invite(person) {
    setPeople((list) => list.map((p) => (p.id === person.id ? { ...p, invited: true } : p)));
    try {
      await api.post(`/games/${id}/invite`, { user_ids: [person.id] });
    } catch (e) {
      setPeople((list) => list.map((p) => (p.id === person.id ? { ...p, invited: false } : p)));
      Alert.alert('Not invited', errorMessage(e));
    }
  }

  const q = state?.question;
  const self = state?.mode === 'self';
  const mine = state?.index != null ? picked[state.index] ?? state?.me?.answer ?? null : null;
  const fraction = q ? Math.min(1, left / (q.time_limit * 1000)) : 0;

  return (
    <View style={[styles.screen, { paddingTop: insets.top + 8, paddingBottom: insets.bottom + 12 }]}>
      <LinearGradient colors={['#2A1170', '#4527A0', '#7C4DFF']} style={StyleSheet.absoluteFill} />
      <View style={styles.top}>
        <BackButton onDark />
        <View style={{ flex: 1 }}>
          <AppText style={styles.quiz} numberOfLines={1}>
            {state?.quiz ?? 'Quiz'}
          </AppText>
          {state?.status === 'question' || state?.status === 'reveal' ? <AppText style={styles.progress}>Question {state.index + 1} of {state.total}</AppText> : null}
        </View>
        <Pressable onPress={sound.toggle} hitSlop={8} style={styles.speaker} accessibilityRole="button" accessibilityLabel={sound.on ? 'Turn the game sounds off' : 'Turn the game sounds on'}>
          <Ionicons name={sound.on ? 'volume-high' : 'volume-mute'} size={20} color={COLORS.onColor} />
        </Pressable>
        {state?.me ? (
          <View style={styles.scorePill}>
            <Ionicons name="star" size={14} color="#FFD36B" />
            <AppText style={styles.scoreText}>{state.me.score}</AppText>
          </View>
        ) : null}
      </View>

      <StateView loading={!state && !error} error={!state ? error : null} onRetry={poll}>
        {state ? (
          <ScrollView contentContainerStyle={{ flexGrow: 1, paddingTop: 12 }} showsVerticalScrollIndicator={false}>
            {state.status === 'lobby' ? (
              <View style={styles.center}>
                <View style={styles.pulse}>
                  <Ionicons name={state.is_host ? 'people' : 'hourglass-outline'} size={42} color={COLORS.onColor} />
                </View>
                <AppText style={styles.big}>{self ? 'Ready to play?' : state.is_host ? 'Your game is ready' : 'You are in!'}</AppText>
                <AppText style={styles.sub}>
                  {self
                    ? `${state.total} questions, ${state.timing?.question ?? 15} seconds each. You start at question 1, and friends can join with the PIN and play any time this week.`
                    : state.is_host
                      ? 'Share the PIN or invite friends, then press Start.'
                      : `Waiting for ${state.host_name ?? 'the host'} to start the game…`}
                </AppText>
                <AppText style={styles.pin}>PIN {state.pin}</AppText>
                <AppText style={styles.sub}>{state.players_count} {state.players_count === 1 ? 'player' : 'players'} so far</AppText>
                <View style={styles.names}>
                  {state.players.slice(0, 24).map((n, i) => (
                    <View key={`${n}${i}`} style={styles.nameChip}>
                      <AppText style={styles.nameText}>{n}</AppText>
                    </View>
                  ))}
                </View>
                {state.is_host || self ? (
                  <View style={styles.hostBox}>
                    {state.is_host ? (
                      <View style={styles.hostRow}>
                        <Pressable onPress={share} style={styles.ghost} accessibilityRole="button" accessibilityLabel="Share the game PIN">
                          <Ionicons name="share-social-outline" size={18} color={COLORS.onColor} />
                          <AppText style={styles.ghostText}>Share PIN</AppText>
                        </Pressable>
                        <Pressable onPress={openPeople} style={styles.ghost} accessibilityRole="button" accessibilityLabel="Invite people you have played with">
                          <Ionicons name="person-add-outline" size={18} color={COLORS.onColor} />
                          <AppText style={styles.ghostText}>Invite people</AppText>
                        </Pressable>
                      </View>
                    ) : null}
                    {self ? (
                      <Button title="Start playing" variant="light" loading={busy} onPress={begin} />
                    ) : (
                      <Button title={state.players_count > 1 ? `Start game · ${state.players_count} players` : 'Start game'} variant="light" loading={busy} onPress={begin} />
                    )}
                    {state.is_host ? (
                      <Pressable onPress={cancelGame} accessibilityRole="button" style={{ alignSelf: 'center', padding: 8 }}>
                        <AppText style={styles.cancel}>{self ? 'Close this game' : 'Cancel game'}</AppText>
                      </Pressable>
                    ) : null}
                  </View>
                ) : null}
              </View>
            ) : null}

            {state.status === 'question' && q ? (
              <View style={{ gap: 14 }}>
                <View style={styles.timer}>
                  <View style={[styles.timerFill, { width: `${fraction * 100}%`, backgroundColor: fraction < 0.3 ? '#FF5A5A' : '#FFD36B' }]} />
                </View>
                <View style={styles.qCard}>
                  <AppText style={styles.qText}>{q.text}</AppText>
                  <AppText style={styles.secs}>{Math.ceil(left / 1000)}s</AppText>
                </View>
                <View style={styles.options}>
                  {q.options.map((opt, i) => {
                    const chosen = mine === i;
                    return (
                      <Pressable key={i} disabled={mine !== null} onPress={() => answer(i)} style={[styles.option, { backgroundColor: OPTION_COLORS[i] }, mine !== null && !chosen && { opacity: 0.4 }, chosen && styles.optionChosen]} accessibilityRole="button" accessibilityLabel={`Answer ${LETTERS[i]}: ${opt}`}>
                        <View style={styles.letter}>
                          <AppText style={styles.letterText}>{LETTERS[i]}</AppText>
                        </View>
                        <AppText style={styles.optText}>{opt}</AppText>
                        {chosen ? <Ionicons name="checkmark-circle" size={24} color={COLORS.onColor} /> : null}
                      </Pressable>
                    );
                  })}
                </View>
                <AppText style={styles.locked}>{mine !== null ? (self ? 'Checking…' : 'Answer locked in ✓ — watch the scores') : 'Pick your answer — faster is worth more!'}</AppText>
                <View style={styles.boardBox}>
                  <AppText style={styles.boardTitle}>{self ? 'Players' : 'Live scores'}</AppText>
                  <Board rows={state.leaderboard} light progress={self} total={state.total} />
                </View>
              </View>
            ) : null}

            {state.status === 'reveal' && q ? (
              <View style={{ gap: 14 }}>
                <View style={styles.qCard}>
                  <AppText style={styles.qText}>{q.text}</AppText>
                </View>
                <View style={[styles.result, { backgroundColor: state.me?.correct ? '#1E9E5A' : '#E21B3C' }]}>
                  <Ionicons name={state.me?.correct ? 'checkmark-circle' : 'close-circle'} size={30} color={COLORS.onColor} />
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.resultTitle}>{state.me?.answer == null ? 'Time ran out' : state.me.correct ? 'Correct!' : 'Not this time'}</AppText>
                    {state.me?.points ? <AppText style={styles.resultSub}>+{state.me.points} points</AppText> : null}
                  </View>
                  {state.me?.streak > 1 && state.me.correct ? <AppText style={styles.resultSub}>🔥 {state.me.streak} in a row</AppText> : null}
                </View>
                {self ? <Button title={state.is_last ? 'See my results' : `Next question · ${Math.ceil(left / 1000)}s`} variant="light" loading={busy} onPress={next} /> : null}
                <View style={styles.options}>
                  {q.options.map((opt, i) => {
                    const right = state.reveal?.correct === i;
                    return (
                      <View key={i} style={[styles.option, { backgroundColor: OPTION_COLORS[i] }, !right && { opacity: 0.35 }, right && styles.optionChosen]}>
                        <View style={styles.letter}>
                          <AppText style={styles.letterText}>{LETTERS[i]}</AppText>
                        </View>
                        <AppText style={styles.optText}>{opt}</AppText>
                        <AppText style={styles.count}>{state.reveal?.counts?.[i] ?? 0}</AppText>
                        {right ? <Ionicons name="checkmark-circle" size={24} color={COLORS.onColor} /> : null}
                      </View>
                    );
                  })}
                </View>
                <View style={styles.boardBox}>
                  <AppText style={styles.boardTitle}>Scoreboard{state.me ? ` · you are #${state.me.rank}` : ''}</AppText>
                  <Board rows={state.leaderboard} limit={8} light progress={self} total={state.total} />
                </View>
                {!self ? <AppText style={styles.locked}>{state.is_last ? 'Final results next…' : `Next question in ${Math.ceil((state.next_in_ms ?? 0) / 1000)}s`}</AppText> : null}
              </View>
            ) : null}

            {state.status === 'cancelled' ? (
              <View style={styles.center}>
                <View style={styles.pulse}>
                  <Ionicons name="close-circle-outline" size={42} color={COLORS.onColor} />
                </View>
                <AppText style={styles.big}>This game was closed</AppText>
                <AppText style={styles.sub}>{self ? 'Its host closed it, or it was open for a week. Start your own from the games list.' : 'The host ended it. Start your own from the games list.'}</AppText>
                <Button title="Back to games" variant="light" onPress={() => router.replace('/games')} style={{ alignSelf: 'stretch', marginTop: 18 }} />
              </View>
            ) : null}

            {state.status === 'finished' && self ? (
              <View style={{ gap: 16, alignItems: 'center' }}>
                <AppText style={styles.big}>Well played 🎉</AppText>
                {state.me ? (
                  <View style={styles.myRun}>
                    <AppText style={styles.myScore}>{state.me.score}</AppText>
                    <AppText style={styles.mySub}>points</AppText>
                    <View style={styles.myStats}>
                      <View style={styles.myStat}>
                        <AppText style={styles.myStatValue}>{state.me.correct_count}/{state.total}</AppText>
                        <AppText style={styles.myStatLabel}>right</AppText>
                      </View>
                      <View style={styles.myStat}>
                        <AppText style={styles.myStatValue}>{state.me.seconds}s</AppText>
                        <AppText style={styles.myStatLabel}>total time</AppText>
                      </View>
                      <View style={styles.myStat}>
                        <AppText style={styles.myStatValue}>#{state.me.rank}</AppText>
                        <AppText style={styles.myStatLabel}>in this game</AppText>
                      </View>
                    </View>
                  </View>
                ) : null}
                <View style={[styles.boardBox, { alignSelf: 'stretch' }]}>
                  <AppText style={styles.boardTitle}>Players in this game</AppText>
                  <Board rows={state.leaderboard} limit={10} light progress total={state.total} />
                </View>
                <Button title="See the records" variant="light" onPress={() => router.push(`/leaderboard/${state.quiz_id}`)} style={{ alignSelf: 'stretch' }} />
                <View style={{ flexDirection: 'row', gap: 10, alignSelf: 'stretch' }}>
                  <Pressable onPress={playAgain} disabled={busy} style={styles.ghost} accessibilityRole="button" accessibilityLabel="Play this quiz again">
                    <Ionicons name="refresh" size={18} color={COLORS.onColor} />
                    <AppText style={styles.ghostText}>Play again</AppText>
                  </Pressable>
                  <Pressable onPress={share} style={styles.ghost} accessibilityRole="button" accessibilityLabel="Challenge friends to this quiz">
                    <Ionicons name="share-social-outline" size={18} color={COLORS.onColor} />
                    <AppText style={styles.ghostText}>Challenge</AppText>
                  </Pressable>
                  <Pressable onPress={() => router.replace('/games')} style={styles.ghost} accessibilityRole="button" accessibilityLabel="Back to games">
                    <Ionicons name="grid-outline" size={18} color={COLORS.onColor} />
                    <AppText style={styles.ghostText}>Games</AppText>
                  </Pressable>
                </View>
              </View>
            ) : null}

            {state.status === 'finished' && !self ? (
              <View style={{ gap: 16, alignItems: 'center' }}>
                <AppText style={styles.big}>Game over 🎉</AppText>
                <View style={styles.podium}>
                  {[1, 0, 2].map((idx) => {
                    const r = state.leaderboard[idx];
                    if (!r) return <View key={idx} style={{ flex: 1 }} />;
                    const height = [120, 90, 70][idx];
                    return (
                      <View key={idx} style={{ flex: 1, alignItems: 'center', justifyContent: 'flex-end' }}>
                        <AppText style={styles.podName} numberOfLines={1}>{r.name}</AppText>
                        <AppText style={styles.podScore}>{r.score}</AppText>
                        <View style={[styles.pod, { height, backgroundColor: ['#FFD36B', '#C9D1D9', '#E39B6B'][idx] }]}>
                          <AppText style={styles.podRank}>{r.rank}</AppText>
                        </View>
                      </View>
                    );
                  })}
                </View>
                {state.me ? (
                  <View style={styles.final}>
                    <AppText style={styles.finalText}>You finished #{state.me.rank} with {state.me.score} points</AppText>
                  </View>
                ) : null}
                <View style={[styles.boardBox, { alignSelf: 'stretch' }]}>
                  <Board rows={state.leaderboard} limit={10} light />
                </View>
                <Button title="Back to games" variant="light" onPress={() => router.replace('/games')} style={{ alignSelf: 'stretch' }} />
              </View>
            ) : null}
          </ScrollView>
        ) : null}
      </StateView>

      <Sheet visible={people !== null} onClose={() => setPeople(null)} title="Invite people">
        {people && people.length === 0 ? (
          <AppText style={styles.empty}>Nobody yet. People you play games with, and your connections, show up here so you can invite them with one tap. Share the PIN for now.</AppText>
        ) : null}
        {(people ?? []).map((p) => (
          <View key={p.id} style={styles.person}>
            <Avatar person={p} size={42} />
            <View style={{ flex: 1 }}>
              <AppText style={styles.personName} numberOfLines={1}>{p.name}</AppText>
              {p.plays ? <AppText variant="caption">Played together {p.plays} {p.plays === 1 ? 'time' : 'times'}</AppText> : <AppText variant="caption">Connection</AppText>}
            </View>
            {p.joined ? (
              <AppText style={styles.joined}>In the game</AppText>
            ) : p.invited ? (
              <View style={styles.invited}>
                <Ionicons name="checkmark" size={16} color={COLORS.success} />
                <AppText style={styles.invitedText}>Invited</AppText>
              </View>
            ) : (
              <Pressable onPress={() => invite(p)} style={styles.inviteBtn} accessibilityRole="button" accessibilityLabel={`Invite ${p.name}`}>
                <AppText style={styles.inviteText}>Invite</AppText>
              </Pressable>
            )}
          </View>
        ))}
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#4527A0', paddingHorizontal: SPACING.md },
  top: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  quiz: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.onColor },
  progress: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.8)' },
  speaker: { width: 38, height: 38, borderRadius: 19, backgroundColor: 'rgba(255,255,255,0.16)', alignItems: 'center', justifyContent: 'center', marginRight: 8 },
  scorePill: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: 'rgba(255,255,255,0.18)', borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 7 },
  scoreText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.onColor },
  center: { alignItems: 'center', gap: 8, paddingTop: 30 },
  pulse: { width: 96, height: 96, borderRadius: 48, backgroundColor: 'rgba(255,255,255,0.16)', alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
  big: { fontFamily: FONTS.bold, fontSize: 30, color: COLORS.onColor, textAlign: 'center' },
  sub: { fontFamily: FONTS.regular, fontSize: 15, color: 'rgba(255,255,255,0.85)', textAlign: 'center' },
  pin: { fontFamily: FONTS.bold, fontSize: 22, letterSpacing: 4, color: '#FFD36B', marginVertical: 6 },
  names: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, justifyContent: 'center', marginTop: 12 },
  nameChip: { backgroundColor: 'rgba(255,255,255,0.16)', borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6 },
  nameText: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.onColor },
  timer: { height: 10, borderRadius: 5, backgroundColor: 'rgba(255,255,255,0.2)', overflow: 'hidden' },
  timerFill: { height: 10, borderRadius: 5 },
  qCard: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.md, minHeight: 100, justifyContent: 'center' },
  qText: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 27, color: COLORS.black, textAlign: 'center' },
  secs: { position: 'absolute', right: 12, top: 8, fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
  options: { gap: 10 },
  option: { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: RADIUS.lg, paddingVertical: 14, paddingHorizontal: 14, minHeight: 62 },
  optionChosen: { borderWidth: 3, borderColor: COLORS.white },
  letter: { width: 34, height: 34, borderRadius: 17, backgroundColor: 'rgba(255,255,255,0.25)', alignItems: 'center', justifyContent: 'center' },
  letterText: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.onColor },
  optText: { flex: 1, fontFamily: FONTS.bold, fontSize: 17, color: COLORS.onColor },
  count: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.onColor, opacity: 0.9 },
  locked: { fontFamily: FONTS.medium, fontSize: 14, color: 'rgba(255,255,255,0.88)', textAlign: 'center' },
  boardBox: { backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: RADIUS.lg, padding: 12, gap: 8 },
  boardTitle: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.onColor },
  boardRow: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.white, borderRadius: RADIUS.md, paddingHorizontal: 12, paddingVertical: 8 },
  boardMe: { borderWidth: 2, borderColor: '#FFD36B' },
  rank: { width: 22, fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  boardName: { flex: 1, fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.black },
  gained: { fontFamily: FONTS.bold, fontSize: 13, color: '#1E9E5A' },
  boardScore: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black, minWidth: 44, textAlign: 'right' },
  result: { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: RADIUS.lg, padding: 14 },
  resultTitle: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.onColor },
  resultSub: { fontFamily: FONTS.semibold, fontSize: 14, color: 'rgba(255,255,255,0.92)' },
  podium: { flexDirection: 'row', alignItems: 'flex-end', gap: 8, alignSelf: 'stretch', marginTop: 8 },
  pod: { width: '100%', borderTopLeftRadius: 12, borderTopRightRadius: 12, alignItems: 'center', paddingTop: 8 },
  podRank: { fontFamily: FONTS.bold, fontSize: 24, color: '#2B1B22' },
  podName: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.onColor, maxWidth: '100%' },
  podScore: { fontFamily: FONTS.semibold, fontSize: 13, color: '#FFD36B', marginBottom: 4 },
  hostBox: { alignSelf: 'stretch', gap: 12, marginTop: 22 },
  hostRow: { flexDirection: 'row', gap: 10 },
  ghost: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, minHeight: 48, borderRadius: RADIUS.full, backgroundColor: 'rgba(255,255,255,0.16)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.3)' },
  ghostText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.onColor },
  cancel: { fontFamily: FONTS.semibold, fontSize: 14, color: 'rgba(255,255,255,0.75)' },
  empty: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray700, textAlign: 'center', paddingVertical: 12 },
  person: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.gray300 },
  personName: { fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  inviteBtn: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 8 },
  inviteText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.onColor },
  invited: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  invitedText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.success },
  joined: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.gray700 },
  progressChip: { fontFamily: FONTS.medium, fontSize: 12, color: 'rgba(255,255,255,0.8)' },
  myRun: { alignSelf: 'stretch', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.14)', borderRadius: RADIUS.xl - 4, paddingVertical: 20, paddingHorizontal: 16 },
  myScore: { fontFamily: FONTS.bold, fontSize: 52, lineHeight: 58, color: '#FFD36B' },
  mySub: { fontFamily: FONTS.semibold, fontSize: 14, color: 'rgba(255,255,255,0.85)', marginBottom: 14 },
  myStats: { flexDirection: 'row', alignSelf: 'stretch', justifyContent: 'space-around' },
  myStat: { alignItems: 'center', flex: 1 },
  myStatValue: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.onColor },
  myStatLabel: { fontFamily: FONTS.regular, fontSize: 12, color: 'rgba(255,255,255,0.8)' },
  final: { backgroundColor: 'rgba(255,255,255,0.16)', borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  finalText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.onColor },
});
