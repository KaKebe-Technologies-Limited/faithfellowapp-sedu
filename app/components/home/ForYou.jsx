// "For you": the slider of Home. Cards that do something: join the live service, see the next event, give to a campaign, read the devotional,
// listen to the latest sermon, read a notice, pray for the requests waiting, give to your church, open the hymn book. A church's own pictures
// are behind its cards; the rest sit on a deep colour with a big soft icon. Every card has one clear button. The cards are sized from the
// width of the phone (one card and a peek of the next) so they fit every screen, and the slider moves along by itself until you touch it.
// Built from the one GET /api/v1/home response.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { useFeatures } from '../../lib/features';
import { dayAndTime, formatMoney } from '../../lib/format';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';
import { openItem } from './HeroCarousel';

const GAP = 12;
const AUTO_MS = 6000;
const MAX = 9;

// Deep colours for the cards that have no picture, and the icon that sits big and soft behind the words.
const LOOKS = {
  live: { colors: ['#2A0A18', '#B02A52'], icon: 'radio' },
  urgent: { colors: ['#5A0F14', '#C0392B'], icon: 'alert-circle' },
  announcement: { colors: ['#3A0B1C', '#9B2347'], icon: 'megaphone' },
  event: { colors: ['#25154A', '#6B46C1'], icon: 'calendar' },
  campaign: { colors: ['#07382A', '#12704F'], icon: 'heart' },
  devotional: { colors: ['#4A2D05', '#C77700'], icon: 'book' },
  sermon: { colors: ['#0B2A5B', '#1D5FD1'], icon: 'mic' },
  prayer: { colors: ['#4A1F05', '#E65100'], icon: 'hand-left' },
  give: { colors: ['#07382A', '#2E9C6E'], icon: 'heart' },
  hymns: { colors: ['#4A2D05', '#D9961A'], icon: 'musical-notes' },
  games: { colors: ['#2B1766', '#7E5BE0'], icon: 'game-controller' },
  invite: { colors: ['#0B3B44', '#1B9AAA'], icon: 'people' },
};

/** What a highlight from the server asks the person to do. */
const ACTIONS = { live: 'Join the service', event: 'See the event', campaign: 'Give now', devotional: 'Read it now', sermon: 'Listen now', announcement: 'Read it', church: 'Visit the church', slide: 'Visit the church', cover: 'Visit the church' };

const giveTarget = (home) => {
  const church = home.churches?.[0];

  return church && (home.churches?.length ?? 0) === 1 ? () => router.push({ pathname: '/give/[slug]', params: { slug: church.slug } }) : () => router.push('/(tabs)/give');
};

/**
 * The cards, most useful first: the live service and urgent notices, the church's events, campaigns, devotional and sermons (when `withHighlights`),
 * the next services, notices, then things to do any day: pray, give, sing, play. `invite` ({ title, onPress }) adds "invite a friend" when the church has a link.
 */
export function forYou(home, { withHighlights = true, features = {}, invite = null } = {}) {
  const items = [];
  const notices = (home.announcements ?? []).map((a) => ({ key: `a${a.id}`, look: a.urgent ? 'urgent' : 'announcement', kicker: a.urgent ? 'Urgent notice' : 'Announcement', title: a.title, subtitle: a.body, church: a.church?.name, cta: a.urgent ? 'Read now' : 'Read it', onPress: () => router.push({ pathname: '/announcement/[id]', params: { id: a.id } }), urgent: a.urgent }));
  const soon = (home.live ?? []).filter((l) => l.status !== 'live').map((l) => ({ key: `s${l.id}`, look: 'live', kicker: 'Live service', title: l.title, subtitle: dayAndTime(l.scheduled_at), church: l.church?.name, cta: 'See details', onPress: () => router.push({ pathname: '/live/[id]', params: { id: l.id } }) }));

  items.push(...notices.filter((n) => n.urgent));
  if (withHighlights) {
    const campaigns = new Map((home.campaigns ?? []).map((c) => [c.id, c]));
    for (const h of home.highlights ?? []) {
      const c = h.kind === 'campaign' ? campaigns.get(h.id) : null;
      items.push({
        key: `${h.kind}${h.id}`,
        look: h.kind,
        kicker: h.label,
        title: h.title,
        subtitle: c ? `${formatMoney(c.raised, c.currency)} raised · ${Math.round(c.percent)}% of the goal` : h.subtitle,
        church: h.church,
        image: h.image,
        cta: ACTIONS[h.kind] ?? 'Open',
        onPress: () => openItem(h),
      });
    }
  }
  items.push(...soon, ...notices.filter((n) => !n.urgent));

  const open = home.prayers?.open_count ?? 0;
  if (open > 0) {
    items.push({ key: 'prayer', look: 'prayer', kicker: 'Prayer wall', title: open === 1 ? 'One request is waiting for your prayers' : `${open} requests are waiting for your prayers`, subtitle: 'Pray with your church family.', cta: 'Pray now', onPress: () => router.push('/prayer') });
  }
  if (invite) items.push({ key: 'invite', look: 'invite', kicker: 'Invite', title: invite.title, subtitle: 'Share your church link with a friend.', cta: 'Invite a friend', onPress: invite.onPress });
  if (!(home.highlights ?? []).some((h) => h.kind === 'campaign') || !withHighlights) {
    items.push({ key: 'give', look: 'give', kicker: 'Giving', title: 'Sow into your church', subtitle: 'Give by mobile money in a few taps.', cta: 'Give now', onPress: giveTarget(home) });
  }
  if (features.hymns !== false) items.push({ key: 'hymns', look: 'hymns', kicker: 'Hymn book', title: 'Sing along with the hymns', subtitle: 'Hymns in English and Luganda.', cta: 'Open the hymns', onPress: () => router.push('/hymns') });
  items.push({ key: 'games', look: 'games', kicker: 'Games', title: 'Test your Bible knowledge', subtitle: 'Play a Bible quiz with friends.', cta: 'Play now', onPress: () => router.push('/games') });

  return items.filter((i) => i.title).slice(0, MAX);
}

