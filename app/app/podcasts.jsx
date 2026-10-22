// Podcasts: faith-based podcasts made by members. Search by name, host or topic, pick a category, see what you follow
// and the newest episodes, and open a podcast to listen. Playing an episode keeps going while you move around the app.
import { useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../components/AppText';
import Chip from '../components/Chip';
import { EpisodeRow, PodcastGrid, PodcastRow, PodcastTile, PublisherStrip } from '../components/podcast/PodcastCards';
import PodcastRadio from '../components/podcast/PodcastRadio';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../constants/theme';
import { PURPLE } from '../constants/podcastTheme';
import { useRadio } from '../lib/radio';
import { usePaged } from '../lib/usePaged';
import { useFetch } from '../lib/useFetch';

function Shelf({ title, hint, children }) {
  return (
    <View style={{ marginBottom: SPACING.lg }}>
      <View style={styles.sectionHead}>
        <View style={styles.sectionBar} />
        <AppText style={styles.section}>{title}</AppText>
        {hint ? <AppText style={styles.hint}>{hint}</AppText> : null}
      </View>
      {children}
    </View>
  );
}

/** "Publishers": the people and groups behind the podcasts. Nothing shows until there are at least two. */
function PublisherStripShelf({ podcasts, onSelect }) {
  const hosts = new Set(podcasts.map((p) => String(p.host ?? '').trim()).filter(Boolean));
  if (hosts.size < 2) return null;

  return (
    <Shelf title="Publishers" hint="Tap one to see their podcasts">
      <PublisherStrip podcasts={podcasts} selected={null} onSelect={onSelect} />
    </Shelf>
  );
}

/** The landing view: the Podcast radio, what you follow, the publishers, the podcasts as thumbnails, the newest episodes and the full list. */
function Overview({ overview, refreshing, reload, onPublisher, onSection }) {
  const { following, new_episodes: fresh, podcasts, sections = [] } = overview;

  return (
    <ScrollView contentContainerStyle={{ paddingTop: 6, paddingBottom: 40 }} keyboardShouldPersistTaps="handled" automaticallyAdjustKeyboardInsets refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
      <PodcastRadio />
      {following.length ? (
        <Shelf title="Following">
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: SPACING.md, gap: 14 }}>
            {following.map((p) => (
              <PodcastTile key={p.id} podcast={p} />
            ))}
          </ScrollView>
        </Shelf>
      ) : null}
      {podcasts.length ? (
        <>
          <PublisherStripShelf podcasts={podcasts} onSelect={onPublisher} />
          <Shelf title="Podcasts" hint={`${podcasts.length} to explore`}>
            <PodcastGrid podcasts={podcasts} />
          </Shelf>
        </>
      ) : null}
      {sections.map((section) => (
        <Shelf key={section.key} title={section.label} hint={section.count > section.podcasts.length ? `${section.count} podcasts` : null}>
          {section.podcasts.map((p) => (
            <PodcastRow key={p.id} podcast={p} />
          ))}
          {section.count > section.podcasts.length ? (
            <Pressable onPress={() => onSection(section.key)} accessibilityRole="button" style={styles.seeAll}>
              <AppText style={styles.seeAllText}>See all {section.count} in {section.label}</AppText>
              <Ionicons name="chevron-forward" size={15} color={PURPLE.main} />
            </Pressable>
          ) : null}
        </Shelf>
      ))}
      {fresh.length ? (
        <Shelf title="New episodes">
          {fresh.slice(0, 6).map((e) => (
            <EpisodeRow key={e.id} episode={e} list={fresh} showPodcast />
          ))}
        </Shelf>
      ) : null}
      {podcasts.length ? (
        <Shelf title="All podcasts">
          {podcasts.map((p) => (
            <PodcastRow key={p.id} podcast={p} />
          ))}
        </Shelf>
      ) : null}
    </ScrollView>
  );
}

