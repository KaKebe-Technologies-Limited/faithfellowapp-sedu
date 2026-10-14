// "Highlights": short summary cards on soft coloured backgrounds that slide along by themselves, one after the other, like the live
// service card: the live service, urgent notices and announcements, the devotional, giving campaigns and the next event. Words
// only. It pauses while you touch it, and a tap opens the item. Built from the one GET /api/v1/home response.
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { dayAndTime, formatMoney } from '../../lib/format';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';

const GAP = 12;
const SLIDE_MS = 4800;
const go = (pathname, id) => router.push({ pathname, params: { id } });

const TONES = {
  live: { colors: ['#4A0F29', '#7D1A39'], ink: '#fff', soft: 'rgba(255,255,255,0.78)', accent: '#FFD98A' },
  urgent: { colors: ['#FFE9E9', '#FFD3D3'], ink: '#7A1B1B', soft: '#9B4A4A', accent: '#D63031' },
  note: { colors: ['#FFF0F6', '#FFE0EC'], ink: '#7A1832', soft: '#9A5568', accent: COLORS.primary },
  devotional: { colors: ['#FFF6E0', '#FFE9BD'], ink: '#6B4309', soft: '#8C6422', accent: '#C77700' },
  campaign: { colors: ['#E4F7EC', '#C9EEDA'], ink: '#0E4D35', soft: '#2F7458', accent: '#12704F' },
  event: { colors: ['#EEE8FB', '#DDD1F6'], ink: '#35205F', soft: '#5F4A8C', accent: '#6B46C1' },
};

function Card({ tone, kicker, title, body, foot, onPress, children, width, label }) {
  const t = TONES[tone];

  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} style={({ pressed }) => [{ width }, pressed && { opacity: 0.93 }]}>
      <LinearGradient colors={t.colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={[styles.glow, { backgroundColor: t.accent }]} />
        <AppText style={[styles.kicker, { color: t.accent }]}>{kicker.toUpperCase()}</AppText>
        <AppText style={[styles.title, { color: t.ink }]} numberOfLines={2}>
          {title}
        </AppText>
        {body ? (
          <AppText style={[styles.body, { color: t.soft }]} numberOfLines={2}>
            {body}
          </AppText>
        ) : null}
        {children}
        {foot ? (
          <AppText style={[styles.foot, { color: t.soft }]} numberOfLines={1}>
            {foot}
          </AppText>
        ) : null}
      </LinearGradient>
    </Pressable>
  );
}

function Progress({ c }) {
  const pct = Math.max(0, Math.min(100, Math.round(c.percent)));

  return (
    <View style={{ marginTop: 10 }}>
      <View style={styles.track}>
        <View style={[styles.fill, { width: `${Math.max(pct, 3)}%` }]} />
      </View>
      <AppText style={[styles.body, { color: TONES.campaign.soft, marginTop: 5 }]} numberOfLines={1}>
        {formatMoney(c.raised, c.currency)} of {formatMoney(c.goal, c.currency)} · {pct}%
      </AppText>
    </View>
  );
}

