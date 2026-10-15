// Announcements: see what has been posted and write a new one — to all members or the public, normal or urgent,
// published now or kept as a draft. Publishing tells your followers. Data: /api/v1/admin/{church}/announcements.
import { useState } from 'react';
import { Alert, FlatList, Platform, Pressable, StyleSheet, Switch, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage, fieldErrors } from '../../../lib/api';
import { excerpt, timeAgo } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

export default function AdminAnnouncements() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const { data, loading, error, retry, reload } = useFetch(admin.path('/announcements'));
  const list = data?.data ?? [];

  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ title: '', body: '', audience: 'members', urgent: false, draft: false });
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      await admin.post('/announcements', { title: form.title.trim(), body: form.body.trim(), audience: form.audience, priority: form.urgent ? 'urgent' : 'normal', status: form.draft ? 'draft' : 'published' });
      setOpen(false);
      setForm({ title: '', body: '', audience: 'members', urgent: false, draft: false });
      await reload();
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not save', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function remove(a) {
    const run = async () => {
      await admin.delete(`/announcements/${a.id}`).catch(() => {});
      reload();
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this announcement?', a.title, [{ text: 'Keep', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: run }]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Announcements"
        subtitle="Tell your church what is happening"
        right={
          <Pressable onPress={() => setOpen(true)} style={styles.add} accessibilityRole="button" accessibilityLabel="New announcement">
            <Ionicons name="add" size={20} color={COLORS.white} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !list.length} emptyIcon="megaphone-outline" emptyTitle="No announcements yet" emptyMessage="Write one and your followers are notified straight away." emptyAction={{ title: 'Write an announcement', onPress: () => setOpen(true) }}>
        <FlatList
          data={list}
          keyExtractor={(a) => String(a.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 10 }}
          renderItem={({ item: a }) => (
            <View style={styles.card}>
              <View style={{ flex: 1, gap: 3 }}>
                <View style={{ flexDirection: 'row', gap: 6, alignItems: 'center' }}>
                  {a.urgent ? <Ionicons name="alert-circle" size={16} color={COLORS.danger} /> : null}
                  <AppText style={styles.title} numberOfLines={2}>{a.title}</AppText>
                </View>
                <AppText variant="caption" numberOfLines={2}>{excerpt(a.body, 140)}</AppText>
                <AppText variant="caption" style={{ color: a.status === 'published' ? COLORS.success : COLORS.warning, fontFamily: FONTS.semibold }}>
                  {a.status === 'published' ? `Published ${timeAgo(a.published_at)}` : a.status === 'draft' ? 'Draft' : 'Scheduled'} · {a.audience === 'public' ? 'Everyone' : 'Members'}
                </AppText>
              </View>
              <Pressable onPress={() => remove(a)} hitSlop={8} accessibilityLabel={`Delete ${a.title}`}>
                <Ionicons name="trash-outline" size={20} color={COLORS.gray500} />
              </Pressable>
            </View>
          )}
        />
      </StateView>

      <Sheet visible={open} onClose={() => setOpen(false)} title="New announcement" tall>
        <TextField label="Title" value={form.title} onChangeText={(t) => setForm((f) => ({ ...f, title: t }))} error={errors.title} maxLength={200} />
        <TextField label="Message" value={form.body} onChangeText={(t) => setForm((f) => ({ ...f, body: t }))} error={errors.body} multiline numberOfLines={5} style={{ minHeight: 110 }} />
        <AppText style={styles.label}>Who sees it?</AppText>
        <View style={styles.wrap}>
          <Chip label="Members" on={form.audience === 'members'} onPress={() => setForm((f) => ({ ...f, audience: 'members' }))} />
          <Chip label="Everyone (public)" on={form.audience === 'public'} onPress={() => setForm((f) => ({ ...f, audience: 'public' }))} />
        </View>
        <View style={styles.switch}>
          <View style={{ flex: 1 }}>
            <AppText style={styles.switchTitle}>Mark as urgent</AppText>
            <AppText variant="caption">Shown in red and sent even in quiet hours.</AppText>
          </View>
          <Switch value={form.urgent} onValueChange={(v) => setForm((f) => ({ ...f, urgent: v }))} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
        </View>
        <View style={styles.switch}>
          <View style={{ flex: 1 }}>
            <AppText style={styles.switchTitle}>Save as a draft</AppText>
            <AppText variant="caption">Nobody is notified until you publish it.</AppText>
          </View>
          <Switch value={form.draft} onValueChange={(v) => setForm((f) => ({ ...f, draft: v }))} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
        </View>
        <Button title={form.draft ? 'Save draft' : 'Publish now'} onPress={save} loading={busy} disabled={!form.title.trim() || !form.body.trim()} style={{ marginTop: 8 }} />
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, ...SHADOW, shadowOpacity: 0.05 },
  title: { flex: 1, fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  switch: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8 },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
});
