// Round church logo, falling back to the church's initials on a brand-coloured circle.
import { Image, StyleSheet, View } from 'react-native';
import { COLORS, FONTS } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { initials } from '../lib/format';
import AppText from './AppText';

export default function ChurchAvatar({ church, size = 40 }) {
  const logo = mediaUrl(church?.logo_url);
  const box = { width: size, height: size, borderRadius: size * 0.28 };

  if (logo) {
    return <Image source={{ uri: logo }} style={[box, styles.image]} accessibilityLabel={church?.name} />;
  }

  return (
    <View style={[box, styles.fallback]}>
      <AppText style={{ fontFamily: FONTS.bold, fontSize: size * 0.36, color: COLORS.white }}>{initials(church?.name)}</AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  image: { backgroundColor: COLORS.gray100 },
  fallback: { backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
});
