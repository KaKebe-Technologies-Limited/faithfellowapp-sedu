// Little sound bars that dance while the radio plays and rest while it does not: the sign that you are listening live.
import { useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, View } from 'react-native';

const BARS = [0.55, 0.9, 0.7, 1, 0.6, 0.85, 0.5]; // each bar's own tallest point, so they never move in step
const REST = 0.22;

export default function Equalizer({ playing, height = 30, color = '#fff', style }) {
  const bars = useRef(BARS.map(() => new Animated.Value(REST))).current;

  useEffect(() => {
    if (!playing) {
      bars.forEach((v) => Animated.timing(v, { toValue: REST, duration: 250, useNativeDriver: true }).start());
      return undefined;
    }
    const loops = bars.map((v, i) => {
      const top = BARS[i];
      const loop = Animated.loop(
        Animated.sequence([
          Animated.timing(v, { toValue: top, duration: 380 + i * 70, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
          Animated.timing(v, { toValue: REST + 0.08, duration: 430 + i * 55, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
        ]),
      );
      loop.start();

      return loop;
    });

    return () => loops.forEach((l) => l.stop());
  }, [playing, bars]);

  return (
    <View style={[styles.row, { height }, style]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
      {bars.map((v, i) => (
        <Animated.View key={i} style={[styles.bar, { height, backgroundColor: color, transform: [{ scaleY: v }] }]} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  bar: { width: 4, borderRadius: 2 },
});
