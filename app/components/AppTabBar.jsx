// The bottom bar: a floating dark wine pill with a round crimson button that rises out of a soft notch over the tab you are on, and
// glides to the next tab when you change. The radio / podcast bar sits on top of it while something plays, so the player stays in view
// on every main screen. It draws the same Home / Bible / Community / Give / Me tabs the navigator describes.
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Defs, LinearGradient as SvgGradient, Path, Stop } from 'react-native-svg';
import { COLORS } from '../constants/theme';
import { SOFT } from '../constants/soft';
import NowPlayingBar from './NowPlayingBar';

const HEIGHT = 66; // the pill
const RISE = 24; // how far the round button rises above the pill
const SIDE = 14; // space left and right of the pill

/** The pill's outline, with a smooth scoop cut out of its top edge around `cx`. */
function outline(width, cx) {
  const r = 30; // corner radius
  const depth = 21;
  const half = 52; // half the width of the scoop at the top edge
  const flat = 24; // half the flat bottom of the scoop
  const left = Math.max(r + half * 0.2, Math.min(width - r - half * 0.2, cx));
  return [
    `M${r} 0`,
    `H${left - half}`,
    `C${left - half * 0.62} 0 ${left - half * 0.62} ${depth} ${left - flat} ${depth}`,
    `H${left + flat}`,
    `C${left + half * 0.62} ${depth} ${left + half * 0.62} 0 ${left + half} 0`,
    `H${width - r}`,
    `A${r} ${r} 0 0 1 ${width} ${r}`,
    `V${HEIGHT - r}`,
    `A${r} ${r} 0 0 1 ${width - r} ${HEIGHT}`,
    `H${r}`,
    `A${r} ${r} 0 0 1 0 ${HEIGHT - r}`,
    `V${r}`,
    `A${r} ${r} 0 0 1 ${r} 0`,
    'Z',
  ].join(' ');
}

export default function AppTabBar({ state, descriptors, navigation }) {
  const insets = useSafeAreaInsets();
  const { width: screen } = useWindowDimensions();
  const width = screen - SIDE * 2;
  const tab = width / state.routes.length;
  const slide = useRef(new Animated.Value(state.index)).current;
  const [cx, setCx] = useState(state.index * tab + tab / 2);

  useEffect(() => {
    const id = slide.addListener(({ value }) => setCx(value * tab + tab / 2));
    Animated.timing(slide, { toValue: state.index, duration: 280, easing: Easing.out(Easing.cubic), useNativeDriver: false }).start();

    return () => slide.removeListener(id);
  }, [state.index, tab, slide]);

  // The strip under the bar matches the page behind it: Home is a blush surface, the other tabs the usual canvas.
  const surface = state.routes[state.index].name === 'index' ? SOFT.surface : COLORS.canvas;

  return (
    <View style={{ backgroundColor: surface }}>
      <NowPlayingBar />
      <View style={[styles.wrap, { backgroundColor: surface, paddingBottom: Math.max(insets.bottom, 8) }]}>
        <View style={{ width, height: HEIGHT + RISE, marginHorizontal: SIDE }}>
          <View style={[styles.pill, { height: HEIGHT }]}>
            <Svg width={width} height={HEIGHT}>
              <Defs>
                <SvgGradient id="wine" x1="0" y1="0" x2="1" y2="1">
                  <Stop offset="0" stopColor="#2A0B17" />
                  <Stop offset="1" stopColor="#5B1431" />
                </SvgGradient>
              </Defs>
              <Path d={outline(width, cx)} fill="url(#wine)" />
            </Svg>
          </View>

          <View style={[styles.bubble, { left: cx - 29, backgroundColor: surface }]} pointerEvents="none">
            <LinearGradient colors={['#E4668A', COLORS.primary, '#8E1C37']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.bubbleFill}>
              {descriptors[state.routes[state.index].key].options.tabBarIcon?.({ focused: true, color: '#fff', size: 25 })}
            </LinearGradient>
          </View>

          <View style={[styles.row, { height: HEIGHT }]}>
            {state.routes.map((route, index) => {
              const { options } = descriptors[route.key];
              const focused = state.index === index;
              const label = options.title ?? route.name;

              const onPress = () => {
                const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
                if (!focused && !event.defaultPrevented) navigation.navigate(route.name, route.params);
              };

              return (
                <Pressable
                  key={route.key}
                  onPress={onPress}
                  onLongPress={() => navigation.emit({ type: 'tabLongPress', target: route.key })}
                  accessibilityRole="button"
                  accessibilityState={{ selected: focused }}
                  accessibilityLabel={label}
                  style={styles.tab}
                >
                  {focused ? null : options.tabBarIcon?.({ focused: false, color: 'rgba(255,255,255,0.66)', size: 24 })}
                </Pressable>
              );
            })}
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { backgroundColor: COLORS.canvas },
  pill: { position: 'absolute', left: 0, right: 0, bottom: 0, borderRadius: 30, shadowColor: '#2A0B17', shadowOpacity: 0.35, shadowRadius: 18, shadowOffset: { width: 0, height: 10 }, elevation: 12 },
  bubble: { position: 'absolute', top: 0, width: 58, height: 58, borderRadius: 29, padding: 4, backgroundColor: COLORS.canvas, shadowColor: COLORS.primary, shadowOpacity: 0.5, shadowRadius: 12, shadowOffset: { width: 0, height: 6 }, elevation: 14 },
  bubbleFill: { flex: 1, borderRadius: 25, alignItems: 'center', justifyContent: 'center' },
  row: { position: 'absolute', left: 0, right: 0, bottom: 0, flexDirection: 'row' },
  tab: { flex: 1, alignItems: 'center', justifyContent: 'center' },
});
