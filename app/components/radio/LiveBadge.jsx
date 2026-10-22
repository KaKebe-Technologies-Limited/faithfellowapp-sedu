// The "LIVE" mark of the radio: a pulsing dot and the word LIVE, because the radio is on round the clock and everyone hears the same thing at
// the same moment. `onAir` turns the dot green (you are listening). Used on the Home card, the full player and the bar above the tabs.
import { useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, View } from 'react-native';
import { FONTS } from '../../constants/theme';
import AppText from '../AppText';

export default function LiveBadge({ onAir = false, small = false, style }) {
  const pulse = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const loop = Animated.loop(Animated.timing(pulse, { toValue: 1, duration: 1500, easing: Easing.out(Easing.ease), useNativeDriver: true }));
    loop.start();

    return () => loop.stop();
  }, [pulse]);

  const size = small ? 7 : 9;
  const color = onAir ? '#5CFF9A' : '#FF5E7E';

  return (
    <View style={[styles.chip, small && styles.chipSmall, style]} accessible accessibilityLabel="Live">
      <View style={{ width: size, height: size }}>
        <Animated.View style={[styles.ring, { backgroundColor: color, borderRadius: size, opacity: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.55, 0] }), transform: [{ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 2.8] }) }] }]} />
        <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: color }} />
      </View>
      <AppText style={[styles.text, small && { fontSize: 10, letterSpacing: 1.2 }]}>LIVE</AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: { flexDirection: 'row', alignItems: 'center', gap: 8, alignSelf: 'flex-start', backgroundColor: 'rgba(20,6,12,0.5)', borderRadius: 999, paddingHorizontal: 12, paddingVertical: 6 },
  chipSmall: { gap: 6, paddingHorizontal: 8, paddingVertical: 3 },
  ring: { ...StyleSheet.absoluteFillObject },
  text: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.5, color: '#fff' },
});
