// Sermons: a banner of preachers at the top, then the preachers, the newest sermons in a sideways row, the most listened as a numbered list, the
// series, and the whole list with a picture beside each sermon. A round play button on a sermon plays its audio at once, in the app's player, with no
// video and no YouTube. Search, a preacher or a series narrows everything to a plain list.
// Data: GET /api/v1/sermons/browse (the banner and the rows) and GET /api/v1/sermons (the list, page by page).
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../components/AppText';
import PreacherStrip from '../components/sermons/PreacherStrip';
import SermonHero from '../components/sermons/SermonHero';
import { RankRow, SermonRow, SermonTile, SeriesCard } from '../components/sermons/SermonParts';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, MAX_FONT_SCALE, RADIUS, SPACING } from '../constants/theme';
import api, { errorMessage } from '../lib/api';
import { useFetch } from '../lib/useFetch';

/** The full list, page by page, for the chosen search / preacher / series. */
function useSermonList(params) {
  const key = JSON.stringify(params);
  const [state, setState] = useState({ items: [], page: 0, last: 1, loading: true, more: false, error: null });

  const load = useCallback(
    async (page) => {
      setState((s) => (page === 1 ? { ...s, loading: true, error: null } : { ...s, more: true }));
      try {
        const { data } = await api.get('/sermons', { params: { ...JSON.parse(key), page, per_page: 20 } });
        setState((s) => ({ items: page === 1 ? data.data : [...s.items, ...data.data.filter((x) => !s.items.some((y) => y.id === x.id))], page, last: data.meta?.last_page ?? 1, loading: false, more: false, error: null }));
      } catch (e) {
        setState((s) => ({ ...s, loading: false, more: false, error: errorMessage(e) }));
      }
    },
    [key],
  );

  useEffect(() => {
    load(1);
  }, [load]);

  return { ...state, reload: () => load(1), loadMore: () => !state.loading && !state.more && state.page < state.last && load(state.page + 1) };
}

function SectionTitle({ title, hint, style }) {
  return (
    <View style={[styles.sectionHead, style]}>
      <AppText style={styles.sectionTitle}>{title}</AppText>
      {hint ? <AppText variant="caption">{hint}</AppText> : null}
    </View>
  );
}

function FilterChip({ label, onClear }) {
  return (
    <Pressable onPress={onClear} style={styles.chip} accessibilityRole="button" accessibilityLabel={`Remove filter ${label}`}>
      <AppText style={styles.chipText} numberOfLines={1}>
        {label}
      </AppText>
      <Ionicons name="close-circle" size={17} color={COLORS.primary} />
    </Pressable>
  );
}

