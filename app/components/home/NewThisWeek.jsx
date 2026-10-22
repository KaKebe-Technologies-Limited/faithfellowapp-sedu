// "What's new": large picture cards in a sideways row, one tap each: the live service, urgent notices and announcements, events, giving
// campaigns (with how much has been raised), the devotional and the latest sermons. Built from the one GET /api/v1/home response.
// `withHighlights` is false when the slider at the top of Home already shows the highlights, so only the notices and the services
// that are still to come are listed here.
import { Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { dayAndTime, formatMoney } from '../../lib/format';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';
import { openItem } from './HeroCarousel';

const GAP = 12;

// Each kind has its own deep colour for the cards that have no picture of their own.
const TINTS = {
  live: ['#2A0A18', '#7D1A39'],
  announcement: ['#3A0B1C', '#9B2347'],
  urgent: ['#5A0F14', '#C0392B'],
  event: ['#25154A', '#5B3CC4'],
  campaign: ['#07382A', '#12704F'],
  devotional: ['#4A2D05', '#C77700'],
  sermon: ['#0B2A5B', '#1D5FD1'],
};

/** [{ kind, id, label, title, subtitle, image, church, urgent }] for the row. */
export function newThisWeek(home, withHighlights = true) {
  const items = [];
  const notices = (home.announcements ?? []).map((a) => ({ kind: 'announcement', id: a.id, label: a.urgent ? 'Urgent notice' : 'Announcement', title: a.title, subtitle: a.body, image: null, church: a.church?.name, urgent: a.urgent }));
  const soon = (home.live ?? []).filter((l) => l.status !== 'live').map((l) => ({ kind: 'live', id: l.id, label: 'Live service', title: l.title, subtitle: dayAndTime(l.scheduled_at), image: null, church: l.church?.name }));

  // Urgent notices lead; the other notices come after the highlights.
  items.push(...notices.filter((n) => n.urgent));
  if (withHighlights) {
    const campaigns = new Map((home.campaigns ?? []).map((c) => [c.id, c]));
    for (const h of home.highlights ?? []) {
      const c = h.kind === 'campaign' ? campaigns.get(h.id) : null;
      items.push(c ? { ...h, subtitle: `${formatMoney(c.raised, c.currency)} raised · ${Math.round(c.percent)}% of the goal` } : h);
    }
  }
  items.push(...soon, ...notices.filter((n) => !n.urgent));

  return items.filter((i) => i.title).slice(0, 12);
}

function Card({ item, width }) {
  const tint = TINTS[item.urgent ? 'urgent' : item.kind] ?? TINTS.live;

  return (
    <Pressable onPress={() => openItem(item)} style={({ pressed }) => [{ width }, pressed && { opacity: 0.93 }]} accessibilityRole="button" accessibilityLabel={`${item.label}: ${item.title}`}>
      <View style={styles.card}>
        <LinearGradient colors={tint} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        {item.image ? <Image source={{ uri: mediaUrl(item.image) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} /> : null}
        <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.28)', 'rgba(20,6,12,0.86)']} locations={[0.15, 0.5, 1]} style={StyleSheet.absoluteFill} />
        <View style={styles.badge}>
          <AppText style={styles.badgeText}>{String(item.label ?? '').toUpperCase()}</AppText>
        </View>
        <View style={styles.text}>
          <AppText style={styles.title} numberOfLines={2}>
            {item.title}
          </AppText>
          {item.subtitle || item.church ? (
            <AppText style={styles.sub} numberOfLines={1}>
              {[item.subtitle, item.church].filter(Boolean).join(' · ')}
            </AppText>
          ) : null}
        </View>
      </View>
    </Pressable>
  );
}

export default function NewThisWeek({ home, withHighlights = true }) {
  const { width: screen } = useWindowDimensions();
  const width = Math.min(Math.round(screen * 0.78), 330);
  const items = newThisWeek(home, withHighlights);
  if (!items.length) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="What's new" />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={width + GAP} decelerationRate="fast" contentContainerStyle={styles.rail}>
        {items.map((item, i) => (
          <Card key={`${item.kind}-${item.id ?? i}-${i}`} item={item} width={width} />
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  rail: { paddingHorizontal: SPACING.md, gap: GAP },
  card: { height: 176, borderRadius: 24, overflow: 'hidden', backgroundColor: COLORS.primaryDark, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.7)' },
  badge: { position: 'absolute', left: 14, top: 14, backgroundColor: 'rgba(20,6,12,0.5)', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 1.3, color: '#fff' },
  text: { position: 'absolute', left: 16, right: 16, bottom: 14 },
  title: { fontFamily: FONTS.bold, fontSize: 19, lineHeight: 24, color: '#fff' },
  sub: { fontFamily: FONTS.medium, fontSize: 12.5, lineHeight: 17, color: 'rgba(255,255,255,0.82)', marginTop: 3 },
});
