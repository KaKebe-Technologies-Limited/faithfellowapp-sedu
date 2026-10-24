// The prayer queue: requests waiting for your approval before they go to the whole church, private requests from
// members to their pastors (reply to them), and what is on the wall. Approve, keep private, reply or hide.
// Data: /api/v1/admin/{church}/prayer.
import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Avatar from '../../../components/Avatar';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage } from '../../../lib/api';
import { timeAgo } from '../../../lib/format';
import { usePaged } from '../../../lib/usePaged';

const TABS = [['pending', 'To approve'], ['private', 'Private'], ['wall', 'On the wall']];

export default function AdminPrayer() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const [tab, setTab] = useState('pending');
  const list = usePaged(admin.path('/prayer'), { status: tab });
  const [reply, setReply] = useState(null);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(null);

  async function act(id, action, body) {
    setBusy(`${id}${action}`);
    try {
      await admin.post(`/prayer/${id}/${action}`, body);
      setReply(null);
      setText('');
      await list.reload();
    } catch (e) {
      Alert.alert('Could not do that', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Prayer queue" subtitle="Pray with your people" />
      <View style={styles.tabs}>
        {TABS.map(([key, label]) => (
          <Chip key={key} label={label} on={tab === key} onPress={() => setTab(key)} />
        ))}
      </View>
      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="hand-left-outline" emptyTitle={tab === 'pending' ? 'Nothing waiting' : 'No requests here'} emptyMessage={tab === 'pending' ? 'New prayer requests that need your approval appear here.' : undefined}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, gap: 12 }}>
          {list.items.map((p) => (
            <View key={p.id} style={styles.card}>
              <View style={styles.head}>
                <Avatar person={{ name: p.author.anonymous ? 'Anonymous' : p.author.name, avatar_url: p.author.avatar_url }} size={38} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.name}>{p.author.name ?? 'Member'}{p.author.anonymous ? ' (asked anonymously)' : ''}</AppText>
                  <AppText variant="caption">{timeAgo(p.created_at)} · {p.visibility === 'pastor' ? 'Private to pastors' : p.visibility === 'public' ? 'Public' : 'Church only'}</AppText>
                </View>
              </View>
              {p.title ? <AppText style={styles.title}>{p.title}</AppText> : null}
              <AppText style={styles.body}>{p.body}</AppText>
              {p.response ? <AppText style={styles.response}>Your reply: {p.response}</AppText> : null}
              <View style={styles.btns}>
                {p.status === 'pending' ? (
                  <>
                    <Button title="Share with church" small onPress={() => act(p.id, 'approve')} loading={busy === `${p.id}approve`} />
                    <Button title="Keep private" small variant="outline" onPress={() => act(p.id, 'decline')} loading={busy === `${p.id}decline`} />
                  </>
                ) : null}
                {p.status !== 'hidden' ? <Button title={p.response ? 'Reply again' : 'Reply privately'} small variant="outline" onPress={() => { setReply(p); setText(''); }} /> : null}
                {p.status === 'open' || p.status === 'answered' ? <Button title="Hide" small variant="outline" onPress={() => act(p.id, 'hide')} /> : null}
              </View>
            </View>
          ))}
        </ScrollView>
      </StateView>

      <Sheet visible={!!reply} onClose={() => setReply(null)} title="Reply privately">
        <AppText variant="caption" style={{ marginBottom: 10 }}>{reply?.body}</AppText>
        <TextField placeholder="We are praying with you…" value={text} onChangeText={setText} multiline numberOfLines={5} maxLength={3000} style={{ minHeight: 110 }} />
        <Button title="Send reply" onPress={() => act(reply.id, 'respond', { response: text.trim() })} loading={busy === `${reply?.id}respond`} disabled={!text.trim()} />
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 6 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 8, borderTopWidth: 4, borderTopColor: COLORS.primaryPale, ...SHADOW, shadowOpacity: 0.05 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  name: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.black },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  body: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.ink },
  response: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 14, color: COLORS.gray900, backgroundColor: COLORS.canvas, borderRadius: RADIUS.md, padding: 10 },
  btns: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 },
});
