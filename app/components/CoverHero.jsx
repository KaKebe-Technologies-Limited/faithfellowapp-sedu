// Big picture at the top of a detail screen (event, campaign, sermon, devotional): the cover with a dark fade,
// round back / share buttons floating on it, and whatever you pass as children (pill, title, meta) laid over the
// bottom. With no picture it falls back to the brand gradient so the screen never has a hole at the top.
import { StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import BrandPlaceholder from './BrandPlaceholder';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { BackButton } from './ScreenHeader';

export default function CoverHero({ uri, height = 280, right, children, colors = [COLORS.primaryDark, COLORS.primary] }) {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.hero, { height: height + insets.top }]}>
      <LinearGradient colors={colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      {uri ? <Image source={{ uri: mediaUrl(uri) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} /> : <BrandPlaceholder />}
      <LinearGradient colors={['rgba(20,6,12,0.35)', 'rgba(20,6,12,0.05)', 'rgba(20,6,12,0.85)']} locations={[0, 0.4, 1]} style={StyleSheet.absoluteFill} />
      <View style={[styles.top, { top: insets.top + 8 }]}>
        <BackButton onDark />
        {right}
      </View>
      <View style={styles.bottom}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  hero: { backgroundColor: COLORS.primaryDark, justifyContent: 'flex-end' },
  top: { position: 'absolute', left: SPACING.md, right: SPACING.md, flexDirection: 'row', justifyContent: 'space-between' },
  bottom: { padding: SPACING.md, paddingBottom: SPACING.md + 4, gap: 6 },
});
