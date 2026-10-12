// One card in the home feed. Handles every item type the API returns (sermon, event, post,
// announcement, devotional, live, campaign update, prayer, testimony) with a sensible fallback.
import { Linking, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, RADIUS, SPACING } from '../constants/theme';
import { excerpt, formatDate, formatDuration, timeAgo } from '../lib/format';
import AppText from './AppText';
import Card from './Card';
import ChurchAvatar from './ChurchAvatar';

const TYPES = {
  sermon: { icon: 'mic-outline', label: 'Sermon' },
  devotional: { icon: 'book-outline', label: 'Devotional' },
  event: { icon: 'calendar-outline', label: 'Event' },
  announcement: { icon: 'megaphone-outline', label: 'Announcement' },
  post: { icon: 'newspaper-outline', label: 'Update' },
  live: { icon: 'radio-outline', label: 'Live' },
  campaign_update: { icon: 'flag-outline', label: 'Campaign' },
  prayer: { icon: 'heart-outline', label: 'Prayer' },
  testimony: { icon: 'sparkles-outline', label: 'Testimony' },
};

function details(entry) {
  const { type, item = {} } = entry;
  if (type === 'sermon') {
    const meta = [item.preacher, item.scripture, item.duration_seconds ? formatDuration(item.duration_seconds) : null].filter(Boolean).join(' · ');
    return { title: item.title, meta, body: null };
  }
  if (type === 'event') {
    const meta = [formatDate(item.starts_at, true), item.location?.name].filter(Boolean).join(' · ');
    return { title: item.title, meta, body: null };
  }
  return { title: item.title, meta: null, body: excerpt(item.body ?? item.description ?? '') };
}

export default function FeedItem({ entry }) {
  const type = TYPES[entry.type] ?? { icon: 'ellipse-outline', label: 'Update' };
  const { title, meta, body } = details(entry);
  const url = entry.item?.web_url;

  return (
    <Card style={styles.card}>
      <View style={styles.header}>
        <ChurchAvatar church={entry.church} size={38} />
        <View style={styles.headerText}>
          <AppText variant="label" numberOfLines={1}>
            {entry.church?.name}
          </AppText>
          <AppText variant="caption">{timeAgo(entry.at)}</AppText>
        </View>
        <View style={[styles.badge, entry.type === 'live' && styles.badgeLive]}>
          <Ionicons name={type.icon} size={13} color={entry.type === 'live' ? COLORS.white : COLORS.primary} />
          <AppText variant="caption" style={[styles.badgeText, entry.type === 'live' && { color: COLORS.white }]}>
            {type.label}
          </AppText>
        </View>
      </View>

      {title ? (
        <AppText variant="heading" style={styles.title}>
          {title}
        </AppText>
      ) : null}
      {meta ? <AppText variant="caption">{meta}</AppText> : null}
      {body ? <AppText style={styles.body}>{body}</AppText> : null}

      {url ? (
        <Pressable onPress={() => Linking.openURL(url)} style={styles.link} accessibilityRole="link">
          <AppText variant="label" style={{ color: COLORS.primary }}>
            Open
          </AppText>
          <Ionicons name="open-outline" size={14} color={COLORS.primary} />
        </Pressable>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  card: { marginBottom: SPACING.md },
  header: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, marginBottom: SPACING.sm },
  headerText: { flex: 1 },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 10, paddingVertical: 4, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  badgeLive: { backgroundColor: COLORS.danger },
  badgeText: { color: COLORS.primary },
  title: { marginTop: 2, marginBottom: 2 },
  body: { marginTop: 6 },
  link: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: SPACING.sm, alignSelf: 'flex-start' },
});
