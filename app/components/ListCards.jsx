// Vertical cards for list screens: a 16:9 cover (the church's photo, or the generated artwork) with the
// details underneath. One card per kind — event, campaign, sermon, devotional — so every list in the app looks
// like it belongs to the same family. Tapping opens the matching in-app screen.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import BrandPlaceholder from './BrandPlaceholder';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { dateTile, dayAndTime, formatDuration, formatMoney } from '../lib/format';
import AppText from './AppText';
import ChurchAvatar from './ChurchAvatar';
import Pill from './Pill';

function Cover({ uri, children, height = 168 }) {
  return (
    <View style={[styles.cover, { height }]}>
      <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
      {uri ? <Image source={{ uri: mediaUrl(uri) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : <BrandPlaceholder />}
      <LinearGradient colors={['rgba(20,6,12,0.0)', 'rgba(20,6,12,0.5)']} locations={[0.45, 1]} style={StyleSheet.absoluteFill} />
      {children}
    </View>
  );
}

function ChurchLine({ church }) {
  return (
    <View style={styles.churchLine}>
      <ChurchAvatar church={church} size={22} />
      <AppText variant="caption" numberOfLines={1} style={{ flex: 1 }}>
        {church?.name}
      </AppText>
    </View>
  );
}

export function EventListCard({ event }) {
  const tile = event.starts_at ? dateTile(event.starts_at) : null;
  return (
    <Pressable onPress={() => router.push({ pathname: '/event/[id]', params: { id: event.id } })} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Event: ${event.title}`}>
      <Cover uri={event.cover_url}>
        {tile ? (
          <View style={styles.tile}>
            <AppText style={styles.tileMonth}>{tile.month}</AppText>
            <AppText style={styles.tileDay}>{tile.day}</AppText>
          </View>
        ) : null}
        <View style={styles.coverPills}>
          {event.is_online ? <Pill label="Online" icon="videocam-outline" tone="dark" /> : null}
          <Pill label={event.is_paid ? 'Tickets' : 'Free'} tone="dark" />
        </View>
      </Cover>
      <View style={styles.info}>
        <AppText style={styles.title} numberOfLines={2}>
          {event.title}
        </AppText>
        <AppText variant="caption" numberOfLines={1} style={styles.meta}>
          {[dayAndTime(event.starts_at), event.location?.name].filter(Boolean).join(' · ')}
        </AppText>
        <ChurchLine church={event.church} />
      </View>
    </Pressable>
  );
}

export function CampaignListCard({ campaign: c }) {
  const pct = Math.max(0, Math.min(100, Math.round(c.percent)));
  return (
    <Pressable onPress={() => router.push({ pathname: '/campaign/[id]', params: { id: c.id } })} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Campaign: ${c.title}`}>
      <Cover uri={c.cover_url} height={150}>
        <View style={styles.coverPills}>
          <Pill label={c.type || 'Campaign'} icon="flag-outline" tone="dark" />
        </View>
      </Cover>
      <View style={styles.info}>
        <AppText style={styles.title} numberOfLines={2}>
          {c.title}
        </AppText>
        <View style={styles.track}>
          <View style={[styles.fill, { width: `${Math.max(pct, 2)}%` }]} />
        </View>
        <View style={styles.progress}>
          <AppText variant="caption" style={{ color: COLORS.gray900 }} numberOfLines={1}>
            {formatMoney(c.raised, c.currency)} of {formatMoney(c.goal, c.currency)}
          </AppText>
          <AppText style={styles.pct}>{pct}%</AppText>
        </View>
        <ChurchLine church={c.church} />
      </View>
    </Pressable>
  );
}

export function SermonListCard({ sermon: s }) {
  const meta = [s.preacher, s.scripture, s.duration_seconds ? formatDuration(s.duration_seconds) : null].filter(Boolean).join(' · ');
  return (
    <Pressable onPress={() => router.push({ pathname: '/sermon/[id]', params: { id: s.id } })} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Sermon: ${s.title}`}>
      <Cover uri={s.thumbnail_url}>
        <View style={styles.play}>
          <Ionicons name="play" size={22} color={COLORS.primary} style={{ marginLeft: 2 }} />
        </View>
        {s.series ? (
          <View style={styles.coverPills}>
            <Pill label={s.series} tone="dark" />
          </View>
        ) : null}
      </Cover>
      <View style={styles.info}>
        <AppText style={styles.title} numberOfLines={2}>
          {s.title}
        </AppText>
        {meta ? (
          <AppText variant="caption" numberOfLines={1} style={styles.meta}>
            {meta}
          </AppText>
        ) : null}
        <ChurchLine church={s.church} />
      </View>
    </Pressable>
  );
}

export function DevotionalListCard({ devotional: d }) {
  return (
    <Pressable onPress={() => router.push({ pathname: '/devotional/[id]', params: { id: d.id } })} style={({ pressed }) => [styles.row, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Devotional: ${d.title}`}>
      <View style={styles.rowThumb}>
        <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
        {d.cover_url ? <Image source={{ uri: mediaUrl(d.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : <BrandPlaceholder />}
      </View>
      <View style={{ flex: 1, gap: 3 }}>
        <AppText style={styles.title} numberOfLines={2}>
          {d.title}
        </AppText>
        {d.scripture ? (
          <AppText style={{ fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary }} numberOfLines={1}>
            {d.scripture}
          </AppText>
        ) : null}
        <AppText variant="caption" numberOfLines={2}>
          {d.excerpt}
        </AppText>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, overflow: 'hidden', marginHorizontal: SPACING.md, marginBottom: 14, ...SHADOW },
  cover: { backgroundColor: COLORS.primaryDark, justifyContent: 'flex-end' },
  coverPills: { position: 'absolute', right: 10, top: 10, flexDirection: 'row', gap: 6 },
  tile: { position: 'absolute', left: 12, top: 12, backgroundColor: COLORS.white, borderRadius: 13, width: 52, paddingVertical: 6, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 21, lineHeight: 24, color: COLORS.black },
  play: { position: 'absolute', alignSelf: 'center', top: '36%', width: 52, height: 52, borderRadius: 26, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  info: { padding: 14, gap: 4 },
  title: { fontFamily: FONTS.bold, fontSize: 16.5, lineHeight: 22, color: COLORS.black },
  meta: { fontSize: 12.5 },
  churchLine: { flexDirection: 'row', alignItems: 'center', gap: 7, marginTop: 6 },
  track: { height: 7, borderRadius: 4, backgroundColor: COLORS.primaryMist, marginTop: 8, overflow: 'hidden' },
  fill: { height: 7, borderRadius: 4, backgroundColor: COLORS.primary },
  progress: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 4 },
  pct: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  row: { flexDirection: 'row', gap: 12, alignItems: 'center', backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, marginHorizontal: SPACING.md, marginBottom: 12, ...SHADOW },
  rowThumb: { width: 84, height: 84, borderRadius: 16, overflow: 'hidden', backgroundColor: COLORS.primaryDark },
});