function Card({ item, width, compact }) {
  const look = LOOKS[item.look] ?? LOOKS.live;
  const height = Math.max(196, Math.min(240, Math.round(width * 0.64)));

  return (
    <Pressable onPress={item.onPress} style={({ pressed }) => [{ width, height }, pressed && { opacity: 0.93 }]} accessibilityRole="button" accessibilityLabel={`${item.kicker}: ${item.title}. ${item.cta}`}>
      <View style={styles.card}>
        <LinearGradient colors={look.colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        {item.image ? (
          <Image source={{ uri: mediaUrl(item.image) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} />
        ) : (
          <Ionicons name={look.icon} size={Math.round(width * 0.5)} color="#fff" style={styles.watermark} />
        )}
        <LinearGradient colors={['rgba(20,6,12,0.1)', 'rgba(20,6,12,0.2)', 'rgba(20,6,12,0.82)']} locations={[0, 0.4, 1]} style={StyleSheet.absoluteFill} />

        <View style={styles.badge}>
          <AppText style={styles.badgeText} numberOfLines={1}>
            {String(item.kicker ?? '').toUpperCase()}
          </AppText>
        </View>

        <View style={styles.body}>
          <AppText style={[styles.title, compact && { fontSize: 18, lineHeight: 23 }]} numberOfLines={2}>
            {item.title}
          </AppText>
          {item.subtitle || item.church ? (
            <AppText style={[styles.sub, compact && { fontSize: 12 }]} numberOfLines={1}>
              {[item.subtitle, item.church].filter(Boolean).join(' · ')}
            </AppText>
          ) : null}
          <View style={styles.cta}>
            <AppText style={styles.ctaText} numberOfLines={1}>
              {item.cta}
            </AppText>
            <Ionicons name="arrow-forward" size={15} color={COLORS.primary} />
          </View>
        </View>
      </View>
    </Pressable>
  );
}

export default function ForYou({ home, withHighlights = true, invite = null, title = 'For you' }) {
  const { width: screen } = useWindowDimensions();
  const features = useFeatures();
  const compact = screen < 350;
  const width = Math.min(Math.round(screen * 0.86), 440);
  const step = width + GAP;
  const items = forYou(home, { withHighlights, features, invite });
  const count = items.length;

  const scroll = useRef(null);
  const at = useRef(0);
  const [current, setCurrent] = useState(0);
  const [paused, setPaused] = useState(false);
  const fade = useRef(new Animated.Value(0)).current;

  const goTo = useCallback(
    (next) => {
      at.current = next;
      setCurrent(next);
      scroll.current?.scrollTo({ x: next * step, animated: true });
    },
    [step],
  );

  useEffect(() => {
    if (count < 2 || paused) return undefined;
    const timer = setInterval(() => goTo((at.current + 1) % count), AUTO_MS);

    return () => clearInterval(timer);
  }, [count, paused, goTo]);

  useEffect(() => {
    Animated.timing(fade, { toValue: 1, duration: 450, useNativeDriver: true }).start();
  }, [fade]);

  if (!count) return null;

  return (
    <Animated.View style={[styles.section, { opacity: fade }]}>
      <SectionHeader title={title} />
      <ScrollView
        ref={scroll}
        horizontal
        showsHorizontalScrollIndicator={false}
        snapToInterval={step}
        decelerationRate="fast"
        contentContainerStyle={{ paddingLeft: SPACING.md, paddingRight: Math.max(SPACING.md, screen - width - SPACING.md), gap: GAP }}
        onScrollBeginDrag={() => setPaused(true)}
        onMomentumScrollEnd={(e) => {
          const next = Math.max(0, Math.min(count - 1, Math.round(e.nativeEvent.contentOffset.x / step)));
          at.current = next;
          setCurrent(next);
          setPaused(false);
        }}
      >
        {items.map((item) => (
          <Card key={item.key} item={item} width={width} compact={compact} />
        ))}
      </ScrollView>

      {count > 1 ? (
        <View style={styles.dots} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          {items.map((item, i) => (
            <View key={item.key} style={[styles.dot, i === current && styles.dotOn]} />
          ))}
        </View>
      ) : null}
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  card: { flex: 1, borderRadius: 26, overflow: 'hidden', backgroundColor: COLORS.primaryDark, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.7)' },
  watermark: { position: 'absolute', right: -18, top: -14, opacity: 0.16 },
  badge: { position: 'absolute', left: 14, top: 14, maxWidth: '75%', backgroundColor: 'rgba(20,6,12,0.5)', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 1.3, color: '#fff' },
  body: { position: 'absolute', left: 16, right: 16, bottom: 14 },
  title: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 25, color: '#fff' },
  sub: { fontFamily: FONTS.medium, fontSize: 12.5, lineHeight: 17, color: 'rgba(255,255,255,0.84)', marginTop: 3 },
  cta: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', gap: 6, backgroundColor: '#fff', borderRadius: 999, paddingHorizontal: 14, height: 36, marginTop: 10 },
  ctaText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.primary },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 6, marginTop: 12 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: 'rgba(125,26,57,0.2)' },
  dotOn: { width: 20, backgroundColor: COLORS.primary },
});
