// Platform admin → Reports: what members reported (comments, prayers, posts, testimonies, live chat). Hide the content, dismiss the report because it is fine, or
// suspend the person who wrote it (asks for your password again). Data: GET /api/v1/platform/reports and POST /api/v1/platform/reports/{id}.
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../../components/AppText';
import Chip from '../../components/Chip';
import PasswordSheet from '../../components/platform/PasswordSheet';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { timeAgo } from '../../lib/format';
import { usePaged } from '../../lib/usePaged';

const KINDS = { comment: 'Comment', podcast_comment: 'Podcast comment', prayer: 'Prayer request', post: 'Post', testimony: 'Testimony', live_chat: 'Live chat' };

export default function PlatformReports() {
  const [status, setStatus] = useState('open');
  const [suspending, setSuspending] = useState(null);
  const [notice, setNotice] = useState(null);
  const [busy, setBusy] = useState(null);
  const list = usePaged('/platform/reports', { status });

  async function act(report, action, extra = {}) {
    setBusy(report.id);
    try {
      const { data } = await api.post(`/platform/reports/${report.id}`, { action, ...extra });
      setNotice(data.message);
      await list.reload();
    } catch (e) {
      if (extra.password !== undefined) throw e;
      Alert.alert('Could not do that', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Reports" subtitle="What members asked you to look at" />
      <View style={{ paddingHorizontal: SPACING.md, gap: 10, paddingBottom: 8 }}>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          {[['open', 'Open'], ['actioned', 'Hidden'], ['dismissed', 'Dismissed']].map(([key, label]) => (
            <Chip key={key} label={label} on={status === key} onPress={() => setStatus(key)} />
          ))}
        </View>
        {notice ? (
          <Pressable onPress={() => setNotice(null)} style={styles.notice} accessibilityRole="button" accessibilityLabel="Dismiss message">
            <Ionicons name="checkmark-circle" size={18} color={COLORS.success} />
            <AppText style={styles.noticeText}>{notice}</AppText>
          </Pressable>
        ) : null}
      </View>

      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="shield-checkmark-outline" emptyTitle={status === 'open' ? 'Nothing to look at' : 'Nothing here'} emptyMessage={status === 'open' ? 'When members report something, it appears here.' : undefined}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingTop: 4, gap: 10, paddingBottom: 48 }}>
          {list.items.map((r) => (
            <View key={r.id} style={styles.card}>
              <View style={styles.head}>
                <View style={styles.kind}>
                  <AppText style={styles.kindText}>{KINDS[r.kind] ?? r.kind}</AppText>
                </View>
                <AppText style={styles.reason}>{r.reason}</AppText>
                <AppText variant="caption">{timeAgo(r.at)}</AppText>
              </View>
              <AppText style={styles.excerpt}>{r.excerpt}</AppText>
              <AppText variant="caption">
                {r.author ? `By ${r.author.name}` : ''}
                {r.church ? `${r.author ? ' · ' : ''}${r.church}` : ''}
                {r.reporter ? ` · reported by ${r.reporter}` : ''}
              </AppText>
              {r.details ? <AppText style={styles.details}>“{r.details}”</AppText> : null}
              {r.status === 'open' ? (
                <View style={styles.actions}>
                  <Pressable onPress={() => act(r, 'hide')} disabled={busy === r.id} style={[styles.btn, styles.btnMain]} accessibilityRole="button" accessibilityLabel="Hide this content">
                    <AppText style={[styles.btnText, { color: COLORS.onColor }]}>Hide</AppText>
                  </Pressable>
                  <Pressable onPress={() => act(r, 'dismiss')} disabled={busy === r.id} style={styles.btn} accessibilityRole="button" accessibilityLabel="Dismiss the report">
                    <AppText style={styles.btnText}>It is fine</AppText>
                  </Pressable>
                  {r.author ? (
                    <Pressable onPress={() => setSuspending(r)} disabled={busy === r.id} style={styles.btn} accessibilityRole="button" accessibilityLabel={`Suspend ${r.author.name}`}>
                      <AppText style={[styles.btnText, { color: COLORS.danger }]}>Suspend</AppText>
                    </Pressable>
                  ) : null}
                </View>
              ) : null}
            </View>
          ))}
          {list.hasMore ? (
            <Pressable onPress={list.loadMore} style={styles.more} accessibilityRole="button">
              <AppText style={styles.moreText}>Load more</AppText>
            </Pressable>
          ) : null}
        </ScrollView>
      </StateView>

      <PasswordSheet
        visible={!!suspending}
        title="Suspend this person?"
        message={suspending ? `${suspending.author?.name} will be signed out and cannot use the app, and what they wrote is hidden.` : undefined}
        confirmLabel="Suspend"
        onClose={() => setSuspending(null)}
        onConfirm={async ({ password }) => {
          await act(suspending, 'suspend', { password });
          setSuspending(null);
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  notice: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: 10 },
  noticeText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.black },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, gap: 6, ...SHADOW },
  head: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  kind: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 3 },
  kindText: { fontFamily: FONTS.bold, fontSize: 11.5, color: COLORS.primary },
  reason: { flex: 1, fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.gray900 },
  excerpt: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.ink },
  details: { fontFamily: FONTS.regular, fontSize: 13, color: COLORS.gray900 },
  actions: { flexDirection: 'row', gap: 8, marginTop: 6 },
  btn: { flex: 1, minHeight: 42, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: COLORS.primaryPale, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
  btnMain: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  btnText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  more: { alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
