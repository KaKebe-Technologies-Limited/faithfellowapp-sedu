// Home tab, in a soft frosted style on the brand colours, built round the radio. From the top: a deep wine panel with the song that is
// on air (the card, the glowing play / stop button, up next, share and give; a short greeting shows for ten seconds when the app opens),
// then on the pale surface: the songs played last, What's new (live service, notices, events, campaigns, devotional, sermons), Explore
// (podcasts, hymns, sermons, events, prayer, giving, games, churches), the inspiration of the hour, the verse of the day, Games, the
// top four podcasts and four sermons. Nothing more: your churches are on Me. When the radio is off, empty or out of reach, the slider
// of highlights takes the top of the panel instead. Pull down to refresh.
// Data: GET /api/v1/home, in one request.
import { useCallback, useRef } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ExploreGrid from '../../components/home/ExploreGrid';
import HeroCarousel from '../../components/home/HeroCarousel';
import HomeHero from '../../components/home/HomeHero';
import { LatestSermons, TopPodcasts } from '../../components/home/HomeLists';
import InspirationCard from '../../components/home/InspirationCard';
import LastPlayed from '../../components/home/LastPlayed';
import LiveBanner from '../../components/home/LiveBanner';
import NewThisWeek from '../../components/home/NewThisWeek';
import NotificationsPrompt from '../../components/home/NotificationsPrompt';
import NowPlayingHero, { useHomeRadio } from '../../components/home/NowPlayingHero';
import QuizCard from '../../components/home/QuizCard';
import VerseCard from '../../components/home/VerseCard';
import WelcomeBanner from '../../components/home/WelcomeBanner';
import Skeleton from '../../components/Skeleton';
import { COLORS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import { useAuth } from '../../lib/auth';
import { useFetch } from '../../lib/useFetch';

function HomeSkeleton() {
  return (
    <View style={{ paddingHorizontal: SPACING.md, gap: SPACING.md, marginBottom: SPACING.md }}>
      <View style={{ flexDirection: 'row', gap: 14 }}>
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} width={66} height={66} radius={22} />
        ))}
      </View>
      <Skeleton height={130} radius={RADIUS.xl} />
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

/** What the slider shows: the highlights, else the churches' own banners, else nothing (the welcome card takes over). */
function sliderItems(data) {
  if (data.highlights?.length) return data.highlights;

  return (data.slides ?? []).map((s) => ({ kind: 'church', slug: s.church?.slug, label: s.church?.name, title: s.title, subtitle: s.subtitle, image: s.image, church: null }));
}

export default function HomeScreen() {
  const { user } = useAuth();
  const home = useFetch('/home');
  const radio = useHomeRadio();

  // Coming back to Home (for example after following a church) quietly refreshes it. (useFetch returns a new reload function each
  // render, so keep the latest in a ref and keep this callback stable.)
  const firstFocus = useRef(true);
  const reload = useRef(home.reload);
  reload.current = home.reload;
  useFocusEffect(
    useCallback(() => {
      if (firstFocus.current) {
        firstFocus.current = false;
        return;
      }
      reload.current();
    }, []),
  );

  const data = home.data?.data;
  const items = data ? sliderItems(data) : [];
  const liveNow = (data?.live ?? []).find((stream) => stream.status === 'live');
  const hasChurches = (data?.churches ?? []).length > 0;

  return (
    <ScrollView
      style={styles.list}
      showsVerticalScrollIndicator={false}
      contentContainerStyle={{ paddingBottom: SPACING.lg }}
      refreshControl={<RefreshControl refreshing={home.refreshing} onRefresh={home.reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
    >
      <HomeHero user={user}>
        {radio !== 'none' ? <NowPlayingHero state={radio} home={data} /> : data ? items.length ? <HeroCarousel items={items} /> : <WelcomeBanner /> : null}
      </HomeHero>

      {home.loading && !data ? <HomeSkeleton /> : null}
      {home.error && !data ? (
        <View style={{ paddingHorizontal: SPACING.md }}>
          <Notice title="Home could not load" message={home.error} actionTitle="Try again" onAction={home.retry} />
        </View>
      ) : null}

      {data ? (
        <>
          {radio === 'ready' ? <LastPlayed /> : null}
          <NewThisWeek home={data} withHighlights={radio !== 'none'} />
          <ExploreGrid home={data} />
          <NotificationsPrompt />
          {!hasChurches ? (
            <View style={{ paddingHorizontal: SPACING.md, marginBottom: SPACING.lg }}>
              <Notice title="Find your church family" message="Follow a church to get its sermons, events and prayer requests." actionTitle="Find churches" onAction={() => router.push('/churches')} />
            </View>
          ) : null}
          <InspirationCard inspiration={data.inspiration} />
          <LiveBanner stream={liveNow} />
          <VerseCard verse={data.verse} />
          <QuizCard quiz={data.quiz} />
          <TopPodcasts podcasts={data.podcasts} />
          <LatestSermons sermons={data.sermons} />
        </>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  list: { flex: 1, backgroundColor: SOFT.surface },
  notice: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, ...SHADOW },
});
