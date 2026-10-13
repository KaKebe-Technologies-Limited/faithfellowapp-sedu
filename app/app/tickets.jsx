// "My tickets": the events you are going to (and seats held until you pay). Each opens the event, where the
// QR ticket is one tap away.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import AppText from '../components/AppText';
import Pill from '../components/Pill';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { FlatList } from 'react-native';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { dateTile, dayAndTime, formatMoney } from '../lib/format';
import { useFetch } from '../lib/useFetch';

export default function TicketsScreen() {
  const { data, loading, error, retry } = useFetch('/tickets');
  const tickets = data?.data ?? [];

  return (
    <View style={styles.screen}>
      <ScreenHeader title="My tickets" subtitle="Events you are going to" />
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !tickets.length} emptyIcon="ticket-outline" emptyTitle="No tickets yet" emptyMessage="Reserve a seat at an event and your ticket will be kept here." emptyAction={{ title: 'Browse events', onPress: () => router.replace('/events') }}>
        <FlatList
          data={tickets}
          keyExtractor={(t) => String(t.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 12 }}
          renderItem={({ item: t }) => {
            const tile = dateTile(t.event.starts_at);
            const going = t.status === 'going';
            return (
              <Pressable onPress={() => router.push({ pathname: '/event/[id]', params: { id: t.event.id } })} style={styles.card} accessibilityRole="button">
                <View style={styles.tile}>
                  <AppText style={styles.tileMonth}>{tile.month}</AppText>
                  <AppText style={styles.tileDay}>{tile.day}</AppText>
                </View>
                <View style={{ flex: 1, gap: 3 }}>
                  <AppText style={styles.title} numberOfLines={2}>
                    {t.event.title}
                  </AppText>
                  <AppText variant="caption" numberOfLines={1}>
                    {[dayAndTime(t.event.starts_at), t.event.location?.name].filter(Boolean).join(' · ')}
                  </AppText>
                  <View style={{ flexDirection: 'row', gap: 6, marginTop: 4 }}>
                    <Pill label={going ? 'Going' : `Pay ${formatMoney(t.amount)}`} icon={going ? 'checkmark-circle-outline' : 'time-outline'} tone={going ? 'soft' : 'urgent'} />
                    <Pill label={`${t.quantity} ${t.quantity === 1 ? 'guest' : 'guests'}`} />
                  </View>
                </View>
                <Ionicons name={going ? 'qr-code-outline' : 'chevron-forward'} size={going ? 26 : 18} color={going ? COLORS.primary : COLORS.gray500} />
              </Pressable>
            );
          }}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  card: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, ...SHADOW },
  tile: { width: 56, paddingVertical: 8, borderRadius: 15, backgroundColor: COLORS.primaryMist, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 23, lineHeight: 26, color: COLORS.black },
  title: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 21, color: COLORS.black },
});
