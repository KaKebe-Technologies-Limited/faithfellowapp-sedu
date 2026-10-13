// A person's round photo, or their initials on a colour picked from their name (so everyone looks different
// even without a photo). Works for the app's people, group members, chat partners and the signed-in member.
import { StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { COLORS, FONTS } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { initials } from '../lib/format';
import AppText from './AppText';

const PALETTE = ['#BC2649', '#7C4DFF', '#00A896', '#F57C00', '#1E88E5', '#AD1457', '#5E35B1', '#2E7D32', '#EF6C00', '#0277BD'];

export const colorFor = (name = '') => PALETTE[[...String(name)].reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % PALETTE.length];

export default function Avatar({ person, size = 44, ring = false }) {
  const uri = mediaUrl(person?.avatar_url);
  const box = { width: size, height: size, borderRadius: size / 2, backgroundColor: colorFor(person?.name) };

  return (
    <View style={[box, styles.center, ring && styles.ring]} accessibilityLabel={person?.name}>
      {uri ? <Image source={{ uri }} style={StyleSheet.absoluteFill} contentFit="cover" transition={200} /> : <AppText numberOfLines={1} allowFontScaling={false} style={{ fontFamily: FONTS.bold, fontSize: size * 0.38, color: COLORS.white, textAlign: 'center' }}>{initials(person?.name)}</AppText>}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  ring: { borderWidth: 3, borderColor: COLORS.white },
});
