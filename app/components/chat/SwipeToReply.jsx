// Wraps a message so that dragging it to the right (the way chat apps do) asks to reply to it: the message follows your finger a little, a reply arrow
// appears, and letting go past the mark starts the reply while the message springs back. Only a clearly sideways drag is taken, so scrolling the
// conversation up and down is never in its way.
import { useRef } from 'react';
import { Animated, PanResponder, StyleSheet, Vibration, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS } from '../../constants/theme';

const TRIGGER = 56; // how far to drag to reply
const MAX = 84; // how far the message can be pulled

export default function SwipeToReply({ onReply, enabled = true, children }) {
  const x = useRef(new Animated.Value(0)).current;
  const reply = useRef(onReply);
  const on = useRef(enabled);
  const buzzed = useRef(false);
  reply.current = onReply;
  on.current = enabled;

  const back = () => Animated.spring(x, { toValue: 0, useNativeDriver: true, bounciness: 7, speed: 16 }).start();

  const pan = useRef(
    PanResponder.create({
      onMoveShouldSetPanResponder: (_, g) => on.current && g.dx > 12 && Math.abs(g.dx) > Math.abs(g.dy) * 1.6,
      onPanResponderGrant: () => {
        buzzed.current = false;
      },
      onPanResponderMove: (_, g) => {
        x.setValue(Math.max(0, Math.min(MAX, g.dx)));
        if (g.dx >= TRIGGER && !buzzed.current) {
          buzzed.current = true;
          try {
            Vibration.vibrate(12);
          } catch {
            // No vibration here; the arrow is the sign.
          }
        }
      },
      onPanResponderRelease: (_, g) => {
        if (g.dx >= TRIGGER) reply.current?.();
        back();
      },
      onPanResponderTerminate: back,
      onPanResponderTerminationRequest: () => false,
    }),
  ).current;

  return (
    <View>
      <Animated.View style={[styles.arrow, { opacity: x.interpolate({ inputRange: [8, TRIGGER], outputRange: [0, 1], extrapolate: 'clamp' }), transform: [{ scale: x.interpolate({ inputRange: [0, TRIGGER], outputRange: [0.5, 1], extrapolate: 'clamp' }) }] }]} pointerEvents="none">
        <Ionicons name="arrow-undo" size={18} color={COLORS.primary} />
      </Animated.View>
      <Animated.View style={{ transform: [{ translateX: x }] }} {...pan.panHandlers}>
        {children}
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  arrow: { position: 'absolute', left: 6, top: 0, bottom: 0, width: 32, alignItems: 'center', justifyContent: 'center' },
});
