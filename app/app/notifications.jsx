// Notification centre: everything your churches and your connections told you, newest first. Tapping one opens the
// right screen inside the app and marks it read; "Mark all read" clears the badge. Data: GET /api/v1/notifications.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../components/AppText';
import PagedScreen from '../components/PagedScreen';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import api from '../lib/api';
import { openNotification, routeFor } from '../lib/deeplink';
import { timeAgo } from '../lib/format';

const ICONS = { connection_request: 'person-add-outline', connection_accepted: 'person-outline', live: 'radio-outline', sermon: 'mic-outline', event: 'calendar-outline', giving: 'heart-outline', prayer: 'hand-left-outline', announcement: 'megaphone-outline', devotional: 'sunny-outline', games: 'game-controller-outline', reading: 'book-outline' };

export default function NotificationsScreen() {
  async function readAll() {
    await api.post('/notifications/read').catch(() => {});
  }

  return (
    <PagedScreen
      title="Notifications"
      subtitle="What is new for you"
      path="/notifications"
      keyOf={(n) => String(n.id)}
      emptyIcon="notifications-outline"
      emptyTitle="You are all caught up"
      emptyMessage="News from your churches and requests from people will show up here."
      right={
        <Pressable onPress={readAll} style={styles.readAll} accessibilityRole="button">
          <AppText style={styles.readAllText}>Mark all read</AppText>
        </Pressable>
      }
      renderItem={(n) => (
        <Pressable
          onPress={() => {
            api.post('/notifications/read', { ids: [n.id] }).catch(() => {});
            openNotification(n);
          }}
          style={[styles.row, !n.read && styles.unread]}
          accessibilityRole={routeFor(n) ? 'button' : undefined}
        >
          <View style={styles.icon}>
            <Ionicons name={ICONS[n.kind ?? n.category] ?? 'notifications-outline'} size={20} color={COLORS.primary} />
          </View>
          <View style={{ flex: 1, gap: 2 }}>
            <AppText style={styles.title} numberOfLines={2}>
              {n.title}
            </AppText>
            {n.body ? (
              <AppText variant="caption" numberOfLines={2} style={{ fontSize: 13 }}>
                {n.body}
              </AppText>
            ) : null}
            <AppText variant="caption">{timeAgo(n.created_at)}</AppText>
          </View>
          {!n.read ? <View style={styles.dot} /> : null}
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  readAll: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 13, paddingVertical: 8 },
  readAllText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: SPACING.md, paddingVertical: 13, backgroundColor: COLORS.white, borderBottomWidth: 1, borderBottomColor: COLORS.gray100 },
  unread: { backgroundColor: COLORS.selected },
  icon: { width: 42, height: 42, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  title: { fontFamily: FONTS.semibold, fontSize: 15, lineHeight: 20, color: COLORS.black },
  dot: { width: 10, height: 10, borderRadius: 5, backgroundColor: COLORS.primary },
});
