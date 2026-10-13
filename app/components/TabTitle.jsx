// Big page title at the top of a tab (Bible, Community, Give, Me), below the status bar, with an optional
// action on the right. Tabs draw their own title instead of the stock navigation header.
import { StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, SPACING } from '../constants/theme';
import AppText from './AppText';

export default function TabTitle({ title, subtitle, right }) {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.row, { paddingTop: insets.top + 10 }]}>
      <View style={{ flex: 1 }}>
        <AppText style={styles.title}>{title}</AppText>
        {subtitle ? <AppText variant="caption">{subtitle}</AppText> : null}
      </View>
      {right}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: SPACING.md, paddingBottom: 8, backgroundColor: COLORS.canvas },
  title: { fontFamily: FONTS.bold, fontSize: 30, lineHeight: 36, color: COLORS.black },
});
