// Publish a devotional or add a sermon (with a link to its audio or video) from the phone. Followers are notified
// when it goes live. Recent ones are listed below with a delete button. Data: /api/v1/admin/{church}/devotionals|sermons.
import { useState } from 'react';
import { Alert, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
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
import { adminApi, today } from '../../../lib/admin';
import { errorMessage, fieldErrors } from '../../../lib/api';
import { formatDate } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

const BLANK_DEVO = { title: '', scripture: '', scripture_text: '', body: '', prayer: '', publish_date: today(), draft: false };
const BLANK_SERMON = { title: '', preacher: '', scripture: '', description: '', audio_url: '', video_url: '', draft: false };

export default function AdminPublish() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const [tab, setTab] = useState('devotionals');
  const devos = useFetch(admin.path('/devotionals'));
  const sermons = useFetch(admin.path('/sermons'));
  const [open, setOpen] = useState(false);
  const [devo, setDevo] = useState(BLANK_DEVO);
  const [sermon, setSermon] = useState(BLANK_SERMON);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const active = tab === 'devotionals' ? devos : sermons;
  const list = active.data?.data ?? [];

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      if (tab === 'devotionals') {
        await admin.post('/devotionals', { title: devo.title.trim(), scripture: devo.scripture || null, scripture_text: devo.scripture_text || null, body: devo.body.trim(), prayer: devo.prayer || null, publish_date: devo.publish_date, status: devo.draft ? 'draft' : 'published', visibility: 'public' });
        setDevo(BLANK_DEVO);
      } else {
        await admin.post('/sermons', { title: sermon.title.trim(), preacher: sermon.preacher || null, scripture: sermon.scripture || null, description: sermon.description || null, audio_url: sermon.audio_url || null, video_url: sermon.video_url || null, language: 'en', status: sermon.draft ? 'draft' : 'published', visibility: 'public' });
        setSermon(BLANK_SERMON);
      }
      setOpen(false);
      await active.reload();
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not save', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function remove(item) {
    const run = async () => {
      await admin.delete(`/${tab}/${item.id}`).catch(() => {});
      active.reload();
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this?', item.title, [{ text: 'Keep', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: run }]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Devotionals & sermons"
        subtitle="Publish to your church"
        right={
          <Pressable onPress={() => setOpen(true)} style={styles.add} accessibilityRole="button" accessibilityLabel="Add new">
            <Ionicons name="add" size={20} color={COLORS.white} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <View style={styles.tabs}>
        <Chip label="Devotionals" on={tab === 'devotionals'} onPress={() => setTab('devotionals')} />
        <Chip label="Sermons" on={tab === 'sermons'} onPress={() => setTab('sermons')} />
      </View>
      <StateView loading={active.loading} error={active.error} onRetry={active.retry} empty={!active.loading && !active.error && !list.length} emptyIcon="book-outline" emptyTitle="Nothing published yet" emptyAction={{ title: 'Add one', onPress: () => setOpen(true) }}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, gap: 10 }}>
          {list.map((item) => (
            <View key={item.id} style={styles.card}>
              <View style={{ flex: 1, gap: 2 }}>
                <AppText style={styles.title} numberOfLines={2}>{item.title}</AppText>
                <AppText variant="caption">
                  {tab === 'devotionals' ? [item.scripture, item.publish_date ? formatDate(item.publish_date) : null].filter(Boolean).join(' · ') : [item.preacher, item.published_at ? formatDate(item.published_at) : null].filter(Boolean).join(' · ')}
                </AppText>
                <AppText variant="caption" style={{ color: item.status === 'published' ? COLORS.success : COLORS.warning, fontFamily: FONTS.semibold }}>{item.status}</AppText>
              </View>
              <Pressable onPress={() => remove(item)} hitSlop={8} accessibilityLabel={`Delete ${item.title}`}>
                <Ionicons name="trash-outline" size={20} color={COLORS.gray500} />
              </Pressable>
            </View>
          ))}
        </ScrollView>
      </StateView>

      <Sheet visible={open} onClose={() => setOpen(false)} title={tab === 'devotionals' ? 'New devotional' : 'New sermon'} tall>
        {tab === 'devotionals' ? (
          <>
            <TextField label="Title" value={devo.title} onChangeText={(t) => setDevo((d) => ({ ...d, title: t }))} error={errors.title} />
            <TextField label="Scripture (e.g. Psalm 23:1)" value={devo.scripture} onChangeText={(t) => setDevo((d) => ({ ...d, scripture: t }))} />
            <TextField label="Verse text" value={devo.scripture_text} onChangeText={(t) => setDevo((d) => ({ ...d, scripture_text: t }))} multiline numberOfLines={3} style={{ minHeight: 70 }} />
            <TextField label="Message" value={devo.body} onChangeText={(t) => setDevo((d) => ({ ...d, body: t }))} error={errors.body} multiline numberOfLines={6} style={{ minHeight: 130 }} />
            <TextField label="Closing prayer" value={devo.prayer} onChangeText={(t) => setDevo((d) => ({ ...d, prayer: t }))} multiline numberOfLines={2} />
            <TextField label="Publish date (YYYY-MM-DD)" value={devo.publish_date} onChangeText={(t) => setDevo((d) => ({ ...d, publish_date: t }))} error={errors.publish_date} />
            <View style={styles.wrap}>
              <Chip label="Publish" on={!devo.draft} onPress={() => setDevo((d) => ({ ...d, draft: false }))} />
              <Chip label="Save as draft" on={devo.draft} onPress={() => setDevo((d) => ({ ...d, draft: true }))} />
            </View>
            <Button title="Save devotional" onPress={save} loading={busy} disabled={!devo.title.trim() || !devo.body.trim()} />
          </>
        ) : (
          <>
            <TextField label="Title" value={sermon.title} onChangeText={(t) => setSermon((s) => ({ ...s, title: t }))} error={errors.title} />
            <TextField label="Preacher" value={sermon.preacher} onChangeText={(t) => setSermon((s) => ({ ...s, preacher: t }))} />
            <TextField label="Scripture" value={sermon.scripture} onChangeText={(t) => setSermon((s) => ({ ...s, scripture: t }))} />
            <TextField label="Audio link (.mp3)" value={sermon.audio_url} onChangeText={(t) => setSermon((s) => ({ ...s, audio_url: t }))} error={errors.audio_url} autoCapitalize="none" keyboardType="url" placeholder="https://…" />
            <TextField label="Video link (YouTube or file)" value={sermon.video_url} onChangeText={(t) => setSermon((s) => ({ ...s, video_url: t }))} error={errors.video_url} autoCapitalize="none" keyboardType="url" placeholder="https://…" />
            <TextField label="Notes" value={sermon.description} onChangeText={(t) => setSermon((s) => ({ ...s, description: t }))} multiline numberOfLines={4} style={{ minHeight: 90 }} />
            <View style={styles.wrap}>
              <Chip label="Publish" on={!sermon.draft} onPress={() => setSermon((s) => ({ ...s, draft: false }))} />
              <Chip label="Save as draft" on={sermon.draft} onPress={() => setSermon((s) => ({ ...s, draft: true }))} />
            </View>
            <Button title="Save sermon" onPress={save} loading={busy} disabled={!sermon.title.trim()} />
            <AppText variant="caption" style={{ textAlign: 'center', marginTop: 8 }}>To upload a recording file, use the website.</AppText>
          </>
        )}
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 6 },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, ...SHADOW, shadowOpacity: 0.05 },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
});
