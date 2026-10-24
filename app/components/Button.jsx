// Brand button: filled (primary), outlined or text-only, with a built-in loading spinner.
import { ActivityIndicator, Pressable, StyleSheet } from 'react-native';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';

export default function Button({ title, onPress, variant = 'primary', loading = false, disabled = false, small = false, style }) {
  const filled = variant === 'primary';
  // 'light' = white button for use on a magenta background.
  const textColor = filled ? COLORS.onColor : COLORS.primary;
  const inactive = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [
        styles.base,
        small && styles.small,
        filled && styles.filled,
        variant === 'light' && styles.light,
        variant === 'outline' && styles.outline,
        inactive && styles.inactive,
        pressed && { opacity: 0.85 },
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={textColor} />
      ) : (
        <AppText style={{ fontFamily: FONTS.semibold, fontSize: small ? 13 : 15, color: textColor }}>{title}</AppText>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: { minHeight: 50, paddingHorizontal: SPACING.lg, borderRadius: RADIUS.full, alignItems: 'center', justifyContent: 'center' },
  small: { minHeight: 36, paddingHorizontal: SPACING.md },
  filled: { backgroundColor: COLORS.primary },
  outline: { borderWidth: 1.5, borderColor: COLORS.primary },
  light: { backgroundColor: COLORS.white },
  inactive: { opacity: 0.55 },
});
