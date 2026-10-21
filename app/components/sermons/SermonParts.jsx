// The pieces the Sermons page is built from, in different shapes so the page is not one long list of the same card:
//  - SermonTile: a wide picture card for a sideways row (the newest sermons)
//  - RankRow: a numbered row with a square picture (the most listened)
//  - SeriesCard: a picture card for a series
//  - SermonRow: a picture on the left and the details on the right (the full list)
// A sermon that has audio gets a round play button: it plays right there, in the app's player, with no video and no YouTube.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { formatDuration, timeAgo } from '../../lib/format';
import { playSermonAudio, useSermonAudio } from '../../lib/sermonAudio';
import AppText from '../AppText';
import BrandPlaceholder from '../BrandPlaceholder';

const open = (s) => router.push({ pathname: '/sermon/[id]', params: { id: s.id } });

/** The sermon's picture on the brand colours, with the FaithFellows mark when it has none. */
export function Thumb({ uri, style, children }) {
  return (
    <View style={[styles.thumb, style]}>
      <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
      {uri ? <Image source={{ uri: mediaUrl(uri) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : <BrandPlaceholder />}
      {children}
    </View>
  );
}

/** Round play / pause button for a sermon that has audio. */
export function QuickPlay({ sermon, list, size = 40 }) {
  const { playing, loading } = useSermonAudio(sermon);
  if (!sermon.audio_url) return null;

  return (
    <Pressable
      onPress={() => playSermonAudio(sermon, list)}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={playing ? `Pause ${sermon.title}` : `Listen to ${sermon.title}`}
      style={({ pressed }) => [styles.quick, { width: size, height: size, borderRadius: size / 2 }, pressed && { opacity: 0.85 }]}
    >
      <Ionicons name={loading ? 'hourglass-outline' : playing ? 'pause' : 'play'} size={size * 0.5} color={COLORS.primary} style={playing || loading ? null : { marginLeft: 2 }} />
    </Pressable>
  );
}

/** Small icons that say what the sermon has: audio to listen to, video to watch. */
export function MediaIcons({ sermon, light = false }) {
  const color = light ? '#fff' : COLORS.gray700;
  if (!sermon.audio_url && !sermon.video_url) return null;

  return (
    <View style={styles.icons}>
      {sermon.audio_url ? <Ionicons name="headset" size={13} color={color} /> : null}
      {sermon.video_url ? <Ionicons name="videocam" size={13} color={color} /> : null}
    </View>
  );
}

function DurationBadge({ seconds }) {
  if (!seconds) return null;

  return (
    <View style={styles.duration}>
      <AppText style={styles.durationText}>{formatDuration(seconds)}</AppText>
    </View>
  );
}

/** "3 d ago" this week, "11 Oct 2026" after that. */
const when = (iso) => {
  if (!iso) return '';
  const days = (Date.now() - new Date(iso).getTime()) / 86400000;

  return days < 7 ? timeAgo(iso) : new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
};

const by = (s) => [s.preacher, s.church?.name].filter(Boolean)[0] ?? '';

/** A wide picture card for a sideways row. */
export function SermonTile({ sermon: s, list, width = 248 }) {
  const pictureHeight = Math.round(width * 0.56);

  return (
    <View style={[styles.tile, { width }]}>
      <Pressable onPress={() => open(s)} style={({ pressed }) => pressed && { opacity: 0.94 }} accessibilityRole="button" accessibilityLabel={`Sermon: ${s.title}`}>
        <Thumb uri={s.thumbnail_url} style={{ height: pictureHeight }}>
          <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.55)']} locations={[0.5, 1]} style={StyleSheet.absoluteFill} />
          <View style={styles.tileIcons}>
            <MediaIcons sermon={s} light />
          </View>
          <DurationBadge seconds={s.duration_seconds} />
        </Thumb>
        <View style={styles.tileInfo}>
          <AppText style={styles.tileTitle} numberOfLines={2}>
            {s.title}
          </AppText>
          <AppText variant="caption" numberOfLines={1}>
            {[by(s), when(s.published_at)].filter(Boolean).join(' · ')}
          </AppText>
        </View>
      </Pressable>
      <View style={[styles.tilePlay, { top: pictureHeight - 48 }]}>
        <QuickPlay sermon={s} list={list} size={38} />
      </View>
    </View>
  );
}

/** A numbered row with a square picture: 1, 2, 3 … for the most listened sermons. */
export function RankRow({ sermon: s, rank, list }) {
  return (
    <View style={styles.rank}>
      <Pressable onPress={() => open(s)} style={({ pressed }) => [styles.rowMain, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Number ${rank}: ${s.title}`}>
        <AppText style={[styles.rankNumber, rank <= 3 && { color: COLORS.primary }]}>{rank}</AppText>
        <Thumb uri={s.thumbnail_url} style={styles.rankThumb} />
        <View style={{ flex: 1, gap: 3 }}>
          <AppText style={styles.rowTitle} numberOfLines={2}>
            {s.title}
          </AppText>
          <AppText variant="caption" numberOfLines={1}>
            {by(s)}
          </AppText>
          {s.listens ? (
            <View style={styles.listens}>
              <Ionicons name="headset" size={12} color={COLORS.primary} />
              <AppText style={styles.listensText}>{s.listens.toLocaleString()} listens</AppText>
            </View>
          ) : null}
        </View>
      </Pressable>
      <QuickPlay sermon={s} list={list} size={38} />
    </View>
  );
}

/** A picture card for a series, with its name and how many sermons it has. */
export function SeriesCard({ series, onPress, width = 212 }) {
  return (
    <Pressable onPress={() => onPress(series)} style={({ pressed }) => [styles.series, { width }, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Series: ${series.title}`}>
      <Thumb uri={series.cover_url} style={StyleSheet.absoluteFill}>
        <LinearGradient colors={['rgba(20,6,12,0.05)', 'rgba(20,6,12,0.82)']} locations={[0.2, 1]} style={StyleSheet.absoluteFill} />
        <View style={styles.seriesText}>
          <AppText style={styles.seriesTitle} numberOfLines={2}>
            {series.title}
          </AppText>
          <AppText style={styles.seriesCount} numberOfLines={1}>
            {series.sermons} {series.sermons === 1 ? 'sermon' : 'sermons'}
            {series.church ? ` · ${series.church}` : ''}
          </AppText>
        </View>
      </Thumb>
    </Pressable>
  );
}

/** A picture on the left, the details on the right: for the full list. */
export function SermonRow({ sermon: s, list }) {
  const meta = [s.scripture, when(s.published_at)].filter(Boolean).join(' · ');

  return (
    <View style={styles.row}>
      <Pressable onPress={() => open(s)} style={({ pressed }) => [styles.rowMain, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Sermon: ${s.title}`}>
        <Thumb uri={s.thumbnail_url} style={styles.rowThumb}>
          <DurationBadge seconds={s.duration_seconds} />
        </Thumb>
        <View style={{ flex: 1, gap: 3 }}>
          <AppText style={styles.rowTitle} numberOfLines={2}>
            {s.title}
          </AppText>
          <AppText variant="caption" numberOfLines={1} style={{ color: COLORS.gray900 }}>
            {by(s)}
          </AppText>
          <View style={styles.rowMeta}>
            <MediaIcons sermon={s} />
            {meta ? (
              <AppText variant="caption" numberOfLines={1} style={{ flex: 1 }}>
                {meta}
              </AppText>
            ) : null}
          </View>
        </View>
      </Pressable>
      <QuickPlay sermon={s} list={list} size={38} />
    </View>
  );
}

const styles = StyleSheet.create({
  thumb: { backgroundColor: COLORS.primaryDark, overflow: 'hidden' },
  quick: { backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center', ...SHADOW, shadowOpacity: 0.16, elevation: 3 },
  icons: { flexDirection: 'row', gap: 6, alignItems: 'center' },
  duration: { position: 'absolute', right: 8, bottom: 8, backgroundColor: 'rgba(20,6,12,0.72)', borderRadius: 8, paddingHorizontal: 7, paddingVertical: 2 },
  durationText: { fontFamily: FONTS.semibold, fontSize: 11.5, color: '#fff' },

  tile: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, overflow: 'hidden', ...SHADOW },
  tileIcons: { position: 'absolute', left: 10, top: 10, backgroundColor: 'rgba(20,6,12,0.55)', borderRadius: 10, paddingHorizontal: 8, paddingVertical: 4 },
  tilePlay: { position: 'absolute', left: 10 },
  tileInfo: { padding: 12, gap: 3, minHeight: 74 },
  tileTitle: { fontFamily: FONTS.bold, fontSize: 15, lineHeight: 20, color: COLORS.black },

  rank: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 10, paddingLeft: 14, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW },
  rankNumber: { width: 26, textAlign: 'center', fontFamily: FONTS.bold, fontSize: 24, color: COLORS.gray300 },
  rankThumb: { width: 72, height: 72, borderRadius: 16 },
  listens: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  listensText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },

  series: { height: 124, borderRadius: RADIUS.lg + 2, overflow: 'hidden', ...SHADOW },
  seriesText: { position: 'absolute', left: 12, right: 12, bottom: 11 },
  seriesTitle: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 20, color: '#fff' },
  seriesCount: { fontFamily: FONTS.medium, fontSize: 12, color: 'rgba(255,255,255,0.82)', marginTop: 2 },

  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 10, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW },
  rowMain: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowThumb: { width: 116, height: 76, borderRadius: 14 },
  rowTitle: { fontFamily: FONTS.bold, fontSize: 15, lineHeight: 20, color: COLORS.black },
  rowMeta: { flexDirection: 'row', alignItems: 'center', gap: 8 },
});
