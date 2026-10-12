// Sermons tab: GET /api/v1/sermons with search. Tap a sermon to play its audio in the mini player
// (expo-audio); sermons with only video open in the browser. Loading, error and empty states included.
import { useState } from 'react';
import { FlatList, Linking, Pressable, RefreshControl, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import AppText from '../../components/AppText';
import Card from '../../components/Card';
import SermonPlayer from '../../components/SermonPlayer';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { formatDate, formatDuration } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function SermonsScreen() {
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [playing, setPlaying] = useState(null);
  const { data, loading, refreshing, error, reload, retry } = useFetch('/sermons', { params: { q: search || undefined, per_page: 30 } });
  const sermons = data?.data ?? [];

  function open(sermon) {
    if (sermon.audio_url) setPlaying(sermon);
    else if (sermon.video_url || sermon.web_url) Linking.openURL(sermon.video_url || sermon.web_url);
  }

  return (
    <View style={styles.flex}>
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput
          value={query}
          onChangeText={setQuery}
          onSubmitEditing={() => setSearch(query.trim())}
          returnKeyType="search"
          placeholder="Search sermons"
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
          contentContainerStyle={[styles.list, playing && { paddingBottom: 120 }]}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          renderItem={({ item: sermon }) => {
            const active = playing?.id === sermon.id;
            return (
              <Pressable onPress={() => open(sermon)} accessibilityRole="button" accessibilityLabel={`Play ${sermon.title}`}>
                <Card style={[styles.row, active && styles.rowActive]}>
                  <View style={[styles.icon, active && { backgroundColor: COLORS.primary }]}>
                    <Ionicons name={sermon.audio_url ? (active ? 'volume-high' : 'play') : 'videocam'} size={20} color={active ? COLORS.white : COLORS.primary} />
                  </View>
                  <View style={styles.text}>
                    <AppText variant="heading" numberOfLines={2}>
                      {sermon.title}
                    </AppText>
                    <AppText variant="caption" numberOfLines={1}>
                      {[sermon.preacher, sermon.church?.name].filter(Boolean).join(' · ')}
                    </AppText>
                    <AppText variant="caption">{[formatDate(sermon.published_at), sermon.duration_seconds ? formatDuration(sermon.duration_seconds) : null].filter(Boolean).join(' · ')}</AppText>
                  </View>
                </Card>
              </Pressable>
            );
          }}
        />
      </StateView>

      {playing ? (
        <View style={styles.player}>
          <SermonPlayer key={playing.id} sermon={playing} onClose={() => setPlaying(null)} />
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: SPACING.sm, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  list: { padding: SPACING.md, paddingTop: SPACING.sm, gap: SPACING.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  rowActive: { borderWidth: 1.5, borderColor: COLORS.primary },
  icon: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  text: { flex: 1, gap: 2 },
  player: { position: 'absolute', left: SPACING.md, right: SPACING.md, bottom: SPACING.md },
});
