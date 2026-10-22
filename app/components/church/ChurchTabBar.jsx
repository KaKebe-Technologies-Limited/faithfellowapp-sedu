// The tabs of a church's page: Home, Sermons, Listen, Events, Updates, Give and About. They swap what is shown right on the page (nothing opens another screen) and
// the bar stays at the top while you scroll. Only the tabs the church has something in are shown.
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import AppText from '../AppText';

export default function ChurchTabBar({ tabs, active, onChange }) {
  return (
    <View style={styles.bar}>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
        {tabs.map((t) => {
          const on = t.key === active;

          return (
            <Pressable key={t.key} onPress={() => onChange(t.key)} accessibilityRole="tab" accessibilityState={{ selected: on }} accessibilityLabel={t.label} style={({ pressed }) => [styles.tab, on && styles.tabOn, pressed && { opacity: 0.85 }]}>
              <Ionicons name={on ? t.icon : `${t.icon}-outline`} size={16} color={on ? '#fff' : COLORS.primary} />
              <AppText style={[styles.label, on && { color: '#fff' }]}>{t.label}</AppText>
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { backgroundColor: SOFT.surface, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: 'rgba(120,80,100,0.12)' },
  row: { paddingHorizontal: SPACING.md, gap: 8 },
  tab: { flexDirection: 'row', alignItems: 'center', gap: 6, height: 38, paddingHorizontal: 15, borderRadius: 999, backgroundColor: '#fff', borderWidth: 1.5, borderColor: COLORS.primaryMist },
  tabOn: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
