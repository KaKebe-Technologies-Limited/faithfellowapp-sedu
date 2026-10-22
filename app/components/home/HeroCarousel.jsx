// The slider at the top of the home screen: the highlights (the live service, events, giving campaigns, the devotional, sermons),
// each over its picture with a line of words and nothing else: no icons, no date tiles. It advances by itself, pauses while you
// touch it, and a tap opens the item. When a highlight has no picture of its own, a soft wine colour stands in.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, FlatList, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import AppText from '../AppText';

const AUTO_MS = 5500;

const ROUTES = {
  live: '/live/[id]',
  event: '/event/[id]',
  campaign: '/campaign/[id]',
  devotional: '/devotional/[id]',
  sermon: '/sermon/[id]',
  announcement: '/announcement/[id]',
};

/** Opens what a highlight is about: the live service, event, campaign, devotional, sermon or announcement, or a church's page. */
export function openItem(item) {
  if (item.kind === 'church' && item.slug) return router.push({ pathname: '/church/[slug]', params: { slug: item.slug } });
  if (ROUTES[item.kind]) router.push({ pathname: ROUTES[item.kind], params: { id: item.id } });
}

function Slide({ item, width, height }) {
  return (
    <View style={{ width, paddingHorizontal: SPACING.md }}>
      <Pressable accessibilityRole="button" accessibilityLabel={`${item.label}: ${item.title}`} onPress={() => openItem(item)} style={[styles.card, { height }]}>
        <LinearGradient colors={['#5B1431', COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        {item.image ? <Image source={{ uri: mediaUrl(item.image) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={350} /> : null}
        <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.25)', 'rgba(20,6,12,0.82)']} locations={[0.2, 0.55, 1]} style={StyleSheet.absoluteFill} />

        <View style={styles.content}>
          <AppText style={styles.label}>{String(item.label ?? '').toUpperCase()}</AppText>
          <AppText style={styles.title} numberOfLines={2}>
            {item.title}
          </AppText>
          {item.subtitle ? (
            <AppText style={styles.subtitle} numberOfLines={2}>
              {item.subtitle}
            </AppText>
          ) : null}
          {item.church ? (
            <AppText style={styles.church} numberOfLines={1}>
              {item.church}
            </AppText>
          ) : null}
        </View>
      </Pressable>
    </View>
  );
}

/** `items`: [{ kind, id, label, title, subtitle, image, church }]. */
export default function HeroCarousel({ items }) {
  const { width } = useWindowDimensions();
  const height = Math.min(300, Math.round((width - SPACING.md * 2) * 0.78));
  const listRef = useRef(null);
  const scrollX = useRef(new Animated.Value(0)).current;
  const index = useRef(0);
  const [current, setCurrent] = useState(0);
  const [paused, setPaused] = useState(false);
  const count = items.length;

  const goTo = useCallback(
    (next) => {
      index.current = next;
      setCurrent(next);
      listRef.current?.scrollToOffset({ offset: next * width, animated: true });
    },
    [width],
  );

  useEffect(() => {
    if (count < 2 || paused) return undefined;
    const timer = setInterval(() => goTo((index.current + 1) % count), AUTO_MS);

    return () => clearInterval(timer);
  }, [count, paused, goTo]);

  if (!count) return null;

  return (
    <View style={styles.wrap}>
      <FlatList
        ref={listRef}
        data={items}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        keyExtractor={(item, i) => `${item.kind}-${item.id ?? i}-${i}`}
        getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })}
        renderItem={({ item }) => <Slide item={item} width={width} height={height} />}
        onScroll={Animated.event([{ nativeEvent: { contentOffset: { x: scrollX } } }], { useNativeDriver: false })}
        scrollEventThrottle={16}
        onScrollBeginDrag={() => setPaused(true)}
        onMomentumScrollEnd={(e) => {
          const next = Math.round(e.nativeEvent.contentOffset.x / width);
          index.current = next;
          setCurrent(next);
          setPaused(false);
        }}
        initialNumToRender={2}
        windowSize={3}
      />

      {count > 1 ? (
        <View style={styles.dots} pointerEvents="none">
          {items.slice(0, 10).map((_, i) => {
            const input = [(i - 1) * width, i * width, (i + 1) * width];
            return (
              <Animated.View
                key={i}
                style={[
                  styles.dot,
                  {
                    width: scrollX.interpolate({ inputRange: input, outputRange: [8, 26, 8], extrapolate: 'clamp' }),
                    opacity: scrollX.interpolate({ inputRange: input, outputRange: [0.5, 1, 0.5], extrapolate: 'clamp' }),
                  },
                ]}
              />
            );
          })}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: SPACING.sm },
  card: { borderRadius: 28, overflow: 'hidden', backgroundColor: COLORS.primaryDark, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.25)' },
  content: { position: 'absolute', left: 18, right: 18, bottom: 34 },
  label: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.6, color: 'rgba(255,255,255,0.9)', marginBottom: 4 },
  title: { fontFamily: FONTS.bold, fontSize: 24, lineHeight: 30, color: COLORS.white },
  subtitle: { fontFamily: FONTS.medium, fontSize: 14, lineHeight: 20, color: 'rgba(255,255,255,0.9)', marginTop: 3 },
  church: { fontFamily: FONTS.regular, fontSize: 12.5, color: 'rgba(255,255,255,0.72)', marginTop: 5 },
  dots: { position: 'absolute', bottom: 14, left: 0, right: 0, flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 5 },
  dot: { height: 4, borderRadius: 2, backgroundColor: COLORS.white },
});
