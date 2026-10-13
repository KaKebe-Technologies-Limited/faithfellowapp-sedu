// One card in the home feed, styled like the website's mobile feed: church avatar with "posted an event ·
// 6 hours ago", a pink type tag on the right, then a layout per type — date tile for events, thumbnail
// for sermons, pink quote card for devotionals, red-bordered urgent notices, and prayer cards with a
// working "I prayed" button. Unknown types fall back to a plain title + text card.
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import api, { mediaUrl } from '../lib/api';
import { dateTile, dayAndTime, excerpt, formatDuration, initials, timeAgo } from '../lib/format';
import AppText from './AppText';
import ChurchAvatar from './ChurchAvatar';
import Pill from './Pill';

const TYPES = {
  sermon: { icon: 'mic-outline', label: 'Sermon', verb: 'shared a sermon' },
  devotional: { icon: 'book-outline', label: 'Devotional' },
  event: { icon: 'calendar-outline', label: 'Event', verb: 'posted an event' },
  announcement: { icon: 'megaphone-outline', label: 'Announcement' },
  post: { icon: 'newspaper-outline', label: 'Update' },
  live: { icon: 'radio-outline', label: 'Live', verb: 'is live' },
  campaign_update: { icon: 'flag-outline', label: 'Campaign' },
  testimony: { icon: 'sparkles-outline', label: 'Testimony' },
};

const go = (pathname, id) => router.push({ pathname, params: { id } });

/** Initials on a pink circle, for people (prayer and testimony authors) rather than churches. */
function PersonAvatar({ name, anonymous }) {
  return (
    <View style={styles.person}>
      {anonymous ? <Ionicons name="person" size={18} color={COLORS.white} /> : <AppText style={{ fontFamily: FONTS.bold, fontSize: 13, color: COLORS.white }}>{initials(name)}</AppText>}
    </View>
  );
}

function Header({ entry, type }) {
  const { item } = entry;
  const person = entry.type === 'prayer' || entry.type === 'testimony';
  const anonymous = person && !item.author;
  const name = person ? item.author || 'Anonymous' : entry.church?.name;
  const when = timeAgo(entry.at);
  const caption = person ? `${entry.church?.name} · ${when}` : entry.type === 'devotional' ? when : [type?.verb, when].filter(Boolean).join(' · ');

  return (
    <View style={styles.header}>
      {person ? <PersonAvatar name={name} anonymous={anonymous} /> : <ChurchAvatar church={entry.church} size={38} />}
      <View style={styles.headerText}>
        <AppText style={styles.name} numberOfLines={2}>
          {name}
        </AppText>
        <AppText variant="caption" numberOfLines={1}>
          {caption}
        </AppText>
      </View>
      {type && entry.type !== 'prayer' ? (
        entry.type === 'announcement' && item.urgent ? (
          <Pill label="Urgent" icon="megaphone-outline" tone="urgent" />
        ) : (
          <Pill label={type.label} icon={type.icon} tone={entry.type === 'live' ? 'live' : 'soft'} />
        )
      ) : null}
    </View>
  );
}

function EventBody({ item }) {
  const tile = item.starts_at ? dateTile(item.starts_at) : null;
  const action = item.is_paid ? 'Tickets' : item.rsvp_enabled ? 'RSVP' : 'View';

  return (
    <Pressable onPress={() => go('/event/[id]', item.id)} style={styles.eventRow} accessibilityRole="button" accessibilityLabel={`${item.title}, ${action}`}>
      {tile ? (
        <View style={styles.tile}>
          <AppText style={styles.tileMonth}>{tile.month}</AppText>
          <AppText style={styles.tileDay}>{tile.day}</AppText>
        </View>
      ) : null}
      <View style={{ flex: 1 }}>
        <AppText style={styles.title} numberOfLines={2}>
          {item.title}
        </AppText>
        <AppText variant="caption" style={styles.meta} numberOfLines={2}>
          {[dayAndTime(item.starts_at), item.location?.name].filter(Boolean).join(' · ')}
        </AppText>
      </View>
      <View style={styles.action}>
        <AppText style={styles.actionText}>{action}</AppText>
      </View>
    </Pressable>
  );
}

