// All upcoming events at the churches you follow (GET /api/v1/events). Tapping a card opens the event in the app.
import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../components/AppText';
import { EventListCard } from '../components/ListCards';
import PagedScreen from '../components/PagedScreen';
import { COLORS, FONTS, RADIUS } from '../constants/theme';

export default function EventsScreen() {
  return (
    <PagedScreen
      title="Events"
      subtitle="At the churches you follow"
      path="/events"
      renderItem={(event) => <EventListCard event={event} />}
      emptyIcon="calendar-outline"
      emptyTitle="No upcoming events"
      emptyMessage="When your churches post an event, it will show up here."
      right={
        <Pressable onPress={() => router.push('/tickets')} style={styles.tickets} accessibilityRole="button" accessibilityLabel="My tickets">
          <Ionicons name="ticket-outline" size={18} color={COLORS.primary} />
          <AppText style={styles.ticketsText}>My tickets</AppText>
        </Pressable>
      }
    />
  );
}

const styles = StyleSheet.create({
  tickets: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 8 },
  ticketsText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary },
});
