// Soft pulsing placeholder block shown while a section loads, so the screen keeps its shape.
import { useEffect, useRef } from 'react';
import { Animated } from 'react-native';
import { COLORS, RADIUS } from '../constants/theme';

export default function Skeleton({ width = '100%', height = 16, radius = RADIUS.md, style }) {
  const pulse = useRef(new Animated.Value(0.55)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 1, duration: 800, useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 0.55, duration: 800, useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [pulse]);

  return <Animated.View style={[{ width, height, borderRadius: radius, backgroundColor: '#E9E4E6', opacity: pulse }, style]} />;
}
