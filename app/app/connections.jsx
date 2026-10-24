// Your connections: the people you are connected to (tap to chat), requests waiting for your answer, and the
// requests you sent (you can cancel them). Data: GET /api/v1/connections.
import { useCallback, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../components/AppText';
import Avatar from '../components/Avatar';
import { PersonRow } from '../components/PersonCard';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import api, { errorMessage } from '../lib/api';
import { useFetch } from '../lib/useFetch';

const TABS = [['friends', 'Connected'], ['incoming', 'Requests'], ['outgoing', 'Sent']];

export default function ConnectionsScreen() {
  const [tab, setTab] = useState('friends');
  const { data, loading, refreshing, error, reload, retry } = useFetch('/connections');
  const lists = data?.data;

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  async function answer(c, action) {
    try {
      await api.post(`/connections/${c.connection_id}/respond`, { action });
      await reload();
    } catch (e) {
      Alert.alert('Could not answer', errorMessage(e));
    }
  }

  const cancel = async (c) => {
    await api.delete(`/people/${c.id}/connection`).catch(() => {});
    reload();
  };

  const rows = lists?.[tab] ?? [];

  return (
    <View style={styles.screen}>
      <ScreenHeader title="My connections" subtitle="People you walk with" />
      <View style={styles.tabs}>
        {TABS.map(([key, label]) => (
          <Pressable key={key} onPress={() => setTab(key)} style={[styles.tab, tab === key && styles.tabOn]} accessibilityRole="tab" accessibilityState={{ selected: tab === key }}>
            <AppText style={[styles.tabText, tab === key && { color: COLORS.onColor }]}>
              {label}
              {lists?.[key]?.length ? ` · ${lists[key].length}` : ''}
            </AppText>
          </Pressable>
        ))}
      </View>
      <StateView loading={loading && !lists} error={!lists ? error : null} onRetry={retry} empty={!!lists && rows.length === 0} emptyIcon="people-outline" emptyTitle={tab === 'friends' ? 'No connections yet' : tab === 'incoming' ? 'No requests right now' : 'No requests sent'} emptyMessage={tab === 'friends' ? 'Find believers in People and say hey.' : undefined} emptyAction={tab === 'friends' ? { title: 'Find people', onPress: () => router.replace('/people') } : undefined}>
        <ScrollView contentContainerStyle={{ paddingBottom: 32, paddingTop: 4 }} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
          {rows.map((c) =>
            tab === 'friends' ? (
              <PersonRow key={c.connection_id} person={c} />
            ) : tab === 'incoming' ? (
              <View key={c.connection_id} style={styles.request}>
                <Pressable onPress={() => router.push({ pathname: '/person/[id]', params: { id: String(c.id) } })} style={styles.reqTop} accessibilityLabel={`${c.name}, view profile`}>
                  <Avatar person={c} size={50} />
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.name}>{c.name}</AppText>
                    <AppText variant="caption" numberOfLines={1}>
                      {[c.city, c.age_range].filter(Boolean).join(' · ')}
                    </AppText>
                  </View>
                </Pressable>
                {c.message ? <AppText style={styles.message}>“{c.message}”</AppText> : null}
                <View style={styles.btns}>
                  <Pressable onPress={() => answer(c, 'accept')} style={styles.accept} accessibilityRole="button" accessibilityLabel={`Accept ${c.name}`}>
                    <AppText style={styles.acceptText}>Accept</AppText>
                  </Pressable>
                  <Pressable onPress={() => answer(c, 'decline')} style={styles.decline} accessibilityRole="button" accessibilityLabel={`Decline ${c.name}`}>
                    <AppText style={styles.declineText}>Not now</AppText>
                  </Pressable>
                </View>
              </View>
            ) : (
              <PersonRow key={c.connection_id} person={c}>
                <Pressable onPress={() => cancel(c)} style={styles.decline} accessibilityRole="button" accessibilityLabel={`Cancel request to ${c.name}`}>
                  <AppText style={styles.declineText}>Cancel</AppText>
                </Pressable>
              </PersonRow>
            ),
          )}
        </ScrollView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 10 },
  tab: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  tabOn: { backgroundColor: COLORS.primary },
  tabText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  request: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, marginHorizontal: SPACING.md, marginBottom: 10, gap: 10 },
  reqTop: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  name: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  message: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 14, lineHeight: 20, color: COLORS.gray900, backgroundColor: COLORS.canvas, borderRadius: RADIUS.md, padding: 10 },
  btns: { flexDirection: 'row', gap: 10 },
  accept: { flex: 1, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, alignItems: 'center', paddingVertical: 11 },
  acceptText: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.onColor },
  decline: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, alignItems: 'center', paddingHorizontal: 18, paddingVertical: 10 },
  declineText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.primary },
});
