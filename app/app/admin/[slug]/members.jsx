// Members: everyone following your church. Approve people waiting to join, set where they are in their journey
// (visitor, new member, member, worker, leader) and block someone who should not follow. Search by name or phone.
// Data: /api/v1/admin/{church}/members.
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Avatar from '../../../components/Avatar';
import Chip from '../../../components/Chip';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage } from '../../../lib/api';
import { formatDate } from '../../../lib/format';
import { usePaged } from '../../../lib/usePaged';

export default function AdminMembers() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const [status, setStatus] = useState('');
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const list = usePaged(admin.path('/members'), { status: status || undefined, q: search || undefined });
  const [picked, setPicked] = useState(null);

  async function update(member, body) {
    try {
      await admin.put(`/members/${member.id}`, body);
      setPicked(null);
      await list.reload();
    } catch (e) {
      Alert.alert('Could not update', errorMessage(e));
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Members" subtitle="People who follow your church" />
      <View style={{ paddingHorizontal: SPACING.md, gap: 10, paddingBottom: 8 }}>
        <View style={styles.search}>
          <Ionicons name="search" size={18} color={COLORS.gray700} />
          <TextInput value={query} onChangeText={setQuery} onSubmitEditing={() => setSearch(query.trim())} returnKeyType="search" placeholder="Search by name or phone" placeholderTextColor={COLORS.gray500} style={styles.input} />
        </View>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {[['', 'Active & waiting'], ['pending', 'Waiting'], ['active', 'Active'], ['blocked', 'Blocked']].map(([key, label]) => (
            <Chip key={key} label={label} on={status === key} onPress={() => setStatus(key)} />
          ))}
        </View>
      </View>
      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="people-outline" emptyTitle="No members found">
        <ScrollView contentContainerStyle={{ padding: SPACING.md, gap: 10 }}>
          {list.items.map((m) => (
            <Pressable key={m.id} onPress={() => setPicked(m)} style={styles.card} accessibilityRole="button" accessibilityLabel={`${m.name}, ${m.status}`}>
              <Avatar person={m} size={46} />
              <View style={{ flex: 1, gap: 2 }}>
                <AppText style={styles.name}>{m.name}</AppText>
                <AppText variant="caption" numberOfLines={1}>{[m.city, m.phone].filter(Boolean).join(' · ') || '—'}</AppText>
                <AppText variant="caption">{m.stage_label} · since {formatDate(m.since)}</AppText>
              </View>
              {m.status === 'pending' ? (
                <View style={styles.pending}>
                  <AppText style={styles.pendingText}>Waiting</AppText>
                </View>
              ) : m.status === 'blocked' ? (
                <Ionicons name="ban-outline" size={20} color={COLORS.danger} />
              ) : (
                <Ionicons name="ellipsis-horizontal" size={20} color={COLORS.gray500} />
              )}
            </Pressable>
          ))}
          {list.hasMore ? (
            <Pressable onPress={list.loadMore} style={styles.more} accessibilityRole="button">
              <AppText style={styles.moreText}>Load more</AppText>
            </Pressable>
          ) : null}
        </ScrollView>
      </StateView>

      <Sheet visible={!!picked} onClose={() => setPicked(null)} title={picked?.name}>
        {picked?.status === 'pending' ? (
          <Pressable onPress={() => update(picked, { action: 'approve' })} style={styles.action} accessibilityRole="button">
            <Ionicons name="checkmark-circle-outline" size={22} color={COLORS.success} />
            <AppText style={styles.actionText}>Approve this person</AppText>
          </Pressable>
        ) : null}
        {picked?.status === 'active' || picked?.status === 'pending' ? (
          <>
            <AppText style={styles.label}>Where are they?</AppText>
            <View style={styles.wrap}>
              {['visitor', 'new_member', 'member', 'worker', 'leader'].map((s) => (
                <Chip key={s} label={s.replace('_', ' ')} on={picked.stage === s} onPress={() => update(picked, { stage: s })} />
              ))}
            </View>
          </>
        ) : null}
        {picked?.status === 'blocked' ? (
          <Pressable onPress={() => update(picked, { action: 'unblock' })} style={styles.action} accessibilityRole="button">
            <Ionicons name="lock-open-outline" size={22} color={COLORS.primary} />
            <AppText style={styles.actionText}>Unblock</AppText>
          </Pressable>
        ) : (
          <Pressable onPress={() => update(picked, { action: 'block' })} style={styles.action} accessibilityRole="button">
            <Ionicons name="ban-outline" size={22} color={COLORS.danger} />
            <AppText style={[styles.actionText, { color: COLORS.danger }]}>Block from following</AppText>
          </Pressable>
        )}
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  input: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, ...SHADOW, shadowOpacity: 0.05 },
  name: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  pending: { backgroundColor: '#FFF3E0', borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 4 },
  pendingText: { fontFamily: FONTS.bold, fontSize: 12, color: '#C25B00' },
  more: { alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 9 },
  moreText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.primary },
  action: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 13 },
  actionText: { fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black },
  label: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black, marginTop: 8, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 6 },
});
