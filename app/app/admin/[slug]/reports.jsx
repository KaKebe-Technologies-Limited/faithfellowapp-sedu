// Moderation: things members reported (comments, posts, prayer requests, live chat). Hide what breaks the rules —
// the author is not told who reported — or dismiss a report that is fine. Data: /api/v1/admin/{church}/reports.
import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import Pill from '../../../components/Pill';
import ScreenHeader from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage } from '../../../lib/api';
import { timeAgo } from '../../../lib/format';
import { usePaged } from '../../../lib/usePaged';

export default function AdminReports() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const [status, setStatus] = useState('open');
  const list = usePaged(admin.path('/reports'), { status });

  async function act(report, action) {
    try {
      await admin.post(`/reports/${report.id}/${action}`);
      await list.reload();
    } catch (e) {
      Alert.alert('Could not do that', errorMessage(e));
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Reports" subtitle="Keep your community safe" />
      <View style={styles.tabs}>
        {[['open', 'To review'], ['actioned', 'Hidden'], ['dismissed', 'Dismissed']].map(([key, label]) => (
          <Chip key={key} label={label} on={status === key} onPress={() => setStatus(key)} />
        ))}
      </View>
      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="shield-checkmark-outline" emptyTitle={status === 'open' ? 'All clear' : 'Nothing here'} emptyMessage={status === 'open' ? 'No reports are waiting for you.' : undefined}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, gap: 12 }}>
          {list.items.map((r) => (
            <View key={r.id} style={styles.card}>
              <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
                <Pill label={r.reason_label} tone="urgent" />
                <Pill label={r.type} />
              </View>
              <AppText style={styles.content}>{r.content || '(no text)'}</AppText>
              {r.details ? <AppText variant="caption">“{r.details}”</AppText> : null}
              <AppText variant="caption">Reported {timeAgo(r.created_at)}{r.reporter ? ` by ${r.reporter}` : ''}</AppText>
              {status === 'open' ? (
                <View style={styles.btns}>
                  <Button title="Hide it" small onPress={() => act(r, 'hide')} />
                  <Button title="It is fine" small variant="outline" onPress={() => act(r, 'dismiss')} />
                </View>
              ) : null}
            </View>
          ))}
        </ScrollView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 6 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 8, ...SHADOW, shadowOpacity: 0.05 },
  content: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.black, backgroundColor: COLORS.canvas, borderRadius: RADIUS.md, padding: 10 },
  btns: { flexDirection: 'row', gap: 8, marginTop: 4 },
});
