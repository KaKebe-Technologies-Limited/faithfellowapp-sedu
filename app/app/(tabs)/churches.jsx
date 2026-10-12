// Churches tab: churches you follow (GET /me) and a searchable directory to discover more
// (GET /churches). Follow / unfollow with POST / DELETE /churches/{slug}/subscription.
// A member can follow at most 3 churches; the server's message is shown if the limit is hit.
import { useState } from 'react';
import { Alert, FlatList, Pressable, RefreshControl, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import Card from '../../components/Card';
import ChurchAvatar from '../../components/ChurchAvatar';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { useFetch } from '../../lib/useFetch';

const TABS = [
  { key: 'mine', label: 'Following' },
  { key: 'discover', label: 'Discover' },
];

export default function ChurchesScreen() {
  const [tab, setTab] = useState('mine');
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [busySlug, setBusySlug] = useState(null);
  const me = useFetch('/me');
  const directory = useFetch('/churches', { params: { q: search || undefined, per_page: 30 }, enabled: tab === 'discover' });

  const mine = me.data?.data?.churches ?? me.data?.churches ?? [];
  const followedSlugs = new Set(mine.map((c) => c.slug));
  const churches = directory.data?.data ?? [];

  async function toggle(church, follow) {
    setBusySlug(church.slug);
    try {
      if (follow) await api.post(`/churches/${church.slug}/subscription`);
      else await api.delete(`/churches/${church.slug}/subscription`);
      await me.reload();
    } catch (e) {
      Alert.alert(follow ? 'Could not follow' : 'Could not unfollow', errorMessage(e));
    } finally {
      setBusySlug(null);
    }
  }

  function confirmUnfollow(church) {
    Alert.alert(`Unfollow ${church.name}?`, 'You will stop seeing their updates.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Unfollow', style: 'destructive', onPress: () => toggle(church, false) },
    ]);
  }

  const active = tab === 'mine' ? me : directory;
  const rows = tab === 'mine' ? mine : churches;

  return (
    <View style={styles.flex}>
      <View style={styles.tabs}>
        {TABS.map((t) => (
          <Pressable key={t.key} onPress={() => setTab(t.key)} style={[styles.tab, tab === t.key && styles.tabActive]} accessibilityRole="tab" accessibilityState={{ selected: tab === t.key }}>
            <AppText variant="label" style={{ color: tab === t.key ? COLORS.white : COLORS.primary }}>
              {t.label}
            </AppText>
          </Pressable>
        ))}
        <AppText variant="caption" style={styles.count}>
          {mine.length}/3 followed
        </AppText>
      </View>

      {tab === 'discover' ? (
        <View style={styles.search}>
          <Ionicons name="search" size={18} color={COLORS.gray700} />
          <TextInput
            value={query}
            onChangeText={setQuery}
            onSubmitEditing={() => setSearch(query.trim())}
            returnKeyType="search"
            placeholder="Search by name or pastor"
            placeholderTextColor={COLORS.gray500}
            style={styles.searchInput}
          />
        </View>
      ) : null}

      <StateView
        loading={active.loading}
        error={active.error}
        onRetry={active.retry}
        empty={!active.loading && !active.error && rows.length === 0}
        emptyIcon={tab === 'mine' ? 'business-outline' : 'search-outline'}
        emptyTitle={tab === 'mine' ? 'You are not following a church yet' : 'No churches found'}
        emptyMessage={tab === 'mine' ? 'Browse the directory and follow up to three churches.' : 'Try another name or clear the search.'}
        emptyAction={tab === 'mine' ? { title: 'Discover churches', onPress: () => setTab('discover') } : undefined}
      >
        <FlatList
          data={rows}
          keyExtractor={(church) => String(church.id)}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={active.refreshing} onRefresh={active.reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          renderItem={({ item: church }) => {
            const following = followedSlugs.has(church.slug);
            const place = [church.location?.city ?? church.city, church.denomination].filter(Boolean).join(' · ');
            return (
              <Pressable onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: church.slug } })} accessibilityLabel={"Open " + church.name}>
              <Card style={styles.row}>
                <ChurchAvatar church={church} size={48} />
                <View style={styles.rowText}>
                  <AppText variant="heading" numberOfLines={2}>
                    {church.name}
                  </AppText>
                  {place ? <AppText variant="caption">{place}</AppText> : null}
                </View>
                {following ? (
                  <Button title="Following" variant="outline" small loading={busySlug === church.slug} onPress={() => confirmUnfollow(church)} />
                ) : (
                  <Button title="Follow" small loading={busySlug === church.slug} onPress={() => toggle(church, true)} />
                )}
              </Card>
              </Pressable>
            );
          }}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  tabs: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, padding: SPACING.md, paddingBottom: SPACING.sm },
  tab: { paddingHorizontal: SPACING.md, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  tabActive: { backgroundColor: COLORS.primary },
  count: { marginLeft: 'auto' },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, marginHorizontal: SPACING.md, marginBottom: SPACING.sm, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  list: { padding: SPACING.md, paddingTop: SPACING.sm, gap: SPACING.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  rowText: { flex: 1 },
});
