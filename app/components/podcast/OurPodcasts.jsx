// "Our Podcasts" at the top of the Podcasts screen: the teaching and podcasts of the churches you follow, a card for each church with a big Play button and
// its episodes below, newest first. Pressing play plays the recordings themselves, one after the other, in the app's one player (so they keep going with
// the screen locked and the bar above the tabs controls them). It does not join the live radio, which has its own section lower down. When none of your
// churches has put anything out yet, the teaching FaithFellows itself has added takes the place.
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { PURPLE } from '../../constants/podcastTheme';
import { mediaUrl } from '../../lib/api';
import { playChurchTrack, useChurchTrack } from '../../lib/churchAudio';
import { formatDuration } from '../../lib/format';
import { togglePodcast, usePodcast } from '../../lib/podcastPlayer';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';
import Equalizer from '../radio/Equalizer';

const FIRST = 5; // episodes shown before "Show more"

/** One episode: its picture, name and length, and a round button that plays it right here (pause while it is playing). */
function Episode({ track, list, church }) {
  const { current, playing, loading, toggle } = useChurchTrack(track);
  const meta = [track.artist, track.duration ? formatDuration(track.duration) : null].filter(Boolean).join(' · ');

  return (
    <Pressable
      onPress={() => (current ? toggle() : playChurchTrack(track, list, church))}
      style={({ pressed }) => [styles.episode, current && styles.episodeOn, pressed && { opacity: 0.94 }]}
      accessibilityRole="button"
      accessibilityLabel={`${playing ? 'Pause' : 'Play'} ${track.title}`}
    >
      <View style={styles.cover}>
        <LinearGradient colors={[PURPLE.main, '#4527A0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        <Ionicons name="mic" size={20} color="rgba(255,255,255,0.7)" style={styles.coverIcon} />
        {track.cover_url ? <Image source={{ uri: mediaUrl(track.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={[styles.title, current && { color: PURPLE.main }]} numberOfLines={2}>
          {track.title}
        </AppText>
        {meta ? (
          <AppText style={styles.meta} numberOfLines={1}>
            {meta}
          </AppText>
        ) : null}
        {playing ? (
          <View style={styles.now}>
            <Equalizer playing height={12} color={PURPLE.main} />
            <AppText style={styles.nowText}>Playing</AppText>
          </View>
        ) : null}
      </View>
      <View style={styles.circle}>{loading ? <ActivityIndicator color="#fff" /> : <Ionicons name={playing ? 'pause' : 'play'} size={19} color="#fff" style={playing ? null : { marginLeft: 2 }} />}</View>
    </Pressable>
  );
}

/** A church's block: who it is, a Play button for the newest episode (it carries on to the next ones), then the episodes. */
function ChurchBlock({ block }) {
  const { episode, phase } = usePodcast();
  const [all, setAll] = useState(false);
  const church = block.church ?? { name: block.name };
  const episodes = block.episodes;
  const mine = Boolean(episode) && phase !== 'idle' && episodes.some((e) => `track-${e.id}` === episode.id);
  const playing = mine && phase === 'playing';
  const loading = mine && phase === 'loading';
  const shown = all ? episodes : episodes.slice(0, FIRST);
  const hidden = episodes.length - shown.length;
  const more = block.total - episodes.length;

  const play = () => (mine ? togglePodcast() : playChurchTrack(episodes[0], episodes, church));
  const openChurch = () => block.church && router.push({ pathname: '/church/[slug]', params: { slug: block.church.slug, tab: 'listen' } });

  return (
    <View style={{ marginBottom: SPACING.md + 4 }}>
      <View style={styles.head}>
        <Pressable onPress={openChurch} disabled={!block.church} style={styles.who} accessibilityRole="button" accessibilityLabel={`${block.name}. Open the church`}>
          <ChurchAvatar church={church} size={46} />
          <View style={{ flex: 1, gap: 2 }}>
            <AppText style={styles.church} numberOfLines={2}>
              {block.name}
            </AppText>
            <AppText style={styles.count}>
              {block.total} {block.total === 1 ? 'episode' : 'episodes'}
            </AppText>
          </View>
        </Pressable>
        <Pressable
          onPress={play}
          accessibilityRole="button"
          accessibilityLabel={playing ? `Pause ${block.name}` : `Play ${block.name}`}
          style={({ pressed }) => [styles.playAll, pressed && { opacity: 0.88 }]}
        >
          {loading ? <ActivityIndicator color="#fff" size="small" /> : <Ionicons name={playing ? 'pause' : 'play'} size={16} color="#fff" />}
          <AppText style={styles.playAllText}>{playing ? 'Pause' : mine ? 'Resume' : 'Play'}</AppText>
        </Pressable>
      </View>

      {shown.map((t) => (
        <Episode key={t.id} track={t} list={episodes} church={church} />
      ))}

      {hidden > 0 ? (
        <Pressable onPress={() => setAll(true)} accessibilityRole="button" style={styles.moreButton}>
          <AppText style={styles.moreText}>Show {hidden} more {hidden === 1 ? 'episode' : 'episodes'}</AppText>
          <Ionicons name="chevron-down" size={15} color={PURPLE.main} />
        </Pressable>
      ) : more > 0 && block.church ? (
        <Pressable onPress={openChurch} accessibilityRole="button" style={styles.moreButton}>
          <AppText style={styles.moreText}>All {block.total} episodes on the church page</AppText>
          <Ionicons name="chevron-forward" size={15} color={PURPLE.main} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** Nothing shows until there is something to play. */
export default function OurPodcasts({ ours }) {
  if (!ours?.length) return null;

  return (
    <View style={{ marginBottom: SPACING.lg }}>
      <View style={styles.sectionHead}>
        <View style={styles.sectionBar} />
        <AppText style={styles.section}>Our Podcasts</AppText>
      </View>
      {ours.map((block) => (
        <ChurchBlock key={block.slug} block={block} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  sectionHead: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingHorizontal: SPACING.md, marginBottom: 12 },
  sectionBar: { width: 4, height: 20, borderRadius: 2, backgroundColor: PURPLE.main },
  section: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black, flex: 1 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: PURPLE.mist, borderRadius: RADIUS.xl, padding: 12, marginHorizontal: SPACING.md, marginBottom: 10 },
  who: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 11 },
  church: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 20, color: COLORS.black },
  count: { fontFamily: FONTS.medium, fontSize: 12.5, color: PURPLE.main },
  playAll: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: PURPLE.main, borderRadius: RADIUS.full, paddingHorizontal: 16, height: 40, ...SHADOW, shadowOpacity: 0.16 },
  playAllText: { fontFamily: FONTS.bold, fontSize: 14, color: '#fff' },
  episode: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 10, marginHorizontal: SPACING.md, marginBottom: 8, borderWidth: 1.5, borderColor: 'transparent', ...SHADOW },
  episodeOn: { borderColor: PURPLE.pale },
  cover: { width: 56, height: 56, borderRadius: 12, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', backgroundColor: PURPLE.main },
  coverIcon: { position: 'absolute' },
  title: { fontFamily: FONTS.semibold, fontSize: 15, lineHeight: 20, color: COLORS.black },
  meta: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray700 },
  now: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 2 },
  nowText: { fontFamily: FONTS.bold, fontSize: 11.5, color: PURPLE.main },
  circle: { width: 40, height: 40, borderRadius: 20, backgroundColor: PURPLE.main, alignItems: 'center', justifyContent: 'center' },
  moreButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 4, marginHorizontal: SPACING.md, paddingVertical: 10 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 14, color: PURPLE.main },
});
