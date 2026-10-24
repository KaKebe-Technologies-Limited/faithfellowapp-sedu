// Host a quiz: your church's quizzes with a "Host" button, games that are on now (tap to run one), and a builder to
// write a new quiz (questions with up to four answers and the right one marked). Players join from the Quiz games
// screen with the PIN. Data: /api/v1/admin/{church}/quizzes.
import { useCallback, useState } from 'react';
import { Alert, Platform, Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage } from '../../../lib/api';
import { useFetch } from '../../../lib/useFetch';

const LETTERS = ['A', 'B', 'C', 'D'];
const blankQuestion = () => ({ question: '', options: ['', '', '', ''], correct: 0 });

export default function AdminGames() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const { data, loading, error, retry, reload } = useFetch(admin.path('/quizzes'));
  const quizzes = data?.data?.quizzes ?? [];
  const active = data?.data?.active ?? [];
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [questions, setQuestions] = useState([blankQuestion()]);
  const [notify, setNotify] = useState(true);
  const [busy, setBusy] = useState(null);
  const [editId, setEditId] = useState(null);

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  const setQ = (i, patch) => setQuestions((qs) => qs.map((q, j) => (j === i ? { ...q, ...patch } : q)));
  const setOption = (i, k, text) => setQ(i, { options: questions[i].options.map((o, j) => (j === k ? text : o)) });
  const valid = title.trim() && questions.every((q) => q.question.trim() && q.options.filter((o) => o.trim()).length >= 2 && q.options[q.correct]?.trim());

  function openNew() {
    setEditId(null);
    setTitle('');
    setQuestions([blankQuestion()]);
    setOpen(true);
  }

  async function openEdit(quiz) {
    setBusy(`edit${quiz.id}`);
    try {
      const { data: res } = await admin.get(`/quizzes/${quiz.id}`);
      setEditId(quiz.id);
      setTitle(res.data.title);
      setQuestions(res.data.questions.map((q) => ({ question: q.question, options: [...q.options, '', '', '', ''].slice(0, 4), correct: q.correct })));
      setOpen(true);
    } catch (e) {
      Alert.alert('Could not open the quiz', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  async function save() {
    setBusy('save');
    try {
      const body = { title: title.trim(), questions: questions.map((q) => ({ question: q.question.trim(), options: q.options, correct: q.correct })) };
      if (editId) await admin.put(`/quizzes/${editId}`, body);
      else await admin.post('/quizzes', body);
      setOpen(false);
      setEditId(null);
      setTitle('');
      setQuestions([blankQuestion()]);
      await reload();
    } catch (e) {
      Alert.alert(editId ? 'Could not save your changes' : 'Could not save the quiz', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  async function host(quiz) {
    setBusy(`host${quiz.id}`);
    try {
      const { data: res } = await admin.post(`/quizzes/${quiz.id}/host`, { notify });
      router.push({ pathname: '/admin/[slug]/game/[id]', params: { slug, id: String(res.data.id) } });
    } catch (e) {
      Alert.alert('Could not start', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  function remove(quiz) {
    const run = async () => {
      await admin.delete(`/quizzes/${quiz.id}`).catch(() => {});
      reload();
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this quiz?', quiz.title, [{ text: 'Keep', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: run }]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Host a quiz"
        subtitle="Play Bible quizzes with your church"
        right={
          <Pressable onPress={openNew} style={styles.add} accessibilityRole="button" accessibilityLabel="Write a new quiz">
            <Ionicons name="add" size={20} color={COLORS.onColor} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <StateView loading={loading && !data} error={!data ? error : null} onRetry={retry}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48, gap: 12 }}>
          {active.map((s) => (
            <Pressable key={s.id} onPress={() => router.push({ pathname: '/admin/[slug]/game/[id]', params: { slug, id: String(s.id) } })} style={styles.live} accessibilityRole="button">
              <Ionicons name="game-controller" size={24} color={COLORS.onColor} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.liveLabel}>Running now · PIN {s.pin} · {s.players_count} playing</AppText>
                <AppText style={styles.liveTitle}>{s.quiz}</AppText>
              </View>
              <Ionicons name="chevron-forward" size={18} color={COLORS.onColor} />
            </Pressable>
          ))}

          <View style={styles.switch}>
            <View style={{ flex: 1 }}>
              <AppText style={styles.switchTitle}>Tell members when I start a game</AppText>
              <AppText variant="caption">Sends a notice with a button to join.</AppText>
            </View>
            <Switch value={notify} onValueChange={setNotify} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.onColor} />
          </View>

          {quizzes.length === 0 ? <AppText variant="caption" style={{ textAlign: 'center', marginTop: 20 }}>No quizzes yet — write your first with "New".</AppText> : null}
          {quizzes.map((q) => (
            <View key={q.id} style={styles.card}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.title}>{q.title}</AppText>
                <AppText variant="caption">{q.questions} {q.questions === 1 ? 'question' : 'questions'}</AppText>
              </View>
              <Button title="Host" small onPress={() => host(q)} loading={busy === `host${q.id}`} />
              <Pressable onPress={() => openEdit(q)} hitSlop={8} accessibilityLabel={`Edit ${q.title}`}>
                <Ionicons name="create-outline" size={21} color={busy === `edit${q.id}` ? COLORS.gray300 : COLORS.primary} />
              </Pressable>
              <Pressable onPress={() => remove(q)} hitSlop={8} accessibilityLabel={`Delete ${q.title}`}>
                <Ionicons name="trash-outline" size={20} color={COLORS.gray500} />
              </Pressable>
            </View>
          ))}
        </ScrollView>
      </StateView>

      <Sheet visible={open} onClose={() => setOpen(false)} title={editId ? 'Edit quiz' : 'New quiz'} tall>
        <TextField label="Quiz name" value={title} onChangeText={setTitle} placeholder="Bible Heroes" maxLength={160} />
        {questions.map((q, i) => (
          <View key={i} style={styles.qCard}>
            <View style={{ flexDirection: 'row', alignItems: 'center' }}>
              <AppText style={styles.qNum}>Question {i + 1}</AppText>
              {questions.length > 1 ? (
                <Pressable onPress={() => setQuestions((qs) => qs.filter((_, j) => j !== i))} hitSlop={8} accessibilityLabel={`Remove question ${i + 1}`} style={{ marginLeft: 'auto' }}>
                  <Ionicons name="close-circle-outline" size={20} color={COLORS.gray500} />
                </Pressable>
              ) : null}
            </View>
            <TextField placeholder="Who built the ark?" value={q.question} onChangeText={(t) => setQ(i, { question: t })} maxLength={300} style={{ marginBottom: 8 }} />
            {q.options.map((o, k) => (
              <View key={k} style={styles.optRow}>
                <Pressable onPress={() => setQ(i, { correct: k })} accessibilityRole="radio" accessibilityState={{ selected: q.correct === k }} accessibilityLabel={`Answer ${LETTERS[k]} is correct`} hitSlop={6}>
                  <Ionicons name={q.correct === k ? 'checkmark-circle' : 'ellipse-outline'} size={26} color={q.correct === k ? COLORS.success : COLORS.gray300} />
                </Pressable>
                <View style={{ flex: 1 }}>
                  <TextField placeholder={`Answer ${LETTERS[k]}${k > 1 ? ' (optional)' : ''}`} value={o} onChangeText={(t) => setOption(i, k, t)} maxLength={120} style={{ marginBottom: 0 }} />
                </View>
              </View>
            ))}
            <AppText variant="caption">Tap the circle beside the right answer.</AppText>
          </View>
        ))}
        <Button title="Add another question" variant="outline" small onPress={() => setQuestions((qs) => [...qs, blankQuestion()])} style={{ alignSelf: 'flex-start', marginVertical: 10 }} />
        <Button title={editId ? 'Save changes' : 'Save quiz'} onPress={save} loading={busy === 'save'} disabled={!valid} />
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.onColor },
  live: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#5E35B1', borderRadius: RADIUS.lg + 2, padding: 14 },
  liveLabel: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.85)' },
  liveTitle: { fontFamily: FONTS.bold, fontSize: 16.5, color: COLORS.onColor },
  switch: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12 },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.black },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, ...SHADOW, shadowOpacity: 0.05 },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  qCard: { backgroundColor: COLORS.canvas, borderRadius: RADIUS.lg, padding: 12, gap: 6, marginBottom: 10 },
  qNum: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  optRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6 },
});
