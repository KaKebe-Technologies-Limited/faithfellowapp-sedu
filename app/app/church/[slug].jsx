// A church's page, with everything about the church in one place and nothing to hunt for on another screen: a banner and the Follow button, then tabs that
// swap right on the page: Home (the live service, the latest sermon, the church's radio, what is coming up, the devotional, giving, the news), Sermons (every
// sermon, with the audio playing right here), Listen (the church's own channel and its songs), Events, News, Give and About (service times, where and how to
// reach them, photos and links). Sermons and songs play in the app's player and keep going while you look around.
// Data: GET /churches/{slug} (the details) and GET /churches/{slug}/page (everything else, in one request; members of the church get what is kept for them).
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Button from '../../components/Button';
import ChurchHeader from '../../components/church/ChurchHeader';
import ChurchTabBar from '../../components/church/ChurchTabBar';
import { AboutTab, EventsTab, GiveTab, HomeTab, ListenTab, SermonsTab, UpdatesTab } from '../../components/church/ChurchTabs';
import { EmptyNote } from '../../components/church/Blocks';
import { goBack } from '../../components/ScreenHeader';
import Skeleton from '../../components/Skeleton';
import StateView from '../../components/StateView';
import { COLORS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import api, { errorMessage } from '../../lib/api';
import { useFetch } from '../../lib/useFetch';

/** The tabs this church has something to show in (Home and About always). */
function tabsFor(page) {
  const tabs = [{ key: 'home', label: 'Home', icon: 'home' }];
  if (page?.stats?.sermons > 0 || page?.sermons?.length) tabs.push({ key: 'sermons', label: 'Sermons', icon: 'mic' });
  if (page?.radio || page?.channel) tabs.push({ key: 'listen', label: 'Listen', icon: 'headset' });
  if (page?.events?.length) tabs.push({ key: 'events', label: 'Events', icon: 'calendar' });
  if (page?.updates?.length || page?.announcements?.length || page?.devotional) tabs.push({ key: 'updates', label: 'News', icon: 'newspaper' });
  if (page?.giving?.available || page?.campaigns?.length) tabs.push({ key: 'give', label: 'Give', icon: 'heart' });
  tabs.push({ key: 'about', label: 'About', icon: 'information-circle' });

  return tabs;
}

export default function ChurchScreen() {
  const { slug, tab: wanted } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const church = useFetch(`/churches/${slug}`);
  const pageQuery = useFetch(`/churches/${slug}/page`);
  const info = church.data?.data;
  const page = pageQuery.data?.data;
  const [tab, setTab] = useState(typeof wanted === 'string' ? wanted : 'home');
  const [busy, setBusy] = useState(false);
  const scroll = useRef(null);
  const offset = useRef(0);
  const headerHeight = useRef(0);

  const tabs = tabsFor(page);
  const active = tabs.some((t) => t.key === tab) ? tab : 'home';

  // Choosing a tab starts it at the top, just under the bar, instead of leaving you part-way down the last one.
  const choose = useCallback((key) => {
    setTab(key);
    if (offset.current > headerHeight.current) scroll.current?.scrollTo({ y: headerHeight.current, animated: false });
  }, []);

  useEffect(() => {
    if (typeof wanted === 'string') setTab(wanted);
  }, [wanted]);

  const reloadAll = () => {
    church.reload();
    pageQuery.reload();
  };

  async function follow() {
    setBusy(true);
    try {
      await api.post(`/churches/${info.slug}/subscription`);
      await Promise.all([church.reload(), pageQuery.reload()]);
    } catch (e) {
      Alert.alert('Could not follow', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function unfollow() {
    setBusy(true);
    try {
      await api.delete(`/churches/${info.slug}/subscription`);
      await Promise.all([church.reload(), pageQuery.reload()]);
    } catch (e) {
      Alert.alert('Could not unfollow', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const confirmUnfollow = () =>
    Alert.alert(`Unfollow ${info.name}?`, 'You will stop seeing their updates.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Unfollow', style: 'destructive', onPress: unfollow },
    ]);

  const share = () => Share.share({ message: info.join_url ? `${info.name} on FaithFellows: ${info.join_url}` : `${info.name} on FaithFellows` }).catch(() => {});

  const floating = (
    <>
      <Pressable onPress={goBack} accessibilityRole="button" accessibilityLabel="Go back" style={[styles.round, { top: insets.top + 8, left: SPACING.md }]}>
        <Ionicons name="chevron-back" size={22} color={COLORS.black} />
      </Pressable>
      {info ? (
        <Pressable onPress={share} accessibilityRole="button" accessibilityLabel={`Share ${info.name}`} style={[styles.round, { top: insets.top + 8, right: SPACING.md }]}>
          <Ionicons name="share-social-outline" size={20} color={COLORS.black} />
        </Pressable>
      ) : null}
    </>
  );

  if (church.loading || church.error || !info) {
    return (
      <View style={styles.screen}>
        {floating}
        {church.loading ? (
          <View style={{ paddingTop: insets.top + 64, paddingHorizontal: SPACING.md, gap: SPACING.md }}>
            <Skeleton height={190} radius={RADIUS.xl} />
            <Skeleton width="60%" height={26} />
            <Skeleton height={80} radius={RADIUS.lg} />
          </View>
        ) : (
          <View style={{ paddingTop: insets.top + 56, flex: 1 }}>
            <StateView error={church.error || 'This church could not be found.'} onRetry={church.retry} />
          </View>
        )}
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <ScrollView
        ref={scroll}
        stickyHeaderIndices={[1]}
        showsVerticalScrollIndicator={false}
        scrollEventThrottle={32}
        onScroll={(e) => {
          offset.current = e.nativeEvent.contentOffset.y;
        }}
        contentContainerStyle={{ paddingBottom: 60 }}
        refreshControl={<RefreshControl refreshing={church.refreshing || pageQuery.refreshing} onRefresh={reloadAll} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
      >
        <View onLayout={(e) => (headerHeight.current = e.nativeEvent.layout.height)}>
          <ChurchHeader info={info} stats={page?.stats} busy={busy} onFollow={follow} onUnfollow={confirmUnfollow} />
        </View>
        <ChurchTabBar tabs={tabs} active={active} onChange={choose} />
        <View style={styles.body}>
          {pageQuery.loading && !page ? (
            <View style={{ gap: SPACING.md }}>
              <Skeleton height={200} radius={RADIUS.xl} />
              <Skeleton height={76} radius={RADIUS.lg} />
              <Skeleton height={76} radius={RADIUS.lg} />
            </View>
          ) : null}
          {pageQuery.error && !page ? (
            <View>
              <EmptyNote icon="cloud-offline-outline" title="The church page could not load" text={pageQuery.error} />
              <Button title="Try again" variant="outline" onPress={pageQuery.retry} style={{ alignSelf: 'center' }} />
            </View>
          ) : null}
          {page && active === 'home' ? <HomeTab info={info} page={page} go={choose} onFollow={info.is_subscribed ? () => choose('listen') : follow} /> : null}
          {page && active === 'sermons' ? <SermonsTab info={info} /> : null}
          {page && active === 'listen' ? <ListenTab info={info} page={page} onFollow={follow} /> : null}
          {page && active === 'events' ? <EventsTab page={page} /> : null}
          {page && active === 'updates' ? <UpdatesTab info={info} page={page} /> : null}
          {page && active === 'give' ? <GiveTab info={info} page={page} /> : null}
          {active === 'about' ? <AboutTab info={info} page={page ?? { socials: [], gallery: [] }} /> : null}
        </View>
      </ScrollView>
      {floating}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: SOFT.surface },
  round: { position: 'absolute', zIndex: 10, width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.92)', alignItems: 'center', justifyContent: 'center', ...SHADOW },
  body: { paddingHorizontal: SPACING.md, paddingTop: SPACING.md },
});
