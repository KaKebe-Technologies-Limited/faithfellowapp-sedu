// Live services: schedule one (with a YouTube, Facebook or stream link, or your own encoder), go live when you are
// ready — your followers are notified — and end it. Members watch and chat inside the app.
// Data: /api/v1/admin/{church}/live.
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect, useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import Pill from '../../../components/Pill';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi, joinDateTime, today } from '../../../lib/admin';
import { errorMessage, fieldErrors } from '../../../lib/api';
import { dayAndTime } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

const SOURCES = [['youtube', 'YouTube'], ['facebook', 'Facebook'], ['hls', 'Stream link'], ['rtmp', 'My encoder']];

export default function AdminLive() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const { data, loading, error, retry, reload } = useFetch(admin.path('/live'));
  const list = data?.data ?? [];
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ title: 'Sunday Service', date: today(), time: '10:00', source: 'youtube', url: '', chat: true });
  const [busy, setBusy] = useState(null);
  const [errors, setErrors] = useState({});

  useFocusEffect(
    useCallback(() => {
      reload();
      const t = setInterval(reload, 15000);
      return () => clearInterval(t);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  async function schedule() {
    setBusy('new');
    setErrors({});
    try {
      await admin.post('/live', { title: form.title.trim(), scheduled_at: joinDateTime(form.date, form.time), source_type: form.source, external_url: form.source === 'rtmp' ? null : form.url.trim(), chat_enabled: form.chat });
      setOpen(false);
      await reload();
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not schedule', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  async function act(stream, action) {
    setBusy(`${stream.id}${action}`);
    try {
      await admin.post(`/live/${stream.id}/${action}`);
      await reload();
    } catch (e) {
      Alert.alert('Could not do that', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Live services"
        subtitle="Go live to your church"
        right={
          <Pressable onPress={() => setOpen(true)} style={styles.add} accessibilityRole="button" accessibilityLabel="Schedule a service">
            <Ionicons name="add" size={20} color={COLORS.white} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <StateView loading={loading && !data} error={!data ? error : null} onRetry={retry} empty={!loading && !error && !list.length} emptyIcon="radio-outline" emptyTitle="No live services yet" emptyAction={{ title: 'Schedule one', onPress: () => setOpen(true) }}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, gap: 12 }}>
          {list.map((s) => (
            <View key={s.id} style={styles.card}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                {s.status === 'live' ? <Pill label="LIVE" icon="radio-outline" tone="live" /> : <Pill label={s.status === 'scheduled' ? 'Scheduled' : 'Ended'} />}
                {s.status === 'live' && s.viewers != null ? <AppText variant="caption">{s.viewers} watching</AppText> : null}
              </View>
              <AppText style={styles.title}>{s.title}</AppText>
              <AppText variant="caption">{dayAndTime(s.scheduled_at)} · {SOURCES.find(([k]) => k === s.source_type)?.[1] ?? s.source_type}{s.peak_viewers ? ` · peak ${s.peak_viewers}` : ''}</AppText>
              {s.status === 'scheduled' ? <Button title="Go live now" onPress={() => act(s, 'start')} loading={busy === `${s.id}start`} /> : null}
              {s.status === 'live' ? <Button title="End the service" variant="outline" onPress={() => act(s, 'stop')} loading={busy === `${s.id}stop`} /> : null}
            </View>
          ))}
        </ScrollView>
      </StateView>

      <Sheet visible={open} onClose={() => setOpen(false)} title="Schedule a live service" tall>
        <TextField label="Title" value={form.title} onChangeText={(t) => setForm((f) => ({ ...f, title: t }))} error={errors.title} />
        <TextField label="Date (YYYY-MM-DD)" value={form.date} onChangeText={(t) => setForm((f) => ({ ...f, date: t }))} error={errors.scheduled_at} />
        <TextField label="Time (HH:MM)" value={form.time} onChangeText={(t) => setForm((f) => ({ ...f, time: t }))} />
        <AppText style={styles.label}>Where does the video come from?</AppText>
        <View style={styles.wrap}>
          {SOURCES.map(([key, label]) => (
            <Chip key={key} label={label} on={form.source === key} onPress={() => setForm((f) => ({ ...f, source: key }))} />
          ))}
        </View>
        {form.source !== 'rtmp' ? <TextField label={form.source === 'hls' ? 'Stream link (.m3u8)' : 'Video link'} value={form.url} onChangeText={(t) => setForm((f) => ({ ...f, url: t }))} error={errors.external_url} autoCapitalize="none" keyboardType="url" placeholder="https://…" /> : <AppText variant="caption" style={{ marginBottom: 10 }}>The stream key for your encoder is on the service's page on the website.</AppText>}
        <View style={styles.switch}>
          <AppText style={styles.switchTitle}>Live chat</AppText>
          <Switch value={form.chat} onValueChange={(v) => setForm((f) => ({ ...f, chat: v }))} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
        </View>
        <Button title="Schedule" onPress={schedule} loading={busy === 'new'} disabled={!form.title.trim()} style={{ marginTop: 6 }} />
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 8, ...SHADOW, shadowOpacity: 0.05 },
  title: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  switch: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 6 },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
});