function SermonBody({ item }) {
  const meta = [item.preacher, item.scripture, item.duration_seconds ? `${Math.round(item.duration_seconds / 60)} min` : null].filter(Boolean).join(' · ');

  return (
    <Pressable onPress={() => go('/sermon/[id]', item.id)} style={styles.sermonRow} accessibilityRole="button" accessibilityLabel={`Open sermon ${item.title}`}>
      <View style={styles.thumb}>
        {item.thumbnail_url ? <Image source={{ uri: mediaUrl(item.thumbnail_url) }} style={StyleSheet.absoluteFill} contentFit="cover" /> : null}
        {!item.thumbnail_url ? <LinearGradient colors={[COLORS.primaryLight, COLORS.primaryDark]} style={StyleSheet.absoluteFill} /> : null}
        <View style={styles.playDot}>
          <Ionicons name="play" size={16} color={COLORS.primary} style={{ marginLeft: 2 }} />
        </View>
      </View>
      <View style={{ flex: 1, gap: 3 }}>
        <AppText style={styles.title} numberOfLines={2}>
          {item.title}
        </AppText>
        {meta ? (
          <AppText variant="caption" numberOfLines={2}>
            {meta}
          </AppText>
        ) : null}
        {item.series ? <Pill label={item.series} style={{ marginTop: 3 }} /> : null}
      </View>
    </Pressable>
  );
}

function PrayerBody({ item }) {
  const [prayed, setPrayed] = useState(!!item.prayed);
  const [count, setCount] = useState(item.prayed_count ?? 0);

  async function toggle() {
    const next = !prayed;
    setPrayed(next);
    setCount((c) => Math.max(0, c + (next ? 1 : -1)));
    try {
      const { data } = await api.post(`/prayer/${item.id}/pray`);
      setPrayed(!!data.prayed);
      setCount(data.count ?? count);
    } catch {
      setPrayed(!next);
      setCount((c) => Math.max(0, c + (next ? -1 : 1)));
    }
  }

  return (
    <>
      {item.title ? (
        <AppText style={styles.title} numberOfLines={2}>
          {item.title}
        </AppText>
      ) : null}
      <AppText style={styles.body}>{excerpt(item.body, 320)}</AppText>
      <View style={styles.prayerRow}>
        <Pressable onPress={toggle} style={[styles.prayed, prayed && styles.prayedOn]} accessibilityRole="button" accessibilityState={{ selected: prayed }} accessibilityLabel={`I prayed, ${count}`}>
          <AppText style={styles.prayedEmoji}>🙏</AppText>
          <AppText style={[styles.prayedText, prayed && { color: COLORS.white }]}>I prayed {count}</AppText>
        </Pressable>
        <Pressable onPress={() => go('/prayer/[id]', item.id)} style={styles.openLink} accessibilityRole="link" hitSlop={8}>
          <AppText variant="label" style={{ color: COLORS.primary }}>
            Open
          </AppText>
          <Ionicons name="arrow-forward" size={14} color={COLORS.primary} />
        </Pressable>
      </View>
    </>
  );
}

