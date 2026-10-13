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
              {options.tabBarIcon?.({ focused, color, size: 24 })}
              <AppText style={[styles.label, { color }]}>{label}</AppText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', backgroundColor: COLORS.white, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.gray100, paddingTop: 8 },
  tab: { flex: 1, alignItems: 'center', justifyContent: 'flex-start', gap: 3 },
  label: { fontFamily: FONTS.medium, fontSize: 11, lineHeight: 14 },
});
