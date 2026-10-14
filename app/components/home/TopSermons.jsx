// "Most listened sermons": the two sermons your churches' members played most, side by side as tall picture cards with
// the preacher and how many times each was played. Tap one to listen or watch.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { formatDuration } from '../../lib/format';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';

function Card({ s, rank }) {
  return (
    <Pressable onPress={() => router.push({ pathname: '/sermon/[id]', params: { id: s.id } })} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Sermon ${s.title}${s.listens ? `, played ${s.listens} times` : ''}`}>
      <LinearGradient colors={['#2A0A14', COLORS.primaryDark]} style={StyleSheet.absoluteFill} />
      {s.thumbnail_url ? <Image source={{ uri: mediaUrl(s.thumbnail_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
      <LinearGradient colors={['rgba(20,6,12,0.15)', 'rgba(20,6,12,0.55)', 'rgba(20,6,12,0.92)']} locations={[0, 0.5, 1]} style={StyleSheet.absoluteFill} />
      <View style={styles.top}>
        <View style={styles.rank}>
          <AppText style={styles.rankText}>#{rank}</AppText>
        </View>
        <View style={styles.play}>
          <Ionicons name="play" size={18} color={COLORS.primary} style={{ marginLeft: 2 }} />
        </View>
      </View>
      <View style={styles.bottom}>
        <AppText style={styles.title} numberOfLines={3}>
          {s.title}
        </AppText>
        <AppText style={styles.meta} numberOfLines={1}>
          {[s.preacher, s.duration_seconds ? formatDuration(s.duration_seconds) : null].filter(Boolean).join(' · ') || s.church?.name}
        </AppText>
        {s.listens ? (
          <View style={styles.listens}>
            <Ionicons name="headset" size={13} color="#FFD98A" />
            <AppText style={styles.listensText}>
              {s.listens.toLocaleString()} {s.listens === 1 ? 'listen' : 'listens'}
            </AppText>
          </View>
        ) : null}
      </View>
    </Pressable>
  );
}

export default function TopSermons({ sermons }) {
  if (!sermons?.length) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="Most listened sermons" actionLabel="All sermons" onAction={() => router.push('/sermons')} />
      <View style={styles.row}>
        {sermons.slice(0, 2).map((s, i) => (
          <Card key={s.id} s={s} rank={i + 1} />
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  row: { flexDirection: 'row', gap: 12, paddingHorizontal: SPACING.md },
  card: { flex: 1, height: 232, borderRadius: 22, overflow: 'hidden', padding: 12, backgroundColor: COLORS.primaryDark, shadowColor: '#2A0A14', shadowOpacity: 0.2, shadowRadius: 12, shadowOffset: { width: 0, height: 6 }, elevation: 5 },
  top: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  rank: { backgroundColor: '#FFD98A', borderRadius: 10, paddingHorizontal: 9, paddingVertical: 3 },
  rankText: { fontFamily: FONTS.bold, fontSize: 12, color: '#5B3A00' },
  play: { width: 34, height: 34, borderRadius: 17, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  bottom: { position: 'absolute', left: 12, right: 12, bottom: 12 },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, lineHeight: 20, color: '#fff' },
  meta: { fontFamily: FONTS.medium, fontSize: 12, color: 'rgba(255,255,255,0.8)', marginTop: 3 },
  listens: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 8 },
  listensText: { fontFamily: FONTS.bold, fontSize: 12, color: '#FFD98A' },
});