function Body({ entry }) {
  const { item } = entry;

  switch (entry.type) {
    case 'event':
      return <EventBody item={item} />;
    case 'sermon':
      return <SermonBody item={item} />;
    case 'prayer':
      return <PrayerBody item={item} />;
    case 'devotional':
      return (
        <Pressable onPress={() => go('/devotional/[id]', item.id)} accessibilityRole="button" accessibilityLabel={`Devotional: ${item.title}`}>
          <AppText style={[styles.title, styles.devotionalTitle]}>{item.title}</AppText>
          {item.scripture_text ? <AppText style={styles.quote}>“{item.scripture_text}”</AppText> : <AppText style={styles.body}>{excerpt(item.body, 200)}</AppText>}
          {item.scripture ? <AppText style={styles.scripture}>{item.scripture}</AppText> : null}
        </Pressable>
      );
    case 'live':
      return (
        <Pressable onPress={() => go('/live/[id]', item.id)} style={styles.eventRow} accessibilityRole="button" accessibilityLabel={`Watch ${item.title}`}>
          <View style={{ flex: 1 }}>
            <AppText style={styles.title}>{item.title}</AppText>
          </View>
          <View style={[styles.action, { backgroundColor: COLORS.danger }]}>
            <AppText style={[styles.actionText, { color: COLORS.white }]}>Watch</AppText>
          </View>
        </Pressable>
      );
    case 'announcement':
      return (
        <Pressable onPress={() => go('/announcement/[id]', item.id)} accessibilityRole="button" accessibilityLabel={`Announcement: ${item.title}`}>
          {item.title ? <AppText style={[styles.title, { fontSize: 16 }]}>{item.title}</AppText> : null}
          <AppText style={styles.noticeBody}>{excerpt(item.body, 280)}</AppText>
        </Pressable>
      );
    case 'post':
      return (
        <Pressable onPress={() => go('/post/[id]', item.id)} accessibilityRole="button" accessibilityLabel={item.images?.length ? 'Open update with photos' : 'Open update'}>
          {item.title ? <AppText style={styles.title}>{item.title}</AppText> : null}
          {item.body ? <AppText style={styles.body}>{excerpt(item.body, 420)}</AppText> : null}
          {item.images?.length ? (
            <View style={styles.photo}>
              <Image source={{ uri: mediaUrl(item.images[0]) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} />
              {item.images.length > 1 ? (
                <View style={styles.more}>
                  <AppText style={styles.moreText}>+{item.images.length - 1}</AppText>
                </View>
              ) : null}
            </View>
          ) : null}
        </Pressable>
      );
    default:
      return (
        <>
          {item.title ? <AppText style={[styles.title, entry.type === 'testimony' && { fontSize: 18 }]}>{item.title}</AppText> : null}
          {item.body ? <AppText style={styles.body}>{excerpt(item.body, 420)}</AppText> : null}
        </>
      );
  }
}

export default function FeedItem({ entry }) {
  const type = TYPES[entry.type];
  const urgent = entry.type === 'announcement' && entry.item?.urgent;

  return (
    <View style={[styles.card, entry.type === 'prayer' && styles.prayerCard, entry.type === 'devotional' && styles.devotionalCard, urgent && styles.urgentCard]}>
      <Header entry={entry} type={type} />
      <View style={styles.content}>
        <Body entry={entry} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: SPACING.md, marginHorizontal: SPACING.md, marginBottom: 14, ...SHADOW },
  prayerCard: { borderTopWidth: 4, borderTopColor: COLORS.primaryPale },
  devotionalCard: { backgroundColor: COLORS.pink },
  urgentCard: { borderWidth: 1.5, borderColor: COLORS.danger },
  header: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  headerText: { flex: 1 },
  name: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black },
  person: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primaryLight, alignItems: 'center', justifyContent: 'center' },
  content: { marginTop: 12 },
  title: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 22, color: COLORS.black },
  meta: { marginTop: 3 },
  body: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 23, color: '#2B2B2B', marginTop: 6 },
  noticeBody: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray700, marginTop: 4 },
  eventRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  tile: { width: 54, paddingVertical: 7, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 25, color: COLORS.black },
  action: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 15, paddingVertical: 9 },
  actionText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.primary },
  sermonRow: { flexDirection: 'row', gap: 12, alignItems: 'center' },
  thumb: { width: 92, height: 68, borderRadius: 14, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.primaryDark },
  playDot: { width: 32, height: 32, borderRadius: 16, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  prayerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 14 },
  prayed: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  prayedOn: { backgroundColor: COLORS.primary },
  prayedEmoji: { fontSize: 14 },
  prayedText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.primary },
  openLink: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  devotionalTitle: { fontSize: 20, lineHeight: 26 },
  quote: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 15, lineHeight: 22, color: COLORS.gray900, marginTop: 8 },
  scripture: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary, marginTop: 8 },
  photo: { height: 190, borderRadius: 14, overflow: 'hidden', marginTop: 12, backgroundColor: COLORS.gray100 },
  more: { position: 'absolute', right: 10, bottom: 10, backgroundColor: 'rgba(0,0,0,0.55)', borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 4 },
  moreText: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.white },
});
