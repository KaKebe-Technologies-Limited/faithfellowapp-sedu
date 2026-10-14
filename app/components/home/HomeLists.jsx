// Two short lists at the end of Home, each with "See all": the top four podcasts and four sermons. Plain rows with a small
// picture, a name and one line of detail. Podcasts come from GET /api/v1/home `podcasts`, sermons from `sermons`.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { mediaUrl } from '../../lib/api';
import { formatDuration } from '../../lib/format';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';

function Row({ image, title, line, meta, onPress, label, last }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, !last && styles.divider, pressed && { opacity: 0.85 }]} accessibilityRole="button" accessibilityLabel={label}>
      <View style={styles.picture}>
        <LinearGradient colors={['#5B1431', COLORS.primary]} style={StyleSheet.absoluteFill} />
        {image ? <Image source={{ uri: mediaUrl(image) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
      </View>
      <View style={{ flex: 1 }}>
        <AppText style={styles.title} numberOfLines={1}>
          {title}
        </AppText>
        <AppText style={styles.line} numberOfLines={1}>
          {line}
        </AppText>
      </View>
      {meta ? <AppText style={styles.meta}>{meta}</AppText> : null}
      <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} />
    </Pressable>
  );
}

export function TopPodcasts({ podcasts }) {
  if (!podcasts?.length) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="Top podcasts" actionLabel="See all" onAction={() => router.push('/podcasts')} />
      <View style={styles.card}>
        {podcasts.slice(0, 4).map((p, i, all) => (
          <Row
            key={p.id}
            image={p.cover_url}
            title={p.name}
            line={[p.host, p.category_label].filter(Boolean).join(' · ')}
            meta={`${p.episodes} ep`}
            label={`Podcast ${p.name}`}
            last={i === all.length - 1}
            onPress={() => router.push({ pathname: '/podcast/[id]', params: { id: p.id } })}
          />
        ))}
      </View>
    </View>
  );
}

export function LatestSermons({ sermons }) {
  if (!sermons?.length) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="Sermons" actionLabel="See all" onAction={() => router.push('/sermons')} />
      <View style={styles.card}>
        {sermons.slice(0, 4).map((s, i, all) => (
          <Row
            key={s.id}
            image={s.thumbnail_url}
            title={s.title}
            line={[s.preacher, s.church?.name].filter(Boolean).join(' · ')}
            meta={s.duration_seconds ? formatDuration(s.duration_seconds) : null}
            label={`Sermon ${s.title}`}
            last={i === all.length - 1}
            onPress={() => router.push({ pathname: '/sermon/[id]', params: { id: s.id } })}
          />
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  card: { marginHorizontal: SPACING.md, backgroundColor: SOFT.glass, borderRadius: 24, borderWidth: 1.5, borderColor: SOFT.glassBorder, paddingHorizontal: 14, ...SOFT_SHADOW },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  divider: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: 'rgba(120,80,100,0.18)' },
  picture: { width: 54, height: 54, borderRadius: 16, overflow: 'hidden', backgroundColor: COLORS.primaryMist },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: SOFT.ink },
  line: { fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted, marginTop: 2 },
  meta: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },
});
