// Heading above a section of the home screen, with an optional "See all" link on the right.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, SPACING } from '../constants/theme';
import AppText from './AppText';

export default function SectionHeader({ title, actionLabel, onAction }) {
  return (
    <View style={styles.row}>
      <AppText variant="heading" style={styles.title}>
        {title}
      </AppText>
      {actionLabel ? (
        <Pressable onPress={onAction} hitSlop={8} accessibilityRole="button" style={styles.action}>
          <AppText variant="label" style={{ color: COLORS.primary }}>
            {actionLabel}
          </AppText>
          <Ionicons name="chevron-forward" size={14} color={COLORS.primary} />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: SPACING.md, marginBottom: SPACING.sm + 2 },
  title: { fontSize: 18 },
  action: { flexDirection: 'row', alignItems: 'center', gap: 2 },
});
