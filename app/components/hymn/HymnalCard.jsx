// A hymnal on the hymn book's first page: a rich cover with the hymnal's name, its language and how many hymns it holds,
// and a badge when it is saved on the phone for offline use. Each hymnal gets its own colours so they are easy to tell apart.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { FONTS, SHADOW } from '../../constants/theme';
import AppText from '../AppText';

const COVERS = [
  ['#7B1E3A', '#BC2649'],
  ['#1F3A5F', '#3A6EA5'],
  ['#2F5D3A', '#5C9E6B'],
  ['#5B3A1F', '#B8762E'],
  ['#4A2C6B', '#8A5CC4'],
  ['#1F5D5D', '#2FA39A'],
];

export default function HymnalCard({ hymnal, saved = false }) {
  const colors = COVERS[hymnal.id % COVERS.length];

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/hymnal/[id]', params: { id: hymnal.id } })}
      style={({ pressed }) => [styles.wrap, pressed && { opacity: 0.93 }]}
      accessibilityRole="button"
      accessibilityLabel={`${hymnal.name}, ${hymnal.language_label}, ${hymnal.hymns_count} hymns`}
    >
      <LinearGradient colors={colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={styles.circleA} />
        <View style={styles.circleB} />
        <Ionicons name="musical-notes" size={30} color="rgba(255,255,255,0.9)" />
        <View style={{ flex: 1 }} />
        <AppText style={styles.name} numberOfLines={2}>
          {hymnal.name}
        </AppText>
        <AppText style={styles.meta} numberOfLines={1}>
          {[hymnal.language_label, `${hymnal.hymns_count} hymns`, hymnal.denomination].filter(Boolean).join(' · ')}
        </AppText>
        {saved ? (
          <View style={styles.saved}>
            <Ionicons name="checkmark-circle" size={13} color="#fff" />
            <AppText style={styles.savedText}>Offline</AppText>
          </View>
        ) : null}
      </LinearGradient>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: { width: 190, marginRight: 14, ...SHADOW },
  card: { height: 150, borderRadius: 22, padding: 16, overflow: 'hidden' },
  circleA: { position: 'absolute', right: -30, top: -30, width: 110, height: 110, borderRadius: 55, backgroundColor: 'rgba(255,255,255,0.12)' },
  circleB: { position: 'absolute', right: 20, bottom: -40, width: 90, height: 90, borderRadius: 45, backgroundColor: 'rgba(255,255,255,0.08)' },
  name: { fontFamily: FONTS.bold, fontSize: 17, lineHeight: 22, color: '#fff' },
  meta: { fontFamily: FONTS.medium, fontSize: 12, color: 'rgba(255,255,255,0.8)', marginTop: 2 },
  saved: { position: 'absolute', top: 14, right: 14, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: 'rgba(0,0,0,0.25)', borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3 },
  savedText: { fontFamily: FONTS.semibold, fontSize: 11, color: '#fff' },
});