export default function Highlights({ home }) {
  const { width: screen } = useWindowDimensions();
  const width = Math.min(screen - SPACING.md * 2 - 24, 340);
  const step = width + GAP;
  const scroll = useRef(null);
  const at = useRef(0);
  const [paused, setPaused] = useState(false);
  const fade = useRef(new Animated.Value(0)).current;

  const live = (home.live ?? []).filter((l) => l.status === 'live');
  const soon = (home.live ?? []).filter((l) => l.status !== 'live');
  const event = home.events?.[0];
  const cards = [
    ...live.map((l) => ({ key: `live${l.id}`, el: (c) => <Card {...c} tone="live" kicker="Live now" title={l.title} foot={l.church?.name} label={`Live: ${l.title}`} onPress={() => go('/live/[id]', l.id)} /> })),
    ...(home.announcements ?? []).map((a) => ({
      key: `a${a.id}`,
      el: (c) => <Card {...c} tone={a.urgent ? 'urgent' : 'note'} kicker={a.urgent ? 'Urgent notice' : 'Announcement'} title={a.title} body={a.body} foot={a.church?.name} label={`Announcement: ${a.title}`} onPress={() => go('/announcement/[id]', a.id)} />,
    })),
    ...(home.devotional
      ? [{
          key: `d${home.devotional.id}`,
          el: (c) => <Card {...c} tone="devotional" kicker="Devotional" title={home.devotional.title} body={home.devotional.scripture_text ? `“${home.devotional.scripture_text}”` : home.devotional.excerpt} foot={home.devotional.scripture || home.devotional.church?.name} label={`Devotional: ${home.devotional.title}`} onPress={() => go('/devotional/[id]', home.devotional.id)} />,
        }]
      : []),
    ...(home.campaigns ?? []).map((k) => ({
      key: `c${k.id}`,
      el: (c) => (
        <Card {...c} tone="campaign" kicker="Fundraising" title={k.title} foot={k.church?.name} label={`Fundraising: ${k.title}`} onPress={() => go('/campaign/[id]', k.id)}>
          <Progress c={k} />
        </Card>
      ),
    })),
    ...(event
      ? [{
          key: `e${event.id}`,
          el: (c) => <Card {...c} tone="event" kicker="Next event" title={event.title} body={dayAndTime(event.starts_at)} foot={[event.location?.name, event.church?.name].filter(Boolean).join(' · ')} label={`Event: ${event.title}`} onPress={() => go('/event/[id]', event.id)} />,
        }]
      : []),
    ...soon.map((l) => ({ key: `s${l.id}`, el: (c) => <Card {...c} tone="live" kicker="Live service" title={l.title} body={dayAndTime(l.scheduled_at)} foot={l.church?.name} label={`Upcoming live service: ${l.title}`} onPress={() => go('/live/[id]', l.id)} /> })),
  ];
  const count = cards.length;

  useEffect(() => {
    if (count < 2 || paused) return undefined;
    const timer = setInterval(() => {
      at.current = (at.current + 1) % count;
      scroll.current?.scrollTo({ x: at.current * step, animated: true });
    }, SLIDE_MS);

    return () => clearInterval(timer);
  }, [count, paused, step]);

  useEffect(() => {
    Animated.timing(fade, { toValue: 1, duration: 500, easing: Easing.out(Easing.cubic), useNativeDriver: true }).start();
  }, [fade]);

  if (!count) return null;

  return (
    <Animated.View style={[styles.section, { opacity: fade }]}>
      <SectionHeader title="Highlights" />
      <ScrollView
        ref={scroll}
        horizontal
        showsHorizontalScrollIndicator={false}
        snapToInterval={step}
        decelerationRate="fast"
        contentContainerStyle={styles.rail}
        onScrollBeginDrag={() => setPaused(true)}
        onMomentumScrollEnd={(e) => {
          at.current = Math.round(e.nativeEvent.contentOffset.x / step);
          setPaused(false);
        }}
      >
        {cards.map(({ key, el }) => (
          <View key={key}>{el({ width })}</View>
        ))}
      </ScrollView>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.md },
  rail: { paddingHorizontal: SPACING.md, gap: GAP, paddingBottom: 14 },
  card: { minHeight: 150, borderRadius: 24, padding: 18, overflow: 'hidden', borderWidth: 1.5, borderColor: SOFT.glassBorder, ...SOFT_SHADOW },
  glow: { position: 'absolute', right: -36, top: -44, width: 130, height: 130, borderRadius: 65, opacity: 0.12 },
  kicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.5, marginBottom: 6 },
  title: { fontFamily: FONTS.bold, fontSize: 18, lineHeight: 24 },
  body: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 19, marginTop: 4 },
  foot: { fontFamily: FONTS.medium, fontSize: 12, marginTop: 10 },
  track: { height: 8, borderRadius: 4, backgroundColor: 'rgba(18,112,79,0.16)', overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: '#12704F' },
});
