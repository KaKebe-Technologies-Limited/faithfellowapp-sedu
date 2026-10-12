// Home tab. From the top: greeting, the banner slider of your churches, church "stories", a live-now
// card, swipeable highlights (urgent notices, events, devotional, campaigns, sermons), then the feed of
// everything your churches post. Pull down to refresh; scroll to the end to load older items.
// Data: GET /api/v1/home (the banner and highlights) and GET /api/v1/feed (the list).
import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import FeedItem from '../../components/FeedItem';
import ChurchStories from '../../components/home/ChurchStories';
import Highlights from '../../components/home/Highlights';
import HeroCarousel from '../../components/home/HeroCarousel';
import HomeHeader from '../../components/home/HomeHeader';
import LiveBanner from '../../components/home/LiveBanner';
import SuggestedChurches from '../../components/home/SuggestedChurches';
import WelcomeBanner from '../../components/home/WelcomeBanner';
import SectionHeader from '../../components/SectionHeader';
import Skeleton from '../../components/Skeleton';
import { COLORS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useFetch } from '../../lib/useFetch';

function HomeSkeleton() {
  return (
    <View style={{ paddingHorizontal: SPACING.md, gap: SPACING.md, marginBottom: SPACING.md }}>
      <Skeleton height={210} radius={RADIUS.xl} />
      <View style={{ flexDirection: 'row', gap: 14 }}>
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} width={64} height={64} radius={20} />
        ))}
      </View>
      <Skeleton height={150} radius={RADIUS.xl} />
    </View>
  );
}

function Notice({ title, message, actionTitle, onAction }) {
  return (
    <View style={styles.notice}>
      <AppText variant="heading">{title}</AppText>
      <AppText style={{ color: COLORS.gray700, marginTop: 4 }}>{message}</AppText>
      {actionTitle ? <Button title={actionTitle} variant="outline" small onPress={onAction} style={{ alignSelf: 'flex-start', marginTop: SPACING.md }} /> : null}
    </View>
  );
}

export default function HomeScreen() {
  const { user } = useAuth();
  const home = useFetch('/home');
  const [items, setItems] = useState([]);
  const [next, setNext] = useState(null);
  const [status, setStatus] = useState({ loading: true, refreshing: false, more: false, error: null });
  const busy = useRef(false);

  const load = useCallback(async (mode = 'load', before = null) => {
    if (busy.current) return;
    busy.current = true;
    if (mode !== 'silent') setStatus((s) => ({ ...s, loading: mode === 'load', refreshing: mode === 'refresh', more: mode === 'more', error: null }));
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

  // Coming back to Home (e.g. after following a church) quietly refreshes the banner, highlights and feed.
  // (useFetch returns a new reload function each render, so keep the latest one in a ref and keep this callback stable.)
  const firstFocus = useRef(true);
  const reloadHome = useRef(home.reload);
  reloadHome.current = home.reload;
  useFocusEffect(
    useCallback(() => {
      if (firstFocus.current) {
        firstFocus.current = false;
        return;
      }
      reloadHome.current();
      load('silent');
    }, [load]),
  );

  const data = home.data?.data;
  const slides = data?.slides ?? [];
  const liveNow = (data?.live ?? []).find((stream) => stream.status === 'live');
  const hasChurches = (data?.churches ?? []).length > 0;

  const header = (
    <View>
      <HomeHeader user={user} />
      {home.loading && !data ? <HomeSkeleton /> : null}
      {home.error && !data ? (
        <View style={{ paddingHorizontal: SPACING.md }}>
          <Notice title="Highlights could not load" message={home.error} actionTitle="Try again" onAction={home.retry} />
        </View>
      ) : null}
      {data ? (
        <>
          {slides.length ? <HeroCarousel slides={slides} /> : !hasChurches ? <WelcomeBanner /> : null}
          {!hasChurches ? (
            <SuggestedChurches
              onFollowed={() => {
                home.reload();
                load('silent');
              }}
            />
          ) : null}
          <ChurchStories churches={data.churches} />
          <LiveBanner stream={liveNow} />
          <Highlights home={data} />
          {items.length || status.loading ? <SectionHeader title="Latest from your churches" /> : null}
        </>
      ) : null}
    </View>
  );

  const empty = !status.loading ? (
    !hasChurches && !status.error ? null : status.error ? (
      <View style={{ paddingHorizontal: SPACING.md }}>
        <Notice title="Your feed could not load" message={status.error} actionTitle="Try again" onAction={() => load('load')} />
      </View>
    ) : (
      <View style={{ paddingHorizontal: SPACING.md }}>
        <Notice
          title={hasChurches ? 'Nothing new yet' : 'Your feed is empty'}
          message={hasChurches ? 'When your churches share sermons, events or updates, they will appear here.' : 'Follow a church to see its sermons, events and updates here.'}
          actionTitle={hasChurches ? undefined : 'Find churches'}
          onAction={() => router.push('/(tabs)/churches')}
        />
      </View>
    )
  ) : (
    <View style={{ paddingHorizontal: SPACING.md, gap: SPACING.md }}>
      <Skeleton height={120} radius={RADIUS.lg} />
      <Skeleton height={120} radius={RADIUS.lg} />
    </View>
  );

  return (
    <FlatList
      style={styles.list}
      data={items}
      keyExtractor={(entry, index) => `${entry.type}-${entry.item?.id ?? index}-${entry.at}`}
      renderItem={({ item }) => <FeedItem entry={item} />}
      ListHeaderComponent={header}
      ListEmptyComponent={empty}
      ListFooterComponent={status.more ? <ActivityIndicator color={COLORS.primary} style={{ paddingVertical: SPACING.lg }} /> : <View style={{ height: SPACING.lg }} />}
      contentContainerStyle={{ paddingBottom: SPACING.md }}
      showsVerticalScrollIndicator={false}
      refreshControl={
        <RefreshControl
          refreshing={status.refreshing}
          onRefresh={() => {
            home.reload();
            load('refresh');
          }}
          tintColor={COLORS.primary}
          colors={[COLORS.primary]}
        />
      }
      onEndReachedThreshold={0.5}
      onEndReached={() => next && !busy.current && load('more', next)}
      initialNumToRender={15}
      maxToRenderPerBatch={10}
      windowSize={9}
    />
  );
}

const styles = StyleSheet.create({
  list: { flex: 1, backgroundColor: COLORS.canvas },
  notice: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, ...SHADOW },
});
