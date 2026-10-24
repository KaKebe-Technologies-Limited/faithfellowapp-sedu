// One reading plan: your progress and streak, a strip of days, today's passage read in place, and the buttons to
// mark it read or take notes. You can also read it with partners: start a circle, or open one you are in.
// Data: GET /reading-plans/{id}?day=, POST …/start, …/complete, …/circles; DELETE leaves the plan.
import { useState } from 'react';
import { Alert, Modal, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import PassageView from '../../components/PassageView';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, fieldErrors } from '../../lib/api';
import { useFetch } from '../../lib/useFetch';

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const TIMES = ['05:00', '06:00', '07:00', '12:30', '20:00', '21:00'];

export default function PlanScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [day, setDay] = useState(null);
  const { data, loading, error, retry, reload } = useFetch(`/reading-plans/${id}`, { params: { day: day ?? undefined } });
  const plan = data?.data;
  const p = plan?.progress;

  const [busy, setBusy] = useState(false);
  const [circleOpen, setCircleOpen] = useState(false);
  const [name, setName] = useState('');
  const [time, setTime] = useState('06:00');
  const [days, setDays] = useState([1, 2, 3, 4, 5]);
  const [circleError, setCircleError] = useState(null);

  async function act(run, failTitle) {
    setBusy(true);
    try {
      await run();
      await reload();
    } catch (e) {
      Alert.alert(failTitle, errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const start = () => act(() => api.post(`/reading-plans/${id}/start`), 'Could not start');
  const markRead = () => act(() => api.post(`/reading-plans/${id}/complete`, { day: plan.day.day }).then(() => setDay(null)), 'Could not save');

  function leave() {
    const run = () => act(async () => {
      await api.delete(`/reading-plans/${id}`);
      router.back();
    }, 'Could not leave');
    if (Platform.OS === 'web') return run();
    Alert.alert('Leave this plan?', 'Your progress on it will be removed.', [
      { text: 'Stay', style: 'cancel' },
      { text: 'Leave plan', style: 'destructive', onPress: run },
    ]);
  }

  async function createCircle() {
    if (!name.trim()) {
      setCircleError('Give your circle a name.');
      return;
    }
    setBusy(true);
    setCircleError(null);
    try {
      const { data: res } = await api.post(`/reading-plans/${id}/circles`, { name: name.trim(), reading_time: time, reading_days: days });
      setCircleOpen(false);
      router.push({ pathname: '/circle/[id]', params: { id: String(res.data.id) } });
    } catch (e) {
      const f = fieldErrors(e);
      setCircleError(f.name || f.reading_time || f.reading_days || errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const toggleDay = (d) => setDays((cur) => (cur.includes(d) ? cur.filter((x) => x !== d) : [...cur, d].sort()));

  return (
    <View style={styles.screen}>
      <ScreenHeader title={plan?.title ?? 'Reading plan'} subtitle={plan ? `${plan.days_count} days · ${plan.church.name}` : undefined} />
      <StateView loading={loading && !plan} error={!plan ? error : null} onRetry={retry}>
        {plan ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} showsVerticalScrollIndicator={false}>
            {p ? (
              <View style={styles.progress}>
                <View style={styles.pRow}>
                  <View>
                    <AppText style={styles.pBig}>{Math.round(p.percent)}%</AppText>
                    <AppText variant="caption">{p.completed} of {plan.days_count} days</AppText>
                  </View>
                  <View style={styles.pRight}>
                    <AppText style={{ fontSize: 22 }}>🔥</AppText>
                    <View>
                      <AppText style={styles.pBig}>{p.streak}</AppText>
                      <AppText variant="caption">day streak</AppText>
                    </View>
                  </View>
                </View>
                <View style={styles.track}>
                  <View style={[styles.fill, { width: `${Math.max(p.percent, 2)}%` }]} />
                </View>
              </View>
            ) : plan.description ? (
              <View style={styles.about}>
                <ChurchAvatar church={plan.church} size={38} />
                <AppText style={{ flex: 1, fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: COLORS.gray900 }}>{plan.description}</AppText>
              </View>
            ) : null}

            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.strip}>
              {plan.days.map((d) => {
                const current = d.day === plan.day.day;
                return (
                  <Pressable key={d.day} onPress={() => setDay(d.day)} style={[styles.dayChip, d.done && styles.dayDone, current && styles.dayCurrent]} accessibilityRole="button" accessibilityLabel={`Day ${d.day}${d.done ? ', read' : ''}`}>
                    {d.done ? <Ionicons name="checkmark" size={15} color={COLORS.onColor} /> : <AppText style={[styles.dayText, current && { color: COLORS.primary }]}>{d.day}</AppText>}
                  </Pressable>
                );
              })}
            </ScrollView>

            <View style={styles.today}>
              <AppText style={styles.dayLabel}>Day {plan.day.day}</AppText>
              {plan.day.title ? <AppText style={styles.dayTitle}>{plan.day.title}</AppText> : null}
              <AppText style={styles.passages}>{plan.day.passages}</AppText>
              {plan.day.reflection ? <AppText style={styles.reflection}>{plan.day.reflection}</AppText> : null}
            </View>

            <PassageView reference={plan.day.passages} />

            <View style={{ gap: 10, marginTop: 20 }}>
              {!p ? (
                <Button title="Start this plan" onPress={start} loading={busy} />
              ) : plan.day.done ? (
                <View style={styles.done}>
                  <Ionicons name="checkmark-circle" size={20} color={COLORS.success} />
                  <AppText style={styles.doneText}>You read day {plan.day.day} ✓</AppText>
                </View>
              ) : (
                <Button title={`I read day ${plan.day.day}`} onPress={markRead} loading={busy} />
              )}
              <Button title="Write a note" variant="outline" onPress={() => router.push({ pathname: '/note/[id]', params: { id: 'new', context: `plan:${plan.id}:${plan.day.day}`, reference: `${plan.title} — day ${plan.day.day}` } })} />
            </View>

            {p ? (
              <View style={{ marginTop: SPACING.lg }}>
                <AppText style={styles.h2}>Read with partners</AppText>
                {plan.circles.map((c) => (
                  <Pressable key={c.id} onPress={() => router.push({ pathname: '/circle/[id]', params: { id: String(c.id) } })} style={styles.circle} accessibilityRole="button">
                    <Ionicons name="people" size={20} color={COLORS.primary} />
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.circleName}>{c.name}</AppText>
                      <AppText variant="caption">{c.members.length} reading · {c.schedule}</AppText>
                    </View>
                    <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
                  </Pressable>
                ))}
                {plan.can_start_circle ? <Button title="Start a reading circle" variant="outline" small onPress={() => setCircleOpen(true)} style={{ alignSelf: 'flex-start', marginTop: 6 }} /> : null}
                <Pressable onPress={leave} style={{ alignSelf: 'center', marginTop: 24 }} accessibilityRole="button">
                  <AppText style={{ color: COLORS.gray700, textDecorationLine: 'underline' }}>Leave this plan</AppText>
                </Pressable>
              </View>
            ) : null}
          </ScrollView>
        ) : null}
      </StateView>

      <Modal visible={circleOpen} animationType="slide" transparent onRequestClose={() => setCircleOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setCircleOpen(false)} accessibilityLabel="Close" />
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.grab} />
          <AppText style={styles.sheetTitle}>Start a reading circle</AppText>
          <AppText variant="caption" style={{ textAlign: 'center', marginBottom: 14 }}>Up to 6 people from your church read the same plan and remind each other.</AppText>
          <TextField label="Circle name" value={name} onChangeText={setName} placeholder="For example: Morning readers" maxLength={80} />
          <AppText style={styles.label}>Reading time</AppText>
          <View style={styles.wrap}>
            {TIMES.map((t) => (
              <Pressable key={t} onPress={() => setTime(t)} style={[styles.chip, time === t && styles.chipOn]} accessibilityRole="button">
                <AppText style={[styles.chipText, time === t && { color: COLORS.onColor }]}>{t}</AppText>
              </Pressable>
            ))}
          </View>
          <AppText style={[styles.label, { marginTop: 14 }]}>Days</AppText>
          <View style={styles.wrap}>
            {DAYS.map((d, i) => (
              <Pressable key={d} onPress={() => toggleDay(i)} style={[styles.chip, days.includes(i) && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: days.includes(i) }}>
                <AppText style={[styles.chipText, days.includes(i) && { color: COLORS.onColor }]}>{d}</AppText>
              </Pressable>
            ))}
          </View>
          {circleError ? <AppText style={{ color: COLORS.danger, marginTop: 10 }}>{circleError}</AppText> : null}
          <Button title="Create circle" onPress={createCircle} loading={busy} disabled={!days.length} style={{ marginTop: 18 }} />
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  progress: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.md, ...SHADOW, shadowOpacity: 0.05 },
  pRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  pRight: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  pBig: { fontFamily: FONTS.bold, fontSize: 26, color: COLORS.black },
  track: { height: 9, borderRadius: 5, backgroundColor: COLORS.primaryMist, marginTop: 12, overflow: 'hidden' },
  fill: { height: 9, borderRadius: 5, backgroundColor: COLORS.primary },
  about: { flexDirection: 'row', gap: 12, alignItems: 'center', backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14 },
  strip: { gap: 8, paddingVertical: 14 },
  dayChip: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center', borderWidth: 1.5, borderColor: COLORS.gray100 },
  dayDone: { backgroundColor: COLORS.success, borderColor: COLORS.success },
  dayCurrent: { borderColor: COLORS.primary, borderWidth: 2.5 },
  dayText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.gray700 },
  today: { marginBottom: 14, gap: 3 },
  dayLabel: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary, textTransform: 'uppercase', letterSpacing: 0.8 },
  dayTitle: { fontFamily: FONTS.bold, fontSize: 21, color: COLORS.black },
  passages: { fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.gray900 },
  reflection: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 14.5, lineHeight: 21, color: COLORS.gray700, marginTop: 6 },
  done: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.full, paddingVertical: 14 },
  doneText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.success },
  h2: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black, marginBottom: 10 },
  circle: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginBottom: 8 },
  circleName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.5)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 12 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black, textAlign: 'center', marginBottom: 4 },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
