// The building blocks of the podcast screens: a square tile for horizontal shelves, a row for a podcast in a list, and
// a row for an episode with a play button (it shows the pause icon and a pink name while that episode is playing).
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { formatDuration, timeAgo } from '../../lib/format';
import { playEpisode, usePodcast } from '../../lib/podcastPlayer';
import AppText from '../AppText';

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
        <AppText style={[styles.epTitle, mine && { color: COLORS.primary }]} numberOfLines={2}>
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
  cardOn: { borderWidth: 1.5, borderColor: COLORS.primaryPale },
  rowCover: { width: 84, height: 84, borderRadius: 12, backgroundColor: COLORS.gray100 },
  epCover: { width: 54, height: 54, borderRadius: 10, backgroundColor: COLORS.gray100 },
  name: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  host: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray700, marginTop: 2 },
  when: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray700, marginTop: 2 },
  summary: { fontFamily: FONTS.regular, fontSize: 13.5, color: COLORS.gray900, marginTop: 4 },
  count: { fontFamily: FONTS.medium, fontSize: 12, color: COLORS.primary, marginTop: 5 },
  epTitle: { fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  play: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
});
