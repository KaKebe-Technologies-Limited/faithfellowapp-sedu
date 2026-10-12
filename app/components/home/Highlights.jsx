// "Highlights" rail on the home screen: swipeable cards for what matters now in your churches —
// urgent notices first, then upcoming events, today's devotional, giving campaigns and the latest
// sermons. Built from the one GET /api/v1/home response.
import { Linking, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { dateTile, dayAndTime, formatDuration, formatMoney } from '../../lib/format';
import AppText from '../AppText';
import Pill from '../Pill';
import SectionHeader from '../SectionHeader';

const CARD_W = 284;
const CARD_H = 184;
const open = (url) => url && Linking.openURL(mediaUrl(url));

/** Photo (or brand gradient) backdrop with a dark fade, for the image-led cards. */
function Backdrop({ uri, colors = [COLORS.primaryDark, COLORS.primary] }) {
  return (
    <>
      <LinearGradient colors={colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      {uri ? <Image source={{ uri: mediaUrl(uri) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} /> : null}
      <LinearGradient colors={['rgba(20,6,12,0.05)', 'rgba(20,6,12,0.35)', 'rgba(20,6,12,0.82)']} locations={[0, 0.5, 1]} style={StyleSheet.absoluteFill} />
    </>
  );
}

function Frame({ onPress, style, label, children }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} style={({ pressed }) => [styles.card, style, pressed && { opacity: 0.92 }]}>
      {children}
    </Pressable>
  );
}

function AnnouncementCard({ a }) {
  return (
    <Frame label={`Announcement: ${a.title}`} style={[styles.light, a.urgent && styles.urgent]}>
      <Pill label={a.urgent ? 'Urgent' : 'Announcement'} icon="megaphone-outline" tone={a.urgent ? 'urgent' : 'soft'} />
      <AppText style={styles.cardTitle} numberOfLines={2}>
        {a.title}
      </AppText>
      <AppText style={styles.cardBody} numberOfLines={2}>
        {a.body}
      </AppText>
      <AppText variant="caption" style={styles.foot} numberOfLines={1}>
        {a.church?.name}
      </AppText>
    </Frame>
  );
}

function EventCard({ e }) {
  const tile = e.starts_at ? dateTile(e.starts_at) : null;
  return (
    <Frame onPress={() => open(e.web_url)} label={`Event: ${e.title}`}>
      <Backdrop uri={e.cover_url} />
      <View style={styles.topRow}>
        {tile ? (
          <View style={styles.tile}>
            <AppText style={styles.tileMonth}>{tile.month}</AppText>
            <AppText style={styles.tileDay}>{tile.day}</AppText>
          </View>
        ) : null}
        <Pill label="Event" icon="calendar-outline" tone="dark" />
      </View>
      <View style={styles.bottom}>
        <AppText style={styles.onPhotoTitle} numberOfLines={2}>
          {e.title}
        </AppText>
        <AppText style={styles.onPhotoMeta} numberOfLines={1}>
          {[dayAndTime(e.starts_at), e.location?.name].filter(Boolean).join(' · ')}
        </AppText>
        <AppText style={styles.onPhotoChurch} numberOfLines={1}>
          {e.church?.name}
        </AppText>
      </View>
    </Frame>
  );
}

function DevotionalCard({ d }) {
  return (
    <Frame onPress={() => open(d.web_url)} label={`Devotional: ${d.title}`} style={styles.pinkCard}>
      <Pill label="Devotional of the day" icon="book-outline" tone="white" />
      <AppText style={styles.quote} numberOfLines={4}>
        {d.scripture_text ? `“${d.scripture_text}”` : d.excerpt}
      </AppText>
      <AppText style={styles.scripture} numberOfLines={1}>
        {d.scripture || d.title}
      </AppText>
      <AppText variant="caption" style={styles.foot} numberOfLines={1}>
        {d.title} · {d.church?.name}
      </AppText>
    </Frame>
  );
}

function CampaignCard({ c }) {
  const pct = Math.max(0, Math.min(100, Math.round(c.percent)));
  return (
    <Frame onPress={() => open(c.web_url)} label={`Campaign: ${c.title}`} style={styles.light}>
      <View style={styles.topRow}>
        <Pill label="Campaign" icon="flag-outline" />
        <View style={styles.give}>
          <AppText style={styles.giveText}>Give</AppText>
        </View>
      </View>
      <AppText style={styles.cardTitle} numberOfLines={2}>
        {c.title}
      </AppText>
      <View style={styles.track}>
        <View style={[styles.fill, { width: `${Math.max(pct, 3)}%` }]} />
      </View>
      <View style={styles.progressRow}>
        <AppText variant="caption" style={{ color: COLORS.gray900 }} numberOfLines={1}>
          {formatMoney(c.raised, c.currency)} of {formatMoney(c.goal, c.currency)}
        </AppText>
        <AppText style={styles.percent}>{pct}%</AppText>
      </View>
    </Frame>
  );
}

function SermonCard({ s }) {
  return (
    <Frame onPress={() => router.push('/(tabs)/sermons')} label={`Sermon: ${s.title}`}>
      <Backdrop uri={s.thumbnail_url} colors={['#2A0A14', COLORS.primaryDark]} />
      <View style={styles.topRow}>
        <Pill label="Sermon" icon="mic-outline" tone="dark" />
        <View style={styles.play}>
          <Ionicons name="play" size={20} color={COLORS.primary} style={{ marginLeft: 2 }} />
        </View>
      </View>
      <View style={styles.bottom}>
        <AppText style={styles.onPhotoTitle} numberOfLines={2}>
          {s.title}
        </AppText>
        <AppText style={styles.onPhotoMeta} numberOfLines={1}>
          {[s.preacher, s.duration_seconds ? formatDuration(s.duration_seconds) : null].filter(Boolean).join(' · ')}
        </AppText>
        <AppText style={styles.onPhotoChurch} numberOfLines={1}>
          {s.church?.name}
        </AppText>
      </View>
    </Frame>
  );
}

function SoonCard({ stream }) {
  return (
    <Frame onPress={() => open(stream.watch_url)} label={`Upcoming live service: ${stream.title}`}>
      <Backdrop colors={['#2A0A14', '#5B1229']} />
      <View style={styles.topRow}>
        <Pill label="Live service" icon="radio-outline" tone="dark" />
      </View>
      <View style={styles.bottom}>
        <AppText style={styles.onPhotoTitle} numberOfLines={2}>
          {stream.title}
        </AppText>
        <AppText style={styles.onPhotoMeta} numberOfLines={1}>
          {dayAndTime(stream.scheduled_at)}
        </AppText>
        <AppText style={styles.onPhotoChurch} numberOfLines={1}>
          {stream.church?.name}
        </AppText>
      </View>
    </Frame>
  );
}

export default function Highlights({ home }) {
  const cards = [
    ...(home.announcements ?? []).map((a) => ({ key: `a${a.id}`, node: <AnnouncementCard a={a} /> })),
    ...(home.events ?? []).slice(0, 3).map((e) => ({ key: `e${e.id}`, node: <EventCard e={e} /> })),
    ...(home.devotional ? [{ key: `d${home.devotional.id}`, node: <DevotionalCard d={home.devotional} /> }] : []),
    ...(home.campaigns ?? []).map((c) => ({ key: `c${c.id}`, node: <CampaignCard c={c} /> })),
    ...(home.sermons ?? []).slice(0, 3).map((s) => ({ key: `s${s.id}`, node: <SermonCard s={s} /> })),
    ...(home.live ?? []).filter((l) => l.status !== 'live').map((l) => ({ key: `l${l.id}`, node: <SoonCard stream={l} /> })),
  ];

  if (!cards.length) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="Highlights" />
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        snapToInterval={CARD_W + 12}
        decelerationRate="fast"
        contentContainerStyle={styles.rail}
      >
        {cards.map(({ key, node }) => (
          <View key={key}>{node}</View>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.md },
  rail: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 8 },
  card: { width: CARD_W, height: CARD_H, borderRadius: RADIUS.xl - 4, overflow: 'hidden', padding: 14, backgroundColor: COLORS.primaryDark, ...SHADOW },
  light: { backgroundColor: COLORS.white },
  urgent: { backgroundColor: '#FFF6F6', borderWidth: 1.5, borderColor: COLORS.danger },
  pinkCard: { backgroundColor: COLORS.pink },
  topRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between' },
  bottom: { position: 'absolute', left: 14, right: 14, bottom: 14 },
  cardTitle: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 21, color: COLORS.black, marginTop: 10 },
  cardBody: { fontFamily: FONTS.regular, fontSize: 13, lineHeight: 18, color: COLORS.gray700, marginTop: 4 },
  foot: { position: 'absolute', left: 14, right: 14, bottom: 12 },
  onPhotoTitle: { fontFamily: FONTS.bold, fontSize: 18, lineHeight: 23, color: COLORS.white },
  onPhotoMeta: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.9)', marginTop: 3 },
  onPhotoChurch: { fontFamily: FONTS.regular, fontSize: 11.5, color: 'rgba(255,255,255,0.7)', marginTop: 2 },
  tile: { backgroundColor: COLORS.white, borderRadius: 12, width: 50, paddingVertical: 5, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 10, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 23, color: COLORS.black },
  quote: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 15, lineHeight: 21, color: COLORS.gray900, marginTop: 12 },
  scripture: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.primary, marginTop: 8 },
  give: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 5 },
  giveText: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.white },
  track: { height: 8, borderRadius: 4, backgroundColor: COLORS.primaryMist, marginTop: 14, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: COLORS.primary },
  progressRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 8 },
  percent: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  play: { width: 40, height: 40, borderRadius: 20, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
});
