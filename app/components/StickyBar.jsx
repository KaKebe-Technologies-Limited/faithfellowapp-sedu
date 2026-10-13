// Bar pinned to the bottom of a detail screen for its main action ("RSVP", "Give", "Pay now"), with a short
// line of context on the left (price, seats left, progress). Respects the phone's home-indicator area.
import { StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SPACING } from '../constants/theme';
import AppText from './AppText';
import Button from './Button';

export default function StickyBar({ title, subtitle, action, onPress, loading, disabled, children }) {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 10) + 4 }]}>
      <View style={{ flex: 1 }}>
        {title ? (
          <AppText style={styles.title} numberOfLines={1}>
            {title}
          </AppText>
        ) : null}
        {subtitle ? (
          <AppText variant="caption" numberOfLines={1}>
            {subtitle}
          </AppText>
        ) : null}
      </View>
      {children}
      {action ? <Button title={action} onPress={onPress} loading={loading} disabled={disabled} style={styles.button} /> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: SPACING.md,
    paddingTop: 12,
    backgroundColor: COLORS.white,
    borderTopWidth: 1,
    borderTopColor: COLORS.gray100,
  },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  button: { minWidth: 140 },
});
