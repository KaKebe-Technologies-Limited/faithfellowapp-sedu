// Four small shortcuts under the quiz card, so Home stays calm: the prayer wall, sermons, events and giving. Each tile shows
// one line of what is new (how many prayer requests are waiting, the most listened sermon, the next event) and opens its own
// screen, where everything is shown in full.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { COLORS, FONTS, SHADOW, SPACING } from '../../constants/theme';
import { dateTile } from '../../lib/format';
import AppText from '../AppText';

function Tile({ icon, tint, bg, title, detail, onPress, label }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.tile, pressed && { opacity: 0.93 }]} accessibilityRole="button" accessibilityLabel={label ?? `${title}. ${detail}`}>
      <View style={[styles.icon, { backgroundColor: bg }]}>
        <Ionicons name={icon} size={22} color={tint} />
      </View>
      <AppText style={styles.title} numberOfLines={1}>
        {title}
      </AppText>
      <AppText style={styles.detail} numberOfLines={1}>
        {detail}
      </AppText>
    </Pressable>
  );
}

export default function QuickAccess({ home }) {
  const open = home.prayers?.open_count ?? 0;
  const topSermon = home.top_sermons?.[0];
  const event = home.events?.[0];
  const when = event?.starts_at ? dateTile(event.starts_at) : null;
  const church = home.churches?.[0];
  const several = (home.churches?.length ?? 0) > 1;

  return (
    <View style={styles.wrap}>
      <View style={styles.row}>
        <Tile icon="hand-left" tint="#B45309" bg="#FFEBC9" title="Prayer wall" detail={open ? `${open} to pray for` : 'Pray together'} onPress={() => router.push('/prayer')} />
        <Tile icon="mic" tint="#1D4ED8" bg="#DCE8FF" title="Sermons" detail={topSermon?.listens ? 'Most listened first' : 'Watch and listen'} onPress={() => router.push('/sermons')} />
      </View>
      <View style={styles.row}>
        <Tile icon="calendar" tint="#BC2649" bg="#FFE1EA" title="Events" detail={event ? (when ? `Next: ${when.month} ${when.day}` : 'See what is on') : 'Nothing planned yet'} onPress={() => router.push('/events')} />
        <Tile
          icon="heart"
          tint="#12704F"
          bg="#D6F2E4"
          title="Give"
          detail="Tithe and offering"
          onPress={() => (church && !several ? router.push({ pathname: '/give/[slug]', params: { slug: church.slug } }) : router.push('/(tabs)/give'))}
          label="Give to your church with mobile money"
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, gap: 12, marginBottom: SPACING.lg },
  row: { flexDirection: 'row', gap: 12 },
  tile: { flex: 1, backgroundColor: COLORS.white, borderRadius: 20, padding: 14, ...SHADOW, shadowOpacity: 0.06 },
  icon: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', marginBottom: 10 },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  detail: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray700, marginTop: 2 },
});
