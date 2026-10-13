// "Your churches" row under the banner: each followed church's logo in a rounded tile, with a red ring
// and a LIVE tag while it is streaming. Tap opens the church; the last tile finds more churches.
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';

function Tile({ church }) {
  const tile = (
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
          {tile}
        </LinearGradient>
      ) : (
        <View style={[styles.ring, styles.quietRing]}>{tile}</View>
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
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row} style={styles.scroll}>
      {churches.map((church) => (
        <Tile key={church.id} church={church} />
      ))}
      <Pressable onPress={() => router.push('/churches')} style={styles.item} accessibilityRole="button" accessibilityLabel="Find more churches">
        <View style={[styles.ring, styles.addRing]}>
          <View style={[styles.inner, styles.add]}>
            <Ionicons name="add" size={26} color={COLORS.primary} />
          </View>
        </View>
        <AppText style={styles.name} numberOfLines={1}>
          Discover
        </AppText>
      </Pressable>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { marginBottom: SPACING.md },
  row: { paddingHorizontal: SPACING.md, gap: 14 },
  item: { width: 70, alignItems: 'center' },
  ring: { width: 68, height: 68, borderRadius: 22, padding: 2.5, alignItems: 'center', justifyContent: 'center' },
  quietRing: { backgroundColor: COLORS.white, borderWidth: 1.5, borderColor: COLORS.gray100 },
  addRing: { backgroundColor: COLORS.primaryMist, borderWidth: 1.5, borderColor: COLORS.primaryPale, borderStyle: 'dashed' },
  inner: { width: '100%', height: '100%', borderRadius: 19, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  add: { backgroundColor: 'transparent' },
  live: { position: 'absolute', top: 54, backgroundColor: COLORS.danger, borderRadius: 7, paddingHorizontal: 6, paddingVertical: 1, borderWidth: 1.5, borderColor: COLORS.white },
  liveText: { fontFamily: FONTS.bold, fontSize: 9, letterSpacing: 0.6, color: COLORS.white },
  name: { fontFamily: FONTS.medium, fontSize: 11.5, color: COLORS.gray900, marginTop: 7, textAlign: 'center' },
});