export default function SermonsScreen() {
  const browse = useFetch('/sermons/browse');
  const [text, setText] = useState('');
  const [filter, setFilter] = useState({ q: '', preacher: null, series: null });
  const filtering = Boolean(filter.q || filter.preacher || filter.series);
  const list = useSermonList({ q: filter.q || undefined, preacher: filter.preacher || undefined, series: filter.series?.id });
  const sections = browse.data?.data;
  const empty = sections && sections.total === 0;

  const pick = (patch) => setFilter((f) => ({ ...f, ...patch }));
  const reload = () => {
    browse.reload();
    list.reload();
  };

  const header = (
    <View>
      {!filtering && sections ? <SermonHero sermons={sections.featured} /> : null}

      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput
          maxFontSizeMultiplier={MAX_FONT_SCALE}
          value={text}
          onChangeText={setText}
          onSubmitEditing={() => pick({ q: text.trim() })}
          returnKeyType="search"
          placeholder="Search sermons or preachers"
          placeholderTextColor={COLORS.gray500}
          style={styles.searchInput}
        />
        {text ? (
          <Pressable
            onPress={() => {
              setText('');
              pick({ q: '' });
            }}
            hitSlop={8}
            accessibilityLabel="Clear the search"
          >
            <Ionicons name="close-circle" size={18} color={COLORS.gray500} />
          </Pressable>
        ) : null}
      </View>

      {sections?.preachers?.length ? (
        <View style={{ marginBottom: SPACING.md }}>
          <SectionTitle title="Preachers" />
          <PreacherStrip preachers={sections.preachers} selected={filter.preacher} onSelect={(name) => pick({ preacher: name })} />
        </View>
      ) : null}

      {filtering ? (
        <View style={styles.chips}>
          {filter.q ? <FilterChip label={`“${filter.q}”`} onClear={() => (setText(''), pick({ q: '' }))} /> : null}
          {filter.preacher ? <FilterChip label={filter.preacher} onClear={() => pick({ preacher: null })} /> : null}
          {filter.series ? <FilterChip label={`Series: ${filter.series.title}`} onClear={() => pick({ series: null })} /> : null}
        </View>
      ) : sections ? (
        <>
          {sections.latest.length ? (
            <View style={{ marginBottom: SPACING.lg }}>
              <SectionTitle title="Latest sermons" hint="Swipe for more" />
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
                {sections.latest.map((s) => (
                  <SermonTile key={s.id} sermon={s} list={sections.latest} />
                ))}
              </ScrollView>
            </View>
          ) : null}

          {sections.popular.length ? (
            <View style={{ marginBottom: SPACING.md }}>
              <SectionTitle title="Most listened" />
              {sections.popular.slice(0, 5).map((s, i) => (
                <RankRow key={s.id} sermon={s} rank={i + 1} list={sections.popular} />
              ))}
            </View>
          ) : null}

          {sections.series.length ? (
            <View style={{ marginBottom: SPACING.lg }}>
              <SectionTitle title="Series" />
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
                {sections.series.map((s) => (
                  <SeriesCard key={s.id} series={s} onPress={(series) => pick({ series })} />
                ))}
              </ScrollView>
            </View>
          ) : null}
        </>
      ) : null}

      <SectionTitle title={filtering ? (list.loading ? 'Searching…' : `${list.items.length}${list.page < list.last ? '+' : ''} ${list.items.length === 1 ? 'sermon' : 'sermons'}`) : 'All sermons'} style={{ marginTop: filtering ? 0 : SPACING.sm }} />
    </View>
  );

  return (
    <View style={styles.flex}>
      <ScreenHeader title="Sermons" subtitle="Hear the word, wherever you are" />
      <StateView loading={browse.loading && !sections} error={browse.error && !sections ? browse.error : null} onRetry={browse.retry} empty={Boolean(empty) && !filtering} emptyIcon="mic-outline" emptyTitle="No sermons yet" emptyMessage="Sermons from the churches you follow will appear here, with the preachers and a way to listen to each one.">
        <FlatList
          data={list.items}
          keyExtractor={(s) => String(s.id)}
          ListHeaderComponent={header}
          renderItem={({ item }) => <SermonRow sermon={item} list={list.items} />}
          ListEmptyComponent={
            list.loading ? (
              <ActivityIndicator color={COLORS.primary} style={{ marginTop: SPACING.lg }} />
            ) : list.error ? (
              <AppText style={styles.note}>{list.error}</AppText>
            ) : (
              <AppText style={styles.note}>{filtering ? 'No sermons match. Try different words, or take a filter off.' : 'No sermons yet.'}</AppText>
            )
          }
          ListFooterComponent={list.more ? <ActivityIndicator color={COLORS.primary} style={{ marginVertical: SPACING.md }} /> : null}
          onEndReached={list.loadMore}
          onEndReachedThreshold={0.6}
          contentContainerStyle={{ paddingBottom: 28 }}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          refreshControl={<RefreshControl refreshing={browse.refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, marginHorizontal: SPACING.md, marginBottom: SPACING.md, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  sectionHead: { flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between', paddingHorizontal: SPACING.md, marginBottom: 10 },
  sectionTitle: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black },
  row: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 6 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, paddingHorizontal: SPACING.md, marginBottom: SPACING.md },
  chip: { flexDirection: 'row', alignItems: 'center', gap: 6, maxWidth: '100%', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingLeft: 12, paddingRight: 8, paddingVertical: 7 },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary, flexShrink: 1 },
  note: { textAlign: 'center', color: COLORS.gray700, marginTop: SPACING.lg, paddingHorizontal: SPACING.lg },
});
