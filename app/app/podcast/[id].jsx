// One podcast: the cover, who presents it, a Follow button, a Play button for the newest episode, a share button, what
// it is about and every episode. Tapping an episode plays it (and the next ones follow); the player bar stays on every
// screen so you can keep listening while you read or chat.
import { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import { EpisodeRow } from '../../components/podcast/PodcastCards';
import { BackButton, HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api, { errorMessage, mediaUrl } from '../../lib/api';
import { playEpisode, togglePodcast, usePodcast } from '../../lib/podcastPlayer';

export default function PodcastScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [podcast, setPodcast] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { episode: current, phase } = usePodcast();
  const [episodes, setEpisodes] = useState([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [more, setMore] = useState(false);
  const [following, setFollowing] = useState(false);
  const [followers, setFollowers] = useState(0);
  const [note, setNote] = useState(null);

  // A podcast that is not approved (or was hidden) answers "not found": say so kindly instead of showing an error.
  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await api.get(`/podcasts/${id}`);
      setPodcast(data.data);
    } catch (e) {
      setPodcast(null);
      if (e?.response?.status !== 404) setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (!podcast) return;
    setEpisodes(podcast.episodes);
    setLastPage(podcast.episodes_last_page ?? 1);
    setFollowing(!!podcast.following);
    setFollowers(podcast.followers);
  }, [podcast]);

  async function toggleFollow() {
    const next = !following;
    setFollowing(next);
    setFollowers((n) => Math.max(0, n + (next ? 1 : -1)));
    try {
      const { data: res } = next ? await api.post(`/podcasts/${id}/follow`) : await api.delete(`/podcasts/${id}/follow`);
      setFollowing(res.following);
      setFollowers(res.followers);
      setNote(next ? 'You are following this podcast.' : null);
    } catch (e) {
      setFollowing(!next);
      setFollowers((n) => Math.max(0, n + (next ? -1 : 1)));
      setNote(errorMessage(e));
    }
  }

  async function showMore() {
    setMore(true);
    try {
      const { data: res } = await api.get(`/podcasts/${id}/episodes`, { params: { page: page + 1 } });
      setEpisodes((list) => [...list, ...res.data]);
      setPage(page + 1);
    } catch (e) {
      setNote(errorMessage(e));
    } finally {
      setMore(false);
    }
  }

  const latest = episodes[0];
  const latestIsOn = !!latest && current?.id === latest.id && phase !== 'idle';

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={load} empty={!loading && !podcast} emptyIcon="mic-off-outline" emptyTitle="This podcast is not showing in the app" emptyMessage="It may still be waiting for review or has been hidden. The podcaster can manage it on the FaithFellows website.">
        {podcast ? (
          <ScrollView contentContainerStyle={{ paddingBottom: insets.bottom + 40 }} showsVerticalScrollIndicator={false}>
            <View style={{ paddingTop: insets.top + 8 }}>
              <LinearGradient colors={[COLORS.primaryMist, COLORS.canvas]} style={StyleSheet.absoluteFill} />
              <View style={styles.top}>
                <BackButton />
                <View style={{ flex: 1 }} />
                <HeaderAction icon="share-social-outline" label="Share this podcast" onPress={() => Share.share({ message: `Listen to "${podcast.name}", a faith-based podcast by ${podcast.host}, on FaithFellows.` }).catch(() => {})} />
              </View>
              <View style={styles.hero}>
                <Image source={{ uri: mediaUrl(podcast.cover_url) }} style={styles.cover} contentFit="cover" transition={250} />
                <AppText style={styles.name}>{podcast.name}</AppText>
                <AppText style={styles.host}>
                  {podcast.host} · {podcast.category_label}
                </AppText>
                <AppText style={styles.counts}>
                  {podcast.episodes_count} episode{podcast.episodes_count === 1 ? '' : 's'} · {followers} follower{followers === 1 ? '' : 's'}
                </AppText>
              </View>
            </View>

            <View style={styles.actions}>
              <View style={{ flex: 1 }}>
                <Button
                  title={latestIsOn && phase === 'playing' ? 'Pause' : latestIsOn ? 'Resume' : 'Play latest'}
                  disabled={!latest}
                  onPress={() => (latestIsOn ? togglePodcast() : playEpisode(latest, episodes))}
                />
              </View>
              <Pressable onPress={toggleFollow} accessibilityRole="button" accessibilityState={{ selected: following }} accessibilityLabel={following ? 'Following. Tap to unfollow' : 'Follow'} style={[styles.follow, following && styles.followOn]}>
                <Ionicons name={following ? 'heart' : 'heart-outline'} size={20} color={following ? COLORS.onColor : COLORS.primary} />
                <AppText style={[styles.followText, following && { color: COLORS.onColor }]}>{following ? 'Following' : 'Follow'}</AppText>
              </Pressable>
            </View>
            {note ? <AppText style={styles.note}>{note}</AppText> : null}

            <View style={styles.about}>
              <AppText style={styles.summary}>{podcast.summary}</AppText>
              {podcast.description ? <AppText style={styles.description}>{podcast.description}</AppText> : null}
            </View>

            <AppText style={styles.section}>Episodes</AppText>
            {episodes.length ? (
              episodes.map((e) => <EpisodeRow key={e.id} episode={e} list={episodes} />)
            ) : (
              <AppText style={styles.empty}>No episodes yet. Check again soon.</AppText>
            )}
            {page < lastPage ? <Button title={more ? 'Loading…' : 'Show older episodes'} variant="outline" small onPress={showMore} style={{ alignSelf: 'center', marginTop: 6 }} /> : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  top: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: SPACING.md },
  hero: { alignItems: 'center', paddingHorizontal: SPACING.md, paddingTop: 8, paddingBottom: 18 },
  cover: { width: 190, height: 190, borderRadius: 24, backgroundColor: COLORS.gray100 },
  name: { fontFamily: FONTS.bold, fontSize: 23, lineHeight: 30, color: COLORS.black, textAlign: 'center', marginTop: 16 },
  host: { fontFamily: FONTS.medium, fontSize: 14.5, color: COLORS.gray900, textAlign: 'center', marginTop: 3 },
  counts: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray700, textAlign: 'center', marginTop: 5 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: SPACING.md, marginTop: 4 },
  follow: { flexDirection: 'row', alignItems: 'center', gap: 6, height: 50, paddingHorizontal: 18, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: COLORS.primary, backgroundColor: COLORS.white },
  followOn: { backgroundColor: COLORS.primary },
  followText: { fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.primary },
  note: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.gray900, textAlign: 'center', marginTop: 10, paddingHorizontal: SPACING.md },
  about: { paddingHorizontal: SPACING.md, marginTop: 18, gap: 8 },
  summary: { fontFamily: FONTS.semibold, fontSize: 15.5, lineHeight: 22, color: COLORS.black },
  description: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: COLORS.gray900 },
  section: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black, paddingHorizontal: SPACING.md, marginTop: 24, marginBottom: 10 },
  empty: { fontFamily: FONTS.regular, fontSize: 14, color: COLORS.gray700, paddingHorizontal: SPACING.md },
});
