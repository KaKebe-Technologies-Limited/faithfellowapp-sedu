// Sermons: search and browse the sermons of the churches you follow (GET /api/v1/sermons). Each card
// opens the sermon in the app, where the video or audio plays right on the page.
import { useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { SermonListCard } from '../components/ListCards';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import { useFetch } from '../lib/useFetch';

export default function SermonsScreen() {
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const { data, loading, refreshing, error, reload, retry } = useFetch('/sermons', { params: { q: search || undefined, per_page: 30 } });
  const sermons = data?.data ?? [];

  return (
    <View style={styles.flex}>
      <ScreenHeader title="Sermons" subtitle="From the churches you follow" />
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput
          value={query}
          onChangeText={setQuery}
          onSubmitEditing={() => setSearch(query.trim())}
          returnKeyType="search"
          placeholder="Search sermons or preachers"
          placeholderTextColor={COLORS.gray500}
          style={styles.searchInput}
        />
      </View>

      <StateView
        loading={loading}
        error={error}
        onRetry={retry}
        empty={!loading && !error && sermons.length === 0}
        emptyIcon="mic-off-outline"
        emptyTitle={search ? 'No sermons match your search' : 'No sermons yet'}
        emptyMessage={search ? 'Try different words.' : 'Sermons from the churches you follow will appear here.'}
      >
        <FlatList
          data={sermons}
          keyExtractor={(sermon) => String(sermon.id)}
          contentContainerStyle={{ paddingTop: 4, paddingBottom: 24 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          renderItem={({ item }) => <SermonListCard sermon={item} />}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: SPACING.sm, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
});
