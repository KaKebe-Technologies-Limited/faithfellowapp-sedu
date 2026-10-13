// The big round play / stop button of the gospel radio: a white button inside a glowing orange-to-pink ring, with a
// black stop square while the music plays and a play triangle when it does not. Tap to join the live radio or stop it.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { COLORS } from '../../constants/theme';
import { toggleRadio, useRadio } from '../../lib/radio';

/** A small dark round button with an icon, for the full player. */
export function RoundIconButton({ icon, label, onPress }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} style={styles.round}>
      <Ionicons name={icon} size={22} color="#fff" />
    </Pressable>
  );
}

export default function RadioButton({ size = 76, style }) {
  const { phase } = useRadio();
  const ring = Math.max(3, Math.round(size * 0.055));
  const inner = size - ring * 2;
  const playing = phase === 'playing';

  return (
    <Pressable
      onPress={toggleRadio}
      accessibilityRole="button"
      accessibilityLabel={playing ? 'Stop the radio' : phase === 'loading' ? 'Starting the radio' : 'Play the radio'}
      style={({ pressed }) => [{ width: size, height: size, borderRadius: size / 2 }, styles.glow, pressed && { transform: [{ scale: 0.96 }] }, style]}
    >
      <LinearGradient colors={['#FFB347', '#FF5E7E', '#C13584']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ width: size, height: size, borderRadius: size / 2, padding: ring }}>
        <View style={[styles.face, { width: inner, height: inner, borderRadius: inner / 2 }]}>
          {phase === 'loading' ? (
            <ActivityIndicator color={COLORS.primary} />
          ) : playing ? (
            <View style={{ width: inner * 0.3, height: inner * 0.3, borderRadius: 4, backgroundColor: '#111' }} />
          ) : (
            <Ionicons name="play" size={inner * 0.42} color={COLORS.primary} style={{ marginLeft: inner * 0.05 }} />
          )}
        </View>
      </LinearGradient>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  glow: { shadowColor: '#FF7A59', shadowOpacity: 0.65, shadowRadius: 14, shadowOffset: { width: 0, height: 0 }, elevation: 10 },
  round: { width: 48, height: 48, borderRadius: 24, backgroundColor: '#2E2E36', alignItems: 'center', justifyContent: 'center' },
  face: { backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
});
