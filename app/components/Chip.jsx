// A small pill you tap to pick an option or filter (selected = filled in the brand colour).
import { Pressable, StyleSheet } from 'react-native';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import AppText from './AppText';

export default function Chip({ label, on = false, onPress, style }) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, on && styles.on, style]} accessibilityRole="button" accessibilityState={{ selected: on }}>
      <AppText style={[styles.text, on && { color: COLORS.white }]}>{label}</AppText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  on: { backgroundColor: COLORS.primary },
  text: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
