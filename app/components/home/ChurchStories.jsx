// "Your churches" row under the banner: the gospel radio's play button first, then each followed church's logo in a
// rounded tile, with a red ring and a LIVE tag while it is streaming. Tap opens the church; the last tile finds more.
// The radio tile is just a play / stop button: tap it and the music starts, with the player bar above the tabs.
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import AppText from '../AppText';
import { useRadio, useStationInfo } from '../../lib/radio';
import ChurchAvatar from '../ChurchAvatar';
import RadioButton from '../radio/RadioButton';

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

/** The gospel radio as a story-sized tile: only a play / stop button and a name under it. */
function RadioTile() {
  const { phase } = useRadio();
  const on = phase === 'playing' || phase === 'loading';

  return (
    <View style={styles.item}>
      <View style={styles.radio}>
        <RadioButton size={62} />
      </View>
      <AppText style={[styles.name, on && { color: COLORS.primary, fontFamily: FONTS.bold }]} numberOfLines={1}>
        {on ? 'On air' : 'Radio'}
      </AppText>
    </View>
  );
}

export default function ChurchStories({ churches }) {
  useStationInfo();
  const { station } = useRadio();
  const radio = !!station?.enabled && station.songs > 0;
  if (!churches?.length && !radio) return null;

  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row} style={styles.scroll}>
      {radio ? <RadioTile /> : null}
      {(churches ?? []).map((church) => (
        <Tile key={church.id} church={church} />
      ))}
      {churches?.length ? (
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
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { marginBottom: SPACING.md },
  row: { paddingHorizontal: SPACING.md, gap: 14 },
  item: { width: 70, alignItems: 'center' },
  radio: { width: 68, height: 68, alignItems: 'center', justifyContent: 'center' },
  ring: { width: 68, height: 68, borderRadius: 22, padding: 2.5, alignItems: 'center', justifyContent: 'center' },
  quietRing: { backgroundColor: COLORS.white, borderWidth: 1.5, borderColor: COLORS.gray100 },
  addRing: { backgroundColor: COLORS.primaryMist, borderWidth: 1.5, borderColor: COLORS.primaryPale, borderStyle: 'dashed' },
  inner: { width: '100%', height: '100%', borderRadius: 19, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  add: { backgroundColor: 'transparent' },
  live: { position: 'absolute', top: 54, backgroundColor: COLORS.danger, borderRadius: 7, paddingHorizontal: 6, paddingVertical: 1, borderWidth: 1.5, borderColor: COLORS.white },
  liveText: { fontFamily: FONTS.bold, fontSize: 9, letterSpacing: 0.6, color: COLORS.white },
  name: { fontFamily: FONTS.medium, fontSize: 11.5, color: COLORS.gray900, marginTop: 7, textAlign: 'center' },
});
