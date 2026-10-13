// A reading circle: up to six people reading the same plan together. See who has read today, read the passage
// in place, tell your partners you have read (with an optional private summary only the circle sees), and share
// the code to invite someone. The owner can change the reading time for everyone.
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, Share, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import PassageView from '../../components/PassageView';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, mediaUrl } from '../../lib/api';
import { timeAgo } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const TIMES = ['05:00', '06:00', '07:00', '12:30', '20:00', '21:00'];

function Face({ member, size = 40 }) {
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: COLORS.primaryLight, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' }}>
      {member.avatar_url ? <Image source={{ uri: mediaUrl(member.avatar_url) }} style={StyleSheet.absoluteFill} contentFit="cover" /> : <AppText style={{ fontFamily: FONTS.bold, fontSize: size * 0.38, color: COLORS.white }}>{String(member.name).charAt(0)}</AppText>}
    </View>
  );
}

export default function CircleScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [day, setDay] = useState(null);
  const { data, loading, error, retry, reload } = useFetch(`/reading-circles/${id}`, { params: { day: day ?? undefined } });
  const c = data?.data;
  const mine = c?.entries?.find((e) => e.name === 'You');

  const [summary, setSummary] = useState(null);
  const [busy, setBusy] = useState(false);
  const [edit, setEdit] = useState(false);
  const [name, setName] = useState('');
  const [time, setTime] = useState('06:00');
  const [days, setDays] = useState([]);

  const shownDay = c?.day.day ?? 1;
  const text = summary ?? mine?.summary ?? '';

  async function complete() {
    setBusy(true);
    try {
      await api.post(`/reading-circles/${id}/days/${shownDay}`, { summary: text.trim() || null });
      setSummary(null);
      await reload();
    } catch (e) {
      Alert.alert('Could not save', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function openEdit() {
    setName(c.name);
    setTime(c.reading_time);
    setDays(c.reading_days);
    setEdit(true);
  }

  async function saveEdit() {
    setBusy(true);
    try {
      await api.put(`/reading-circles/${id}`, { name: name.trim(), reading_time: time, reading_days: days });
      setEdit(false);
      await reload();
    } catch (e) {
      Alert.alert('Could not update', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function leave() {
    const run = async () => {
      try {
        await api.delete(`/reading-circles/${id}/leave`);
        router.back();
      } catch (e) {
        Alert.alert('Could not leave', errorMessage(e));
      }
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Leave this circle?', 'Your own plan progress is kept.', [
      { text: 'Stay', style: 'cancel' },
      { text: 'Leave', style: 'destructive', onPress: run },
    ]);
  }

  const toggleDay = (d) => setDays((cur) => (cur.includes(d) ? cur.filter((x) => x !== d) : [...cur, d].sort()));

  return (
    <View style={styles.screen}>
      <ScreenHeader title={c?.name ?? 'Reading circle'} subtitle={c ? `${c.plan.title} · ${c.schedule}` : undefined} />
      <StateView loading={loading && !c} error={!c ? error : null} onRetry={retry}>
        {c ? (
          <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <View style={styles.codeCard}>
                <View style={{ flex: 1 }}>
                  <AppText variant="caption">Invite code (share with up to {c.max_members - 1} partners)</AppText>
                  <AppText style={styles.code}>{c.code}</AppText>
                </View>
                <Pressable onPress={() => Share.share({ message: `Read ${c.plan.title} with me on FaithFellows! Open Bible → Reading plans → Join, and enter the code ${c.code}.` }).catch(() => {})} style={styles.share} accessibilityRole="button" accessibilityLabel="Share the code">
                  <Ionicons name="share-social-outline" size={20} color={COLORS.white} />
                </Pressable>
              </View>

              <View style={styles.faces}>
                {c.members.map((m) => (
                  <View key={m.id} style={{ alignItems: 'center', gap: 4, width: 64 }}>
                    <Face member={m} size={46} />
                    <AppText variant="caption" numberOfLines={1} style={{ fontSize: 11.5 }}>
                      {m.name.split(' ')[0]}
                    </AppText>
                  </View>
                ))}
              </View>

              <View style={styles.dayNav}>
                <Pressable disabled={shownDay <= 1} onPress={() => setDay(shownDay - 1)} style={[styles.navBtn, shownDay <= 1 && { opacity: 0.3 }]} accessibilityRole="button" accessibilityLabel="Previous day">
                  <Ionicons name="chevron-back" size={20} color={COLORS.primary} />
                </Pressable>
                <View style={{ flex: 1, alignItems: 'center' }}>
                  <AppText style={styles.dayLabel}>Day {shownDay}{shownDay === c.current_day ? ' · today' : ''}</AppText>
                  <AppText style={styles.passages}>{c.day.passages}</AppText>
                </View>
                <Pressable disabled={shownDay >= c.plan.days_count} onPress={() => setDay(shownDay + 1)} style={[styles.navBtn, shownDay >= c.plan.days_count && { opacity: 0.3 }]} accessibilityRole="button" accessibilityLabel="Next day">
                  <Ionicons name="chevron-forward" size={20} color={COLORS.primary} />
                </Pressable>
              </View>
              {c.day.reflection ? <AppText style={styles.reflection}>{c.day.reflection}</AppText> : null}

              <PassageView reference={c.day.passages} />

              <AppText style={styles.h2}>Who has read</AppText>
              {c.entries.map((e) => (
                <View key={e.user_id} style={styles.entry}>
                  <Face member={{ name: e.name, avatar_url: e.avatar_url }} size={36} />
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.entryName}>{e.name}</AppText>
                    {e.done ? (
                      <AppText style={styles.entryText}>{e.summary || 'Read it ✔ — no summary yet.'}</AppText>
                    ) : (
                      <AppText variant="caption">Not yet</AppText>
                    )}
                    {e.done && e.completed_at ? <AppText variant="caption">{timeAgo(e.completed_at)}</AppText> : null}
                  </View>
                  <Ionicons name={e.done ? 'checkmark-circle' : 'ellipse-outline'} size={22} color={e.done ? COLORS.success : COLORS.gray300} />
                </View>
              ))}

              <View style={styles.compose}>
                <AppText style={styles.h3}>{mine?.done ? 'Your summary' : 'Finished reading? Share what stood out'}</AppText>
                <TextInput value={text} onChangeText={setSummary} placeholder="A verse that spoke to you, a question, a prayer…" placeholderTextColor={COLORS.gray500} multiline style={styles.input} maxLength={3000} textAlignVertical="top" />
                <Button title={mine?.done ? 'Update summary' : "I've read it — tell my partners"} onPress={complete} loading={busy} />
              </View>

              <View style={{ gap: 10, marginTop: 20 }}>
                {c.is_owner ? <Button title="Change reading time" variant="outline" onPress={openEdit} /> : null}
                <Pressable onPress={leave} style={{ alignSelf: 'center', marginTop: 4 }} accessibilityRole="button">
                  <AppText style={{ color: COLORS.gray700, textDecorationLine: 'underline' }}>Leave this circle</AppText>
                </Pressable>
              </View>
            </ScrollView>
          </KeyboardAvoidingView>
        ) : null}
      </StateView>

      <Modal visible={edit} animationType="slide" transparent onRequestClose={() => setEdit(false)}>
        <Pressable style={styles.backdrop} onPress={() => setEdit(false)} accessibilityLabel="Close" />
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.grab} />
          <AppText style={styles.sheetTitle}>Reading time for everyone</AppText>
          <TextField label="Circle name" value={name} onChangeText={setName} maxLength={80} />
          <AppText style={styles.label}>Time</AppText>
          <View style={styles.wrap}>
            {TIMES.map((t) => (
              <Pressable key={t} onPress={() => setTime(t)} style={[styles.chip, time === t && styles.chipOn]} accessibilityRole="button">
                <AppText style={[styles.chipText, time === t && { color: COLORS.white }]}>{t}</AppText>
              </Pressable>
            ))}
          </View>
          <AppText style={[styles.label, { marginTop: 14 }]}>Days</AppText>
          <View style={styles.wrap}>
            {DAYS.map((d, i) => (
              <Pressable key={d} onPress={() => toggleDay(i)} style={[styles.chip, days.includes(i) && styles.chipOn]} accessibilityRole="button">
                <AppText style={[styles.chipText, days.includes(i) && { color: COLORS.white }]}>{d}</AppText>
              </Pressable>
            ))}
          </View>
          <Button title="Save" onPress={saveEdit} loading={busy} disabled={!days.length || !name.trim()} style={{ marginTop: 18 }} />
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  codeCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14 },
  code: { fontFamily: FONTS.bold, fontSize: 26, letterSpacing: 5, color: COLORS.primaryDark, marginTop: 2 },
  share: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  faces: { flexDirection: 'row', gap: 6, marginTop: 14 },
  dayNav: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 10, ...SHADOW, shadowOpacity: 0.04 },
  navBtn: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  dayLabel: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.primary, textTransform: 'uppercase', letterSpacing: 0.8 },
  passages: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black, textAlign: 'center' },
  reflection: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 14.5, lineHeight: 21, color: COLORS.gray700, marginVertical: 12 },
  h2: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black, marginTop: 24, marginBottom: 10 },
  h3: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black, marginBottom: 8 },
  entry: { flexDirection: 'row', alignItems: 'flex-start', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, marginBottom: 8 },
  entryName: { fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.black },
  entryText: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray900, marginTop: 2 },
  compose: { marginTop: 14, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 4 },
  input: { minHeight: 100, borderRadius: RADIUS.md, borderWidth: 1.5, borderColor: COLORS.gray300, padding: 12, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black, marginBottom: 10, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.5)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 12 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black, textAlign: 'center', marginBottom: 14 },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
