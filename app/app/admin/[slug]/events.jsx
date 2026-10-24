// Events: your church's events (past and future) and a form to create one (see components/EventForm). Publishing an event tells your followers. Tap an event for its guest list and door check-in.
// Data: /api/v1/admin/{church}/events.
import { useState } from 'react';
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback } from 'react';
import AppText from '../../../components/AppText';
import EventForm from '../../../components/EventForm';
import ScreenHeader from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { dateTile, dayAndTime } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

export default function AdminEvents() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const { data, loading, error, retry, reload } = useFetch(admin.path('/events'));
  const events = data?.data ?? [];
  const [open, setOpen] = useState(false);

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Events"
        subtitle="Create events and check people in"
        right={
          <Pressable onPress={() => setOpen(true)} style={styles.add} accessibilityRole="button" accessibilityLabel="New event">
            <Ionicons name="add" size={20} color={COLORS.onColor} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !events.length} emptyIcon="calendar-outline" emptyTitle="No events yet" emptyAction={{ title: 'Create an event', onPress: () => setOpen(true) }}>
        <FlatList
          data={events}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 10 }}
          renderItem={({ item: e }) => {
            const tile = dateTile(e.starts_at);
            return (
              <Pressable onPress={() => router.push({ pathname: '/admin/[slug]/event/[id]', params: { slug, id: String(e.id) } })} style={styles.card} accessibilityRole="button" accessibilityLabel={`${e.title}, ${e.going} going`}>
                <View style={styles.tile}>
                  <AppText style={styles.tileMonth}>{tile.month}</AppText>
                  <AppText style={styles.tileDay}>{tile.day}</AppText>
                </View>
                <View style={{ flex: 1, gap: 2 }}>
                  <AppText style={styles.title} numberOfLines={2}>{e.title}</AppText>
                  <AppText variant="caption" numberOfLines={1}>{[dayAndTime(e.starts_at), e.location].filter(Boolean).join(' · ')}</AppText>
                  <AppText variant="caption" style={{ color: e.status === 'published' ? COLORS.success : COLORS.warning, fontFamily: FONTS.semibold }}>{e.status} · {e.going} going{e.is_paid ? ' · ticketed' : ''}</AppText>
                </View>
                <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
              </Pressable>
            );
          }}
        />
      </StateView>

      <EventForm visible={open} onClose={() => setOpen(false)} slug={slug} onSaved={reload} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.onColor },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, ...SHADOW, shadowOpacity: 0.05 },
  tile: { width: 54, paddingVertical: 7, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 25, color: COLORS.black },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
});
