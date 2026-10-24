// Platform admin → Podcast reviews: podcasts members started that wait to be approved before they show in the app. Approve (the podcaster is told and followers
// hear about it), turn one down with a reason so they know what to change, or hide one that is already in. Data: GET /api/v1/platform/podcasts and POST …/{id}.
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import Chip from '../../components/Chip';
import ScreenHeader from '../../components/ScreenHeader';
import Sheet from '../../components/Sheet';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, fieldErrors, mediaUrl } from '../../lib/api';
import { usePaged } from '../../lib/usePaged';

export default function PlatformPodcasts() {
  const [status, setStatus] = useState('pending');
  const [reviewing, setReviewing] = useState(null); // { podcast, action: 'reject' | 'hide' }
  const [reason, setReason] = useState('');
  const [problem, setProblem] = useState(null);
  const [notice, setNotice] = useState(null);
  const [busy, setBusy] = useState(null);
  const list = usePaged('/platform/podcasts', { status });

  async function send(podcast, action, why) {
    setBusy(podcast.id);
    setProblem(null);
    try {
      const { data } = await api.post(`/platform/podcasts/${podcast.id}`, { action, reason: why || undefined });
      setNotice(data.message);
      setReviewing(null);
      setReason('');
      await list.reload();
    } catch (e) {
      const field = fieldErrors(e);
      if (reviewing) setProblem(field.reason ?? errorMessage(e));
      else Alert.alert('Could not do that', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Podcast reviews" subtitle="Approve what goes into the app" />
      <View style={{ paddingHorizontal: SPACING.md, gap: 10, paddingBottom: 8 }}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
          {[['pending', 'Waiting'], ['approved', 'In the app'], ['rejected', 'Turned down'], ['hidden', 'Hidden']].map(([key, label]) => (
            <Chip key={key} label={label} on={status === key} onPress={() => setStatus(key)} />
          ))}
        </ScrollView>
        {notice ? (
          <Pressable onPress={() => setNotice(null)} style={styles.notice} accessibilityRole="button" accessibilityLabel="Dismiss message">
            <Ionicons name="checkmark-circle" size={18} color={COLORS.success} />
            <AppText style={styles.noticeText}>{notice}</AppText>
          </Pressable>
        ) : null}
      </View>

      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="mic-off-outline" emptyTitle={status === 'pending' ? 'Nothing waiting' : 'Nothing here'}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingTop: 4, gap: 10, paddingBottom: 48 }}>
          {list.items.map((p) => (
            <View key={p.id} style={styles.card}>
              <View style={styles.row}>
                <Image source={{ uri: mediaUrl(p.cover_url) }} style={styles.cover} contentFit="cover" />
                <View style={{ flex: 1, gap: 2 }}>
                  <AppText style={styles.name} numberOfLines={2}>
                    {p.name}
                  </AppText>
                  <AppText variant="caption" numberOfLines={1}>
                    {[p.host, p.category].filter(Boolean).join(' · ')}
                  </AppText>
                  <AppText variant="caption">
                    {p.episodes} {p.episodes === 1 ? 'episode' : 'episodes'}
                    {p.owner ? ` · by ${p.owner}` : ''}
                  </AppText>
                </View>
              </View>
              {p.summary ? <AppText style={styles.summary}>{p.summary}</AppText> : null}
              {p.review_note ? <AppText style={styles.note}>Note: {p.review_note}</AppText> : null}
              <View style={styles.actions}>
                {p.status !== 'approved' ? (
                  <Pressable onPress={() => send(p, 'approve')} disabled={busy === p.id} style={[styles.btn, styles.btnMain]} accessibilityRole="button" accessibilityLabel={`Approve ${p.name}`}>
                    <AppText style={[styles.btnText, { color: COLORS.onColor }]}>{busy === p.id ? 'Approving…' : 'Approve'}</AppText>
                  </Pressable>
                ) : null}
                {p.status === 'pending' ? (
                  <Pressable onPress={() => setReviewing({ podcast: p, action: 'reject' })} style={styles.btn} accessibilityRole="button" accessibilityLabel={`Turn down ${p.name}`}>
                    <AppText style={styles.btnText}>Turn down</AppText>
                  </Pressable>
                ) : null}
                {p.status === 'approved' ? (
                  <Pressable onPress={() => setReviewing({ podcast: p, action: 'hide' })} style={styles.btn} accessibilityRole="button" accessibilityLabel={`Hide ${p.name}`}>
                    <AppText style={[styles.btnText, { color: COLORS.danger }]}>Hide from the app</AppText>
                  </Pressable>
                ) : null}
              </View>
            </View>
          ))}
          {list.hasMore ? (
            <Pressable onPress={list.loadMore} style={styles.more} accessibilityRole="button">
              <AppText style={styles.moreText}>Load more</AppText>
            </Pressable>
          ) : null}
        </ScrollView>
      </StateView>

      <Sheet visible={!!reviewing} onClose={() => setReviewing(null)} title={reviewing?.action === 'reject' ? 'Why is it turned down?' : 'Hide this podcast?'}>
        <AppText style={styles.sheetText}>{reviewing?.action === 'reject' ? 'The podcaster is told, so say what to change.' : 'It leaves the app. The podcaster is told.'}</AppText>
        <TextField label={reviewing?.action === 'reject' ? 'Reason' : 'Reason (optional)'} value={reason} onChangeText={setReason} multiline maxLength={500} error={problem} />
        <View style={{ gap: 10 }}>
          <Button title={reviewing?.action === 'reject' ? 'Turn down' : 'Hide'} onPress={() => send(reviewing.podcast, reviewing.action, reason.trim())} loading={busy === reviewing?.podcast?.id} />
          <Button title="Cancel" variant="outline" onPress={() => setReviewing(null)} />
        </View>
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  notice: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: 10 },
  noticeText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.black },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, gap: 8, ...SHADOW },
  row: { flexDirection: 'row', gap: 12 },
  cover: { width: 64, height: 64, borderRadius: 14, backgroundColor: COLORS.gray100 },
  name: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 21, color: COLORS.black },
  summary: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray900 },
  note: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.gray900 },
  actions: { flexDirection: 'row', gap: 10, marginTop: 4 },
  btn: { flex: 1, minHeight: 42, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: COLORS.primaryPale, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
  btnMain: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  btnText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primary },
  sheetText: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray900, textAlign: 'center', marginBottom: 10 },
  more: { alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
