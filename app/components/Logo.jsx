// FaithFellows emblem (ring + cross) drawn with plain Views, so it scales and needs no image file.
import { StyleSheet, View } from 'react-native';
import { COLORS } from '../constants/theme';

export default function Logo({ size = 72, color = COLORS.white, background = 'transparent' }) {
  const ring = size * 0.04;
  const bar = size * 0.1;

  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: background, alignItems: 'center', justifyContent: 'center' }}>
      <View style={[styles.ring, { width: size * 0.86, height: size * 0.86, borderRadius: size, borderWidth: ring, borderColor: color }]} />
      <View style={{ position: 'absolute', width: bar, height: size * 0.5, borderRadius: bar / 3, backgroundColor: color, top: size * 0.26 }} />
      <View style={{ position: 'absolute', width: size * 0.34, height: bar, borderRadius: bar / 3, backgroundColor: color, top: size * 0.4 }} />
    </View>
  );
}

const styles = StyleSheet.create({
  ring: { position: 'absolute' },
});
