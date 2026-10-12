// The banner slider at the top of the home screen: the churches' photos, taking turns between the
// churches you follow. Swipe or wait; it advances by itself and pauses while you touch it.
// Each slide shows the church badge, name, title and subtitle over a soft dark gradient; tap opens the church.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, FlatList, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { initials } from '../../lib/format';
import AppText from '../AppText';

const AUTO_MS = 5500;

function Slide({ slide, width, height }) {
  const logo = mediaUrl(slide.church?.logo_url);

  return (
    <View style={{ width, paddingHorizontal: SPACING.md }}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${slide.title}, ${slide.church?.name ?? ''}`}
        onPress={() => slide.church && router.push({ pathname: '/church/[slug]', params: { slug: slide.church.slug } })}
        style={[styles.card, { height }]}
      >
        <Image source={{ uri: mediaUrl(slide.image) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={350} />
        <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.18)', 'rgba(20,6,12,0.78)']} locations={[0.25, 0.55, 1]} style={StyleSheet.absoluteFill} />

        <View style={styles.content}>
          <View style={styles.badge}>
            {logo ? (
              <Image source={{ uri: logo }} style={styles.badgeImage} contentFit="cover" />
            ) : (
              <AppText style={{ fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary }}>{initials(slide.church?.name)}</AppText>
            )}
          </View>
          <View style={styles.text}>
            <AppText style={styles.church} numberOfLines={1}>
              {(slide.church?.name ?? '').toUpperCase()}
            </AppText>
            <AppText style={styles.title} numberOfLines={1}>
              {slide.title}
            </AppText>
            {slide.subtitle ? (
              <AppText style={styles.subtitle} numberOfLines={2}>
                {slide.subtitle}
              </AppText>
            ) : null}
          </View>
        </View>
      </Pressable>
    </View>
  );
}

export default function HeroCarousel({ slides }) {
  const { width } = useWindowDimensions();
  const height = Math.min(300, Math.round((width - SPACING.md * 2) * 0.72));
  const listRef = useRef(null);
  const scrollX = useRef(new Animated.Value(0)).current;
  const index = useRef(0);
  const [current, setCurrent] = useState(0);
  const [paused, setPaused] = useState(false);
  const count = slides.length;

  const goTo = useCallback((next) => {
    index.current = next;
    setCurrent(next);
    listRef.current?.scrollToOffset({ offset: next * width, animated: true });
  }, [width]);

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
        data={slides}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        keyExtractor={(slide, i) => `${slide.church?.id ?? 'c'}-${i}`}
        getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })}
        renderItem={({ item }) => <Slide slide={item} width={width} height={height} />}
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

      {count > 1 && count <= 10 ? (
        <View style={styles.dots} pointerEvents="none">
          {slides.map((_, i) => {
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

      {count > 10 ? (
        <View style={styles.counter} pointerEvents="none">
          <AppText style={styles.counterText}>
            {current + 1} / {count}
          </AppText>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: SPACING.md },
  card: { borderRadius: RADIUS.xl, overflow: 'hidden', backgroundColor: COLORS.primaryDark, ...SHADOW, shadowOpacity: 0.16 },
  content: { position: 'absolute', left: 14, right: 14, bottom: 30, flexDirection: 'row', alignItems: 'flex-end', gap: 12 },
  badge: { width: 52, height: 52, borderRadius: 14, borderWidth: 2, borderColor: COLORS.white, backgroundColor: COLORS.white, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' },
  badgeImage: { width: '100%', height: '100%' },
  text: { flex: 1 },
  church: { fontFamily: FONTS.bold, fontSize: 10, letterSpacing: 1.4, color: 'rgba(255,255,255,0.92)', marginBottom: 2 },
  title: { fontFamily: FONTS.bold, fontSize: 21, lineHeight: 26, color: COLORS.white },
  subtitle: { fontFamily: FONTS.regular, fontSize: 13, lineHeight: 18, color: 'rgba(255,255,255,0.86)', marginTop: 1 },
  dots: { position: 'absolute', bottom: 12, left: 0, right: 0, flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 5 },
  dot: { height: 4, borderRadius: 2, backgroundColor: COLORS.white },
  counter: { position: 'absolute', bottom: 12, right: SPACING.md + 12, backgroundColor: 'rgba(0,0,0,0.45)', borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 3 },
  counterText: { fontFamily: FONTS.semibold, fontSize: 11, color: COLORS.white },
});
