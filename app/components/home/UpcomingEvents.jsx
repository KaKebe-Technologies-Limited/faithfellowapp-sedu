// "Upcoming events": a swipeable row of tall event cards: the picture (or a drawn cover), a date tile, the title, when and
// where, and the church that is hosting. Tap a card to see the event and book a place.
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { dateTile, dayAndTime } from '../../lib/format';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';

const W = 232;

function Card({ e }) {
  const tile = e.starts_at ? dateTile(e.starts_at) : null;

  return (
    <Pressable onPress={() => router.push({ pathname: '/event/[id]', params: { id: e.id } })} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Event: ${e.title}`}>
      <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
      {e.cover_url ? <Image source={{ uri: mediaUrl(e.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
      <LinearGradient colors={['rgba(20,6,12,0.0)', 'rgba(20,6,12,0.45)', 'rgba(20,6,12,0.88)']} locations={[0.1, 0.55, 1]} style={StyleSheet.absoluteFill} />
      <View style={styles.top}>
        {tile ? (
          <View style={styles.tile}>
            <AppText style={styles.tileMonth}>{tile.month}</AppText>
            <AppText style={styles.tileDay}>{tile.day}</AppText>
          </View>
        ) : null}
        <View style={styles.tag}>
          <AppText style={styles.tagText}>{e.is_paid ? 'Tickets' : 'Free'}</AppText>
        </View>
      </View>
      <View style={styles.bottom}>
        <AppText style={styles.title} numberOfLines={2}>
          {e.title}
        </AppText>
        <View style={styles.line}>
          <Ionicons name="time-outline" size={13} color="rgba(255,255,255,0.85)" />
          <AppText style={styles.meta} numberOfLines={1}>
            {dayAndTime(e.starts_at)}
          </AppText>
        </View>
        {e.location?.name ? (
          <View style={styles.line}>
            <Ionicons name={e.is_online ? 'videocam-outline' : 'location-outline'} size={13} color="rgba(255,255,255,0.85)" />
            <AppText style={styles.meta} numberOfLines={1}>
              {e.location.name}
            </AppText>
          </View>
        ) : null}
        <AppText style={styles.church} numberOfLines={1}>
          {e.church?.name}
        </AppText>
      </View>
    </Pressable>
  );
}

export default function UpcomingEvents({ events }) {
  if (!events?.length) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="Upcoming events" actionLabel="All events" onAction={() => router.push('/events')} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} snapToInterval={W + 12} decelerationRate="fast" contentContainerStyle={styles.rail}>
        {events.slice(0, 5).map((e) => (
          <Card key={e.id} e={e} />
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  rail: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 10 },
  card: { width: W, height: 270, borderRadius: 24, overflow: 'hidden', padding: 14, backgroundColor: COLORS.primaryDark, shadowColor: '#2A0A14', shadowOpacity: 0.2, shadowRadius: 12, shadowOffset: { width: 0, height: 6 }, elevation: 5 },
  top: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  tile: { backgroundColor: '#fff', borderRadius: 14, width: 54, paddingVertical: 6, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 25, color: COLORS.black },
  tag: { backgroundColor: 'rgba(0,0,0,0.45)', borderRadius: 10, paddingHorizontal: 10, paddingVertical: 4 },
  tagText: { fontFamily: FONTS.semibold, fontSize: 12, color: '#fff' },
  bottom: { position: 'absolute', left: 14, right: 14, bottom: 14 },
  title: { fontFamily: FONTS.bold, fontSize: 18, lineHeight: 23, color: '#fff', marginBottom: 6 },
  line: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 2 },
  meta: { flex: 1, fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.9)' },
  church: { fontFamily: FONTS.regular, fontSize: 11.5, color: 'rgba(255,255,255,0.65)', marginTop: 6 },
});
