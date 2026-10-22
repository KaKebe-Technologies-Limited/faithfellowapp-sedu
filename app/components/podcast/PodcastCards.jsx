// The building blocks of the podcast screens: a square tile for horizontal shelves, a row for a podcast in a list, and
// a row for an episode with a play button (it shows the pause icon and a pink name while that episode is playing).
import { ActivityIndicator, FlatList, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW } from '../../constants/theme';
import { PURPLE } from '../../constants/podcastTheme';
import { mediaUrl } from '../../lib/api';
import { formatDuration, timeAgo } from '../../lib/format';
import { playEpisode, usePodcast } from '../../lib/podcastPlayer';
import AppText from '../AppText';
import { colorFor } from '../Avatar';

const open = (id) => router.push({ pathname: '/podcast/[id]', params: { id } });

/** A cover with the name under it, for a horizontal shelf. */
export function PodcastTile({ podcast }) {
  return (
    <Pressable onPress={() => open(podcast.id)} style={styles.tile} accessibilityRole="button" accessibilityLabel={`${podcast.name}, ${podcast.host}`}>
      <Image source={{ uri: mediaUrl(podcast.cover_url) }} style={styles.tileCover} contentFit="cover" transition={200} />
      <AppText style={styles.tileName} numberOfLines={2}>
        {podcast.name}
      </AppText>
      <AppText style={styles.tileHost} numberOfLines={1}>
        {podcast.host}
      </AppText>
    </Pressable>
  );
}

