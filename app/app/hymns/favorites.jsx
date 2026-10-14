// Your favourite hymns, newest first. Tap the star again to take one out.
import { useCallback, useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import HymnRow from '../../components/hymn/HymnRow';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';

export default function FavoriteHymnsScreen() {
  const [state, setState] = useState({ loading: true, refreshing: false, error: null, hymns: [] });

  const load = useCallback(async (refreshing = false) => {
    setState((s) => ({ ...s, loading: !refreshing && !s.hymns.length, refreshing, error: null }));
    try {
      const { data } = await api.get('/hymns/favorites');
      setState({ loading: false, refreshing: false, error: null, hymns: data.data });
    } catch (e) {
      setState((s) => ({ ...s, loading: false, refreshing: false, error: errorMessage(e) }));
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  async function remove(hymn) {
    setState((s) => ({ ...s, hymns: s.hymns.filter((h) => h.id !== hymn.id) }));
    try {
      await api.delete(`/hymns/${hymn.id}/favorite`);
    } catch {
      load(true);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="My favourites" subtitle="Hymns you love" />
      <StateView loading={state.loading} error={state.error} onRetry={() => load()} empty={!state.loading && !state.error && !state.hymns.length} emptyIcon="star-outline" emptyTitle="No favourites yet" emptyMessage="Open a hymn and tap the star to keep it here.">
        <FlatList
          data={state.hymns}
          keyExtractor={(h) => String(h.id)}
          contentContainerStyle={{ paddingTop: 8, paddingBottom: 40 }}
          refreshControl={<RefreshControl refreshing={state.refreshing} onRefresh={() => load(true)} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          renderItem={({ item }) => <HymnRow hymn={item} showHymnal onToggle={remove} />}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
});
