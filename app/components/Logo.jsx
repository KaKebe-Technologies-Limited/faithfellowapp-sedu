// The authentic FaithFellows logo (the mark, from assets/logo-mark.png), never redrawn or recoloured. On dark or coloured
// backgrounds it sits on a white rounded tile, so it keeps its own colour. `plain` draws just the mark, for light backgrounds.
import { StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';

export default function Logo({ size = 72, plain = false }) {
  const mark = <Image source={require('../assets/logo-mark.png')} style={plain ? { width: size, height: size } : { width: size * 0.7, height: size * 0.7 }} contentFit="contain" accessibilityLabel="FaithFellows" />;

  if (plain) return mark;

  return <View style={[styles.tile, { width: size, height: size, borderRadius: size * 0.28 }]}>{mark}</View>;
}

const styles = StyleSheet.create({
  tile: { backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center' },
});
