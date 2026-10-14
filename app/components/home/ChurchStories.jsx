// "Your churches": each followed church's logo in a soft frosted circle, with a red ring and a LIVE tag while it is streaming.
// Tap opens the church; the last circle finds more. (The radio, podcasts and hymns live in the Explore grid above.)
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';

function Tile({ church }) {
  const inner = (
    <View style={styles.inner}>
      <ChurchAvatar church={church} size={56} />
    </View>
  );

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: church.slug } })}
      style={styles.item}
      accessibilityRole="button"
      accessibilityLabel={church.is_live ? `${church.name}, live now` : church.name}
    >
      {church.is_live ? (
        <LinearGradient colors={['#FF5A6E', COLORS.primary]} style={styles.ring}>
          {inner}
        </LinearGradient>
      ) : (
        <View style={[styles.ring, styles.quietRing]}>{inner}</View>
      )}
      {church.is_live ? (
        <View style={styles.live}>
          <AppText style={styles.liveText}>LIVE</AppText>
        </View>
      ) : null}
      <AppText style={styles.name} numberOfLines={1}>
        {church.name}
      </AppText>
    </Pressable>
  );
}

export default function ChurchStories({ churches }) {
  if (!churches?.length) return null;

  return (
    <View style={styles.wrap}>
      <AppText style={styles.title}>Your churches</AppText>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
        {churches.map((church) => (
          <Tile key={church.id} church={church} />
        ))}
        <Pressable onPress={() => router.push('/churches')} style={styles.item} accessibilityRole="button" accessibilityLabel="Find more churches">
          <View style={[styles.ring, styles.quietRing]}>
            <View style={[styles.inner, styles.add]}>
              <Ionicons name="add" size={26} color={COLORS.primary} />
            </View>
          </View>
          <AppText style={styles.name} numberOfLines={1}>
            Discover
          </AppText>
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 6 },
  title: { fontFamily: FONTS.bold, fontSize: 18, color: SOFT.ink, paddingHorizontal: SPACING.md, marginBottom: 12 },
  row: { paddingHorizontal: SPACING.md, gap: 16, paddingBottom: 14 },
  item: { width: 74, alignItems: 'center' },
  ring: { width: 70, height: 70, borderRadius: 35, padding: 3, alignItems: 'center', justifyContent: 'center' },
  quietRing: { backgroundColor: SOFT.glass, borderWidth: 1.5, borderColor: SOFT.glassBorder, ...SOFT_SHADOW },
  inner: { width: 62, height: 62, borderRadius: 31, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  add: { backgroundColor: COLORS.primaryMist },
  live: { position: 'absolute', top: 50, backgroundColor: COLORS.primary, borderRadius: 8, paddingHorizontal: 6, paddingVertical: 1, borderWidth: 2, borderColor: SOFT.surface },
  liveText: { fontFamily: FONTS.bold, fontSize: 9, letterSpacing: 0.6, color: '#fff' },
  name: { fontFamily: FONTS.semibold, fontSize: 12, color: SOFT.ink, marginTop: 8, maxWidth: 74, textAlign: 'center' },
});