/** A podcast in a list: cover, name, who presents it, a line about it, and how many episodes. */
export function PodcastRow({ podcast }) {
  return (
    <Pressable onPress={() => open(podcast.id)} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Podcast: ${podcast.name}`}>
      <Image source={{ uri: mediaUrl(podcast.cover_url) }} style={styles.rowCover} contentFit="cover" transition={200} />
      <View style={{ flex: 1 }}>
        <AppText style={styles.name} numberOfLines={1}>
          {podcast.name}
        </AppText>
        <AppText style={styles.host} numberOfLines={1}>
          {podcast.host} · {podcast.category_label}
        </AppText>
        <AppText style={styles.summary} numberOfLines={2}>
          {podcast.summary}
        </AppText>
        <AppText style={styles.count}>
          {podcast.episodes} episode{podcast.episodes === 1 ? '' : 's'}
          {podcast.following ? ' · Following' : ''}
        </AppText>
      </View>
    </Pressable>
  );
}

/** An episode. `list` is the episodes shown with it, so the next one in the list plays after it. */
export function EpisodeRow({ episode, list, showPodcast = false }) {
  const { episode: current, phase } = usePodcast();
  const mine = current?.id === episode.id && phase !== 'idle';
  const playing = mine && phase === 'playing';

  return (
    <Pressable
      onPress={() => playEpisode(episode, list ?? [episode])}
      style={({ pressed }) => [styles.card, mine && styles.cardOn, pressed && { opacity: 0.94 }]}
      accessibilityRole="button"
      accessibilityLabel={`${playing ? 'Pause' : 'Play'} ${episode.title}`}
    >
      {showPodcast ? <Image source={{ uri: mediaUrl(episode.podcast?.cover_url) }} style={styles.epCover} contentFit="cover" /> : null}
      <View style={{ flex: 1 }}>
        <AppText style={[styles.epTitle, mine && { color: PURPLE.main }]} numberOfLines={2}>
          {episode.title}
        </AppText>
        {showPodcast ? (
          <AppText style={styles.host} numberOfLines={1}>
            {episode.podcast?.name}
          </AppText>
        ) : null}
        <AppText style={styles.when} numberOfLines={1}>
          {[episode.published_at ? timeAgo(episode.published_at) : null, episode.duration ? formatDuration(episode.duration) : null].filter(Boolean).join(' · ')}
        </AppText>
        {episode.summary && !showPodcast ? (
          <AppText style={styles.summary} numberOfLines={2}>
            {episode.summary}
          </AppText>
        ) : null}
      </View>
      <View style={styles.play}>
        {mine && phase === 'loading' ? <ActivityIndicator color={COLORS.white} /> : <Ionicons name={playing ? 'pause' : 'play'} size={20} color={COLORS.white} style={playing ? null : { marginLeft: 2 }} />}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  tile: { width: 128 },
  tileCover: { width: 128, height: 128, borderRadius: RADIUS.lg, backgroundColor: COLORS.gray100 },
  tileName: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginTop: 8 },
  tileHost: { fontFamily: FONTS.regular, fontSize: 12, color: COLORS.gray700, marginTop: 1 },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, marginHorizontal: 16, marginBottom: 10, ...SHADOW },
  cardOn: { borderWidth: 1.5, borderColor: PURPLE.pale },
  rowCover: { width: 84, height: 84, borderRadius: 12, backgroundColor: COLORS.gray100 },
  epCover: { width: 54, height: 54, borderRadius: 10, backgroundColor: COLORS.gray100 },
  name: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  host: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray700, marginTop: 2 },
  when: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray700, marginTop: 2 },
  summary: { fontFamily: FONTS.regular, fontSize: 13.5, color: COLORS.gray900, marginTop: 4 },
  count: { fontFamily: FONTS.medium, fontSize: 12, color: PURPLE.main, marginTop: 5 },
  epTitle: { fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  play: { width: 42, height: 42, borderRadius: 21, backgroundColor: PURPLE.main, alignItems: 'center', justifyContent: 'center' },
  gridWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 14, paddingHorizontal: 16 },
  gridCover: { width: '100%', aspectRatio: 1, borderRadius: RADIUS.lg + 2, backgroundColor: PURPLE.mist },
  gridBadge: { position: 'absolute', left: 8, bottom: 8, backgroundColor: 'rgba(40,16,90,0.72)', borderRadius: 9, paddingHorizontal: 8, paddingVertical: 3 },
  gridBadgeText: { fontFamily: FONTS.semibold, fontSize: 10.5, color: '#fff' },
  gridName: { fontFamily: FONTS.bold, fontSize: 14.5, lineHeight: 19, color: COLORS.black, marginTop: 8 },
  gridHost: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray700, marginTop: 1 },
  gridCount: { fontFamily: FONTS.medium, fontSize: 12, color: PURPLE.main, marginTop: 2 },
  pubItem: { width: 78, alignItems: 'center' },
  pubRing: { width: 70, height: 70, borderRadius: 35, borderWidth: 2.5, borderColor: PURPLE.pale, alignItems: 'center', justifyContent: 'center' },
  pubRingOn: { borderColor: PURPLE.main },
  pubCover: { width: 60, height: 60, borderRadius: 30, backgroundColor: PURPLE.mist },
  pubName: { fontFamily: FONTS.semibold, fontSize: 12, lineHeight: 15, color: COLORS.black, textAlign: 'center', marginTop: 6 },
  pubCount: { fontFamily: FONTS.regular, fontSize: 11, color: COLORS.gray700 },
});

/** Podcasts as cover thumbnails, two to a row: the cover, its category, the name, who publishes it and how many episodes. */
export function PodcastGrid({ podcasts }) {
  const { width } = useWindowDimensions();
  const tile = Math.floor((width - 32 - 14) / 2);

  return (
    <View style={styles.gridWrap}>
      {podcasts.map((p) => (
        <Pressable key={p.id} onPress={() => open(p.id)} style={({ pressed }) => [{ width: tile }, pressed && { opacity: 0.92 }]} accessibilityRole="button" accessibilityLabel={`Podcast: ${p.name}, by ${p.host}`}>
          <View>
            <Image source={{ uri: mediaUrl(p.cover_url) }} style={styles.gridCover} contentFit="cover" transition={200} />
            {p.category_label ? (
              <View style={styles.gridBadge}>
                <AppText style={styles.gridBadgeText}>{p.category_label}</AppText>
              </View>
            ) : null}
          </View>
          <AppText style={styles.gridName} numberOfLines={2}>
            {p.name}
          </AppText>
          <AppText style={styles.gridHost} numberOfLines={1}>
            by {p.host}
          </AppText>
          <AppText style={styles.gridCount}>
            {p.episodes} episode{p.episodes === 1 ? '' : 's'}
          </AppText>
        </Pressable>
      ))}
    </View>
  );
}

/** The people and groups that publish the podcasts, as round covers: tap one to see only their podcasts. `selected` is a host name or null. */
export function PublisherStrip({ podcasts, selected, onSelect }) {
  const byHost = new Map();
  podcasts.forEach((p) => {
    const host = String(p.host ?? '').trim();
    if (!host) return;
    const entry = byHost.get(host) ?? { host, cover_url: p.cover_url, count: 0 };
    entry.count += 1;
    byHost.set(host, entry);
  });
  const publishers = [...byHost.values()];
  if (publishers.length < 2) return null;

  return (
    <FlatList
      data={publishers}
      horizontal
      showsHorizontalScrollIndicator={false}
      keyExtractor={(x) => x.host}
      contentContainerStyle={{ paddingHorizontal: 16, gap: 12 }}
      renderItem={({ item }) => {
        const on = selected === item.host;

        return (
          <Pressable onPress={() => onSelect(on ? null : item.host)} style={styles.pubItem} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={`${item.host}, ${item.count} podcast${item.count === 1 ? '' : 's'}`}>
            <View style={[styles.pubRing, on && styles.pubRingOn]}>
              <Image source={{ uri: mediaUrl(item.cover_url) }} style={[styles.pubCover, { backgroundColor: colorFor(item.host) }]} contentFit="cover" />
            </View>
            <AppText style={styles.pubName} numberOfLines={2}>
              {item.host}
            </AppText>
            <AppText style={styles.pubCount}>
              {item.count} podcast{item.count === 1 ? '' : 's'}
            </AppText>
          </Pressable>
        );
      }}
    />
  );
}
