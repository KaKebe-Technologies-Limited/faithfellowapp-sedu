// Platform admin → Churches: the applications waiting for approval and every church, found by name or town. Approve an application (the church is told),
// decline it, suspend a church that must stop, or bring one back. Declining and suspending ask for your password again.
// Data: GET /api/v1/platform/churches and POST …/{church}/approve|decline|suspend|reactivate.
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Chip from '../../components/Chip';
import ChurchAvatar from '../../components/ChurchAvatar';
import PasswordSheet from '../../components/platform/PasswordSheet';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { timeAgo } from '../../lib/format';
import { usePaged } from '../../lib/usePaged';

const FILTERS = [
  ['pending', 'Waiting'],
  ['active', 'Active'],
  ['suspended', 'Suspended'],
  ['archived', 'Declined'],
  ['', 'All'],
];
const LABEL = { pending: 'Waiting', active: 'Active', suspended: 'Suspended', archived: 'Declined' };

export default function PlatformChurches() {
  const params = useLocalSearchParams();
  const [status, setStatus] = useState(typeof params.status === 'string' ? params.status : 'pending');
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [asking, setAsking] = useState(null); // { church, action }
  const [notice, setNotice] = useState(null);
  const [busy, setBusy] = useState(null);
  const list = usePaged('/platform/churches', { status: status || undefined, q: search || undefined });

  async function approve(church) {
    setBusy(church.id);
    try {
      const { data } = await api.post(`/platform/churches/${church.slug}/approve`);
      setNotice(data.message);
      await list.reload();
    } catch (e) {
      Alert.alert('Could not approve', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  async function confirmed({ password, reason }) {
    const { data } = await api.post(`/platform/churches/${asking.church.slug}/${asking.action}`, { password, reason });
    setNotice(data.message);
    setAsking(null);
    await list.reload();
  }

  const ask = {
    decline: { title: 'Decline this application?', confirm: 'Decline', reason: 'optional', text: (c) => `${c.name} will not be approved. Add a reason if you like.` },
    suspend: { title: 'Suspend this church?', confirm: 'Suspend', reason: 'optional', text: (c) => `${c.name} and its members lose access until you bring it back.` },
    reactivate: { title: 'Bring this church back?', confirm: 'Reactivate', reason: false, text: (c) => `${c.name} will be active again.` },
  }[asking?.action];

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Churches" subtitle="Applications and every church" />
      <View style={{ paddingHorizontal: SPACING.md, gap: 10, paddingBottom: 8 }}>
        <View style={styles.search}>
          <Ionicons name="search" size={18} color={COLORS.gray700} />
          <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={query} onChangeText={setQuery} onSubmitEditing={() => setSearch(query.trim())} returnKeyType="search" placeholder="Search by name, town or pastor" placeholderTextColor={COLORS.gray500} style={styles.input} />
        </View>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
          {FILTERS.map(([key, label]) => (
            <Chip key={key || 'all'} label={label} on={status === key} onPress={() => setStatus(key)} />
          ))}
        </ScrollView>
        {notice ? (
          <Pressable onPress={() => setNotice(null)} style={styles.notice} accessibilityRole="button" accessibilityLabel="Dismiss message">
            <Ionicons name="checkmark-circle" size={18} color={COLORS.success} />
            <AppText style={styles.noticeText}>{notice}</AppText>
          </Pressable>
        ) : null}
      </View>

      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="business-outline" emptyTitle={status === 'pending' ? 'No applications waiting' : 'No churches here'}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingTop: 4, gap: 10, paddingBottom: 48 }}>
          {list.items.map((c) => (
            <View key={c.id} style={styles.card}>
              <View style={styles.row}>
                <ChurchAvatar church={c} size={46} />
                <View style={{ flex: 1, gap: 2 }}>
                  <AppText style={styles.name} numberOfLines={2}>
                    {c.name}
                  </AppText>
                  <AppText variant="caption" numberOfLines={1}>
                    {[c.city, c.denomination].filter(Boolean).join(' · ') || '—'}
                  </AppText>
                </View>
                <View style={[styles.pill, c.status === 'pending' && styles.pillWait, c.status === 'suspended' && styles.pillBad]}>
                  <AppText style={[styles.pillText, c.status === 'pending' && { color: COLORS.warningInk }, c.status === 'suspended' && { color: COLORS.danger }]}>{LABEL[c.status] ?? c.status}</AppText>
                </View>
              </View>
              {c.owner ? (
                <AppText variant="caption" numberOfLines={2}>
                  {c.status === 'pending' ? 'Applied' : 'Owner'}: {c.owner.name}
                  {c.owner.email ? ` · ${c.owner.email}` : ''}
                  {c.status === 'pending' && c.applied_at ? ` · ${timeAgo(c.applied_at)}` : ''}
                </AppText>
              ) : null}
              {c.status === 'active' ? <AppText variant="caption">{c.followers} {c.followers === 1 ? 'follower' : 'followers'}</AppText> : null}
              {c.status_reason ? <AppText style={styles.reason}>Reason: {c.status_reason}</AppText> : null}
              <View style={styles.actions}>
                {c.status === 'pending' ? (
                  <>
                    <Pressable onPress={() => approve(c)} disabled={busy === c.id} style={[styles.btn, styles.btnMain]} accessibilityRole="button" accessibilityLabel={`Approve ${c.name}`}>
                      <AppText style={[styles.btnText, { color: COLORS.onColor }]}>{busy === c.id ? 'Approving…' : 'Approve'}</AppText>
                    </Pressable>
                    <Pressable onPress={() => setAsking({ church: c, action: 'decline' })} style={styles.btn} accessibilityRole="button" accessibilityLabel={`Decline ${c.name}`}>
                      <AppText style={styles.btnText}>Decline</AppText>
                    </Pressable>
                  </>
                ) : null}
                {c.status === 'active' ? (
                  <Pressable onPress={() => setAsking({ church: c, action: 'suspend' })} style={styles.btn} accessibilityRole="button" accessibilityLabel={`Suspend ${c.name}`}>
                    <AppText style={[styles.btnText, { color: COLORS.danger }]}>Suspend</AppText>
                  </Pressable>
                ) : null}
                {c.status === 'suspended' ? (
                  <Pressable onPress={() => setAsking({ church: c, action: 'reactivate' })} style={[styles.btn, styles.btnMain]} accessibilityRole="button" accessibilityLabel={`Bring back ${c.name}`}>
                    <AppText style={[styles.btnText, { color: COLORS.onColor }]}>Bring back</AppText>
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

      <PasswordSheet visible={!!asking} title={ask?.title} message={asking ? ask.text(asking.church) : undefined} confirmLabel={ask?.confirm} reason={ask?.reason ?? false} onClose={() => setAsking(null)} onConfirm={confirmed} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  input: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  notice: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: 10 },
  noticeText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.black },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, gap: 6, ...SHADOW },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  name: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 21, color: COLORS.black },
  pill: { backgroundColor: COLORS.successMist, borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 4 },
  pillWait: { backgroundColor: COLORS.warningMist },
  pillBad: { backgroundColor: COLORS.dangerMist },
  pillText: { fontFamily: FONTS.bold, fontSize: 11.5, color: COLORS.success },
  reason: { fontFamily: FONTS.regular, fontSize: 13, color: COLORS.gray900 },
  actions: { flexDirection: 'row', gap: 10, marginTop: 6 },
  btn: { flex: 1, minHeight: 42, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: COLORS.primaryPale, alignItems: 'center', justifyContent: 'center' },
  btnMain: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  btnText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primary },
  more: { alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
