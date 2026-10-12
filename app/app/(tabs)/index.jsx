// Feed tab: GET /api/v1/feed — latest sermons, events, updates and more from the churches you follow.
// Pull down to refresh, scroll to the end to load older items. Shows loading, error and empty states.
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet } from 'react-native';
import { router } from 'expo-router';
import FeedItem from '../../components/FeedItem';
import StateView from '../../components/StateView';
import { COLORS, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';

export default function FeedScreen() {
  const [items, setItems] = useState([]);
  const [next, setNext] = useState(null);
  const [status, setStatus] = useState({ loading: true, refreshing: false, more: false, error: null });
  const busy = useRef(false);

  const load = useCallback(async (mode = 'load', before = null) => {
    if (busy.current) return;
    busy.current = true;
    setStatus((s) => ({ ...s, loading: mode === 'load', refreshing: mode === 'refresh', more: mode === 'more', error: null }));
    try {
      const { data } = await api.get('/feed', { params: before ? { before } : {} });
      setItems((current) => (mode === 'more' ? [...current, ...data.data] : data.data));
      setNext(data.next_before ?? null);
      setStatus({ loading: false, refreshing: false, more: false, error: null });
    } catch (e) {
      setStatus({ loading: false, refreshing: false, more: false, error: errorMessage(e) });
    } finally {
      busy.current = false;
    }
  }, []);

  useEffect(() => {
    load('load');
  }, [load]);

  const showList = !status.loading && !(status.error && !items.length);

  return (
    <StateView
      loading={status.loading}
      error={!items.length ? status.error : null}
      onRetry={() => load('load')}
      empty={showList && items.length === 0}
      emptyIcon="newspaper-outline"
      emptyTitle="Your feed is empty"
      emptyMessage="Follow a church to see its sermons, events and updates here."
      emptyAction={{ title: 'Find churches', onPress: () => router.push('/(tabs)/churches') }}
    >
      <FlatList
        data={items}
        keyExtractor={(entry, index) => `${entry.type}-${entry.item?.id ?? index}-${entry.at}`}
        renderItem={({ item }) => <FeedItem entry={item} />}
        contentContainerStyle={styles.list}
        refreshControl={<RefreshControl refreshing={status.refreshing} onRefresh={() => load('refresh')} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
        onEndReachedThreshold={0.4}
        onEndReached={() => next && !busy.current && load('more', next)}
        ListFooterComponent={status.more ? <ActivityIndicator color={COLORS.primary} style={styles.more} /> : null}
      />
    </StateView>
  );
}

const styles = StyleSheet.create({
  list: { padding: SPACING.md },
  more: { paddingVertical: SPACING.lg },
});