/** Search results or one category / your followed podcasts, newest first. */
function Results({ params }) {
  const { items, loading, refreshing, loadingMore, error, reload, retry, loadMore } = usePaged('/podcasts', params);

  return (
    <StateView
      loading={loading}
      error={error}
      onRetry={retry}
      empty={!loading && !error && items.length === 0}
      emptyIcon="mic-off-outline"
      emptyTitle={params.q ? 'No podcast matches' : params.following ? 'You are not following any podcast yet' : 'No podcasts here yet'}
      emptyMessage={params.q ? 'Try the name of the podcast, who presents it, or a topic.' : 'Podcasts will appear here as they are approved.'}
    >
      <FlatList
        data={items}
        keyExtractor={(p) => String(p.id)}
        contentContainerStyle={{ paddingTop: 6, paddingBottom: 40 }}
        onEndReached={loadMore}
        onEndReachedThreshold={0.4}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
        ListFooterComponent={loadingMore ? <AppText style={styles.more}>Loading…</AppText> : null}
        renderItem={({ item }) => <PodcastRow podcast={item} />}
      />
    </StateView>
  );
}

export default function PodcastsScreen() {
  const [text, setText] = useState('');
  const [q, setQ] = useState('');
  const [category, setCategory] = useState('');
  const [onlyFollowing, setOnlyFollowing] = useState(false);
  const overview = useFetch('/podcasts/overview');
  // The Podcast radio is a podcast too: the screen is not empty while a podcast channel is on.
  const talkOn = useRadio().stations.some((s) => s.kind === 'talk');

  // Search as you type, a moment after the last letter.
  useEffect(() => {
    const t = setTimeout(() => setQ(text.trim()), 400);
    return () => clearTimeout(t);
  }, [text]);

  const browsing = !!q || !!category || onlyFollowing;
  const categories = overview.data?.data?.categories ?? [];
  const params = { q: q || undefined, category: category || undefined, following: onlyFollowing ? 1 : undefined };

  return (
    <View style={styles.flex}>
      <ScreenHeader title="Podcasts" subtitle="Faith-based, to listen to anywhere" />
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE}
          value={text}
          onChangeText={setText}
          returnKeyType="search"
          autoCorrect={false}
          placeholder="Search podcasts, hosts or topics"
          placeholderTextColor={COLORS.gray500}
          style={styles.searchInput}
          accessibilityLabel="Search podcasts"
        />
      </View>
      <View>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
          <Chip tone="purple" label="All" on={!category && !onlyFollowing} onPress={() => (setCategory(''), setOnlyFollowing(false), setText(''), setQ(''))} />
          {overview.data?.data?.following?.length ? <Chip tone="purple" label="Following" on={onlyFollowing} onPress={() => (setOnlyFollowing(true), setCategory(''))} /> : null}
          {categories.map((c) => (
            <Chip key={c.key} tone="purple" label={c.label} on={category === c.key} onPress={() => (setCategory(c.key), setOnlyFollowing(false))} />
          ))}
        </ScrollView>
      </View>

      {browsing ? (
        <Results params={params} />
      ) : (
        <StateView
          loading={overview.loading}
          error={overview.error}
          onRetry={overview.retry}
          empty={!overview.loading && !overview.error && !overview.data?.data?.podcasts?.length && !talkOn}
          emptyIcon="mic-outline"
          emptyTitle="No podcasts yet"
          emptyMessage="Podcasts made by members appear here once they are approved. You can start one from the FaithFellows website."
        >
          {overview.data ? <Overview overview={overview.data.data} refreshing={overview.refreshing} reload={overview.reload} onPublisher={(host) => host && (setText(host), setQ(host))} onSection={(key) => (setCategory(key), setOnlyFollowing(false))} /> : null}
        </StateView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: SPACING.sm, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  chips: { paddingHorizontal: SPACING.md, gap: 8, paddingBottom: 6 },
  sectionHead: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingHorizontal: SPACING.md, marginBottom: 12 },
  sectionBar: { width: 4, height: 20, borderRadius: 2, backgroundColor: PURPLE.main },
  section: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black, flex: 1 },
  hint: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray700 },
  seeAll: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 4, paddingVertical: 8 },
  seeAllText: { fontFamily: FONTS.semibold, fontSize: 14, color: PURPLE.main },
  more: { textAlign: 'center', color: COLORS.gray700, paddingVertical: 12 },
});
