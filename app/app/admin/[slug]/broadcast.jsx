// Message the church: write a short message and send it now to your members (or only to a membership stage, or a
// study group) as an in-app notice — urgent ones skip quiet hours. See what you sent before.
// Data: /api/v1/admin/{church}/broadcasts.
import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Switch, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import ScreenHeader from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage, fieldErrors } from '../../../lib/api';
import { timeAgo } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

const STAGES = [['visitor', 'Visitors'], ['new_member', 'New members'], ['member', 'Members'], ['worker', 'Workers'], ['leader', 'Leaders']];

export default function AdminBroadcast() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const history = useFetch(admin.path('/broadcasts'));
  const [form, setForm] = useState({ title: '', body: '', audience: 'members', stages: [], urgent: false, category: 'announcement' });
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const [done, setDone] = useState(null);
  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v }));
  const toggleStage = (s) => setForm((f) => ({ ...f, stages: f.stages.includes(s) ? f.stages.filter((x) => x !== s) : [...f.stages, s] }));

  async function send() {
    setBusy(true);
    setErrors({});
    setDone(null);
    try {
      const { data } = await admin.post('/broadcasts', { title: form.title.trim(), body: form.body.trim(), audience: form.audience, ...(form.audience === 'stage' ? { stages: form.stages } : {}), category: form.category, is_urgent: form.urgent, send: 'now' });
      setDone(`Sent to ${data.data.recipients} ${data.data.recipients === 1 ? 'person' : 'people'}.`);
      setForm((f) => ({ ...f, title: '', body: '' }));
      await history.reload();
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not send', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Message the church" subtitle="A notice to your members' phones" />
      <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <View style={styles.card}>
          <TextField label="Title" value={form.title} onChangeText={set('title')} error={errors.title} maxLength={160} placeholder="Sunday service moved" />
          <TextField label="Message" value={form.body} onChangeText={set('body')} error={errors.body} multiline numberOfLines={4} maxLength={2000} style={{ minHeight: 90 }} placeholder="We start at 9 AM this week." />
          <AppText style={styles.label}>Who gets it?</AppText>
          <View style={styles.wrap}>
            <Chip label="All members" on={form.audience === 'members'} onPress={() => set('audience')('members')} />
            <Chip label="By stage" on={form.audience === 'stage'} onPress={() => set('audience')('stage')} />
            <Chip label="Staff only" on={form.audience === 'staff'} onPress={() => set('audience')('staff')} />
          </View>
          {form.audience === 'stage' ? (
            <View style={[styles.wrap, { marginTop: 10 }]}>
              {STAGES.map(([key, label]) => (
                <Chip key={key} label={label} on={form.stages.includes(key)} onPress={() => toggleStage(key)} />
              ))}
            </View>
          ) : null}
          <View style={styles.switch}>
            <View style={{ flex: 1 }}>
              <AppText style={styles.switchTitle}>Urgent</AppText>
              <AppText variant="caption">Delivered at once, even in quiet hours.</AppText>
            </View>
            <Switch value={form.urgent} onValueChange={set('urgent')} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
          </View>
          {done ? <AppText style={styles.done}>{done}</AppText> : null}
          <Button title="Send now" onPress={send} loading={busy} disabled={!form.title.trim() || !form.body.trim() || (form.audience === 'stage' && !form.stages.length)} />
        </View>

        <AppText style={styles.h}>Sent before</AppText>
        <StateView loading={history.loading} error={history.error} onRetry={history.retry} empty={!history.loading && !history.error && !(history.data?.data ?? []).length} emptyIcon="paper-plane-outline" emptyTitle="Nothing sent yet">
          {(history.data?.data ?? []).map((b) => (
            <View key={b.id} style={styles.sent}>
              <AppText style={styles.sentTitle}>{b.title}</AppText>
              <AppText variant="caption" numberOfLines={2}>{b.body}</AppText>
              <AppText variant="caption" style={{ color: COLORS.primary, fontFamily: FONTS.semibold }}>{b.status === 'sent' ? `Sent ${timeAgo(b.sent_at)} to ${b.recipients}` : b.status}</AppText>
            </View>
          ))}
        </StateView>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.md, ...SHADOW, shadowOpacity: 0.05 },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  switch: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  done: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.success, marginBottom: 10 },
  h: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black, marginTop: SPACING.lg, marginBottom: 10 },
  sent: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, gap: 3, marginBottom: 8 },
  sentTitle: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
});
