// White rounded card with a soft shadow, used for feed items, lists and settings groups.
import { StyleSheet, View } from 'react-native';
import { COLORS, RADIUS, SHADOW, SPACING } from '../constants/theme';

export default function Card({ style, children, ...rest }) {
  return (
    <View style={[styles.card, style]} {...rest}>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, ...SHADOW },
});
