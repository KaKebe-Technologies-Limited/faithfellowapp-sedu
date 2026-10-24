// "Explore": the everyday places of the app as soft frosted tiles, four to a row, each opening its own screen (the radio is the card at
// the top of Home). Podcasts and the hymn book belong to the whole platform; the super admin can switch them off for a church
// (see lib/features.js), and a new sign-up with no church sees all of them.
// A little number on a tile says what is waiting (prayer requests to pray for).
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { useFeatures } from '../../lib/features';
import AppText from '../AppText';

const TILE = 66;

/** A frosted square tile with the icon on a coloured chip. */
function Frame({ children }) {
  return <View style={styles.frame}>{children}</View>;
}

function Chip({ icon, colors }) {
  return (
    <LinearGradient colors={colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.chip}>
      <Ionicons name={icon} size={23} color="#fff" />
    </LinearGradient>
  );
}

function Cell({ icon, colors, label, onPress, count, hint }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.cell, pressed && { opacity: 0.85, transform: [{ scale: 0.97 }] }]} accessibilityRole="button" accessibilityLabel={hint ?? label}>
      <Frame>
        <Chip icon={icon} colors={colors} />
        {count ? (
          <View style={styles.count}>
            <AppText style={styles.countText}>{count > 99 ? '99+' : count}</AppText>
          </View>
        ) : null}
      </Frame>
      <AppText style={styles.label} numberOfLines={1}>
        {label}
      </AppText>
    </Pressable>
  );
}

export default function ExploreGrid({ home }) {
  const features = useFeatures();
  const church = home?.churches?.[0];
  const several = (home?.churches?.length ?? 0) > 1;

  return (
    <View style={styles.wrap}>
      <AppText style={styles.title}>Explore</AppText>
      <View style={styles.grid}>
        {features.podcasts ? <Cell icon="headset" colors={['#8E6BD6', '#4527A0']} label="Podcasts" onPress={() => router.push('/podcasts')} /> : null}
        {features.hymns ? <Cell icon="musical-notes" colors={['#F6BC4A', '#C77700']} label="Hymns" onPress={() => router.push('/hymns')} /> : null}
        <Cell icon="mic" colors={['#5AA6F5', '#1D5FD1']} label="Sermons" onPress={() => router.push('/sermons')} />
        <Cell icon="calendar" colors={['#E4668A', COLORS.primary]} label="Events" onPress={() => router.push('/events')} />
        <Cell icon="hand-left" colors={['#FFAE55', '#E65100']} label="Prayer" count={home?.prayers?.open_count ?? 0} hint="Prayer wall" onPress={() => router.push('/prayer')} />
        <Cell
          icon="heart"
          colors={['#46C48B', '#12704F']}
          label="Give"
          hint="Give to your church with mobile money"
          onPress={() => (church && !several ? router.push({ pathname: '/give/[slug]', params: { slug: church.slug } }) : router.push('/(tabs)/give'))}
        />
        <Cell icon="game-controller" colors={['#A58AFF', '#5B3CC4']} label="Games" hint="Games" onPress={() => router.push('/games')} />
        <Cell icon="business" colors={['#3FC1C9', '#0E7C86']} label="Churches" hint="Find and follow churches" onPress={() => router.push('/churches')} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg },
  title: { fontFamily: FONTS.display, fontSize: 18, color: SOFT.ink, marginBottom: 12 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', rowGap: 16 },
  cell: { width: '25%', alignItems: 'center' },
  frame: { width: TILE, height: TILE, borderRadius: 24, backgroundColor: SOFT.glass, borderWidth: 1.5, borderColor: SOFT.glassBorder, alignItems: 'center', justifyContent: 'center', ...SOFT_SHADOW },
  chip: { width: 44, height: 44, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  count: { position: 'absolute', top: -5, right: -5, minWidth: 20, height: 20, borderRadius: 10, paddingHorizontal: 5, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: SOFT.surface },
  countText: { fontFamily: FONTS.bold, fontSize: 10.5, color: '#fff' },
  label: { fontFamily: FONTS.semibold, fontSize: 12.5, color: SOFT.ink, marginTop: 8 },
  on: { color: COLORS.primary, fontFamily: FONTS.bold },
});
