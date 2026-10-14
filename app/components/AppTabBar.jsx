// The bottom tab bar, with the radio / podcast bar sitting on top of it while something plays, so the player stays in view on
// every main screen. It draws the same Home / Bible / Community / Give / Me tabs the navigator describes.
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS } from '../constants/theme';
import AppText from './AppText';
import NowPlayingBar from './NowPlayingBar';

export default function AppTabBar({ state, descriptors, navigation }) {
  const insets = useSafeAreaInsets();

  return (
    <View>
      <NowPlayingBar />
      <View style={[styles.bar, { height: 72 + insets.bottom, paddingBottom: 10 + insets.bottom }]}>
        {state.routes.map((route, index) => {
          const { options } = descriptors[route.key];
          const focused = state.index === index;
          const color = focused ? COLORS.primary : COLORS.gray500;
          const label = options.title ?? route.name;

          const onPress = () => {
            const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
            if (!focused && !event.defaultPrevented) navigation.navigate(route.name, route.params);
          };

          return (
            <Pressable key={route.key} onPress={onPress} onLongPress={() => navigation.emit({ type: 'tabLongPress', target: route.key })} accessibilityRole="button" accessibilityState={{ selected: focused }} accessibilityLabel={label} style={styles.tab}>
              <View style={[styles.pill, focused && styles.pillOn]}>{options.tabBarIcon?.({ focused, color, size: 23 })}</View>
              <AppText style={[styles.label, { color }]}>{label}</AppText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingTop: 8, shadowColor: '#A67C8E', shadowOpacity: 0.25, shadowRadius: 16, shadowOffset: { width: 0, height: -6 }, elevation: 14 },
  tab: { flex: 1, alignItems: 'center', justifyContent: 'flex-start', gap: 2 },
  pill: { width: 52, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  pillOn: { backgroundColor: COLORS.primaryMist },
  label: { fontFamily: FONTS.medium, fontSize: 11, lineHeight: 14 },
});
