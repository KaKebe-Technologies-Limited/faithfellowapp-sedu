// "Last played": the songs the radio played just before the one that is on, as small cover cards in a sideways row. A tap opens the full
// radio player, where the whole list is. Shows nothing until the radio has something behind it.
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import { mediaUrl } from '../../lib/api';
import { useRadio } from '../../lib/radio';
import AppText from '../AppText';
import SectionHeader from '../SectionHeader';

const SIZE = 118;

export default function LastPlayed() {
  const { recent, station } = useRadio();
  if (!recent?.length) return null;
  const open = () => router.push(station?.kind === 'talk' ? { pathname: '/radio', params: { kind: 'talk' } } : '/radio');

  return (
    <View style={styles.section}>
      <SectionHeader title="Last played" actionLabel="Open radio" onAction={open} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
        {recent.map((t, i) => (
          <Pressable key={`${t.id}-${i}`} onPress={open} style={({ pressed }) => [{ width: SIZE }, pressed && { opacity: 0.9 }]} accessibilityRole="button" accessibilityLabel={`${t.title}${t.artist ? ` by ${t.artist}` : ''}. Open the radio`}>
            <View style={styles.cover}>
              <LinearGradient colors={['#5B1431', COLORS.primary]} style={StyleSheet.absoluteFill} />
              {t.cover_url ? <Image source={{ uri: mediaUrl(t.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
            </View>
            <AppText style={styles.title} numberOfLines={1}>
              {t.title}
            </AppText>
            <AppText style={styles.artist} numberOfLines={1}>
              {t.artist || ' '}
            </AppText>
          </Pressable>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  rail: { paddingHorizontal: SPACING.md, gap: 12 },
  cover: { width: SIZE, height: SIZE, borderRadius: 20, overflow: 'hidden', backgroundColor: COLORS.primaryMist },
  title: { fontFamily: FONTS.bold, fontSize: 14, color: SOFT.ink, marginTop: 8 },
  artist: { fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted, marginTop: 1 },
});
