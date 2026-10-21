// The banner at the top of the Sermons page: a slider of sermons by different preachers, each over its picture with the preacher's name in big
// letters, the sermon under it, and buttons to listen (no video needed) or to open it. It slides by itself, stops while you touch it, and a tap
// opens the sermon.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, FlatList, Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { mediaUrl } from '../../lib/api';
import { playSermonAudio, useSermonAudio } from '../../lib/sermonAudio';
import AppText from '../AppText';
import { colorFor } from '../Avatar';
import { preacherInitials } from './PreacherStrip';

const AUTO_MS = 6000;
const open = (s) => router.push({ pathname: '/sermon/[id]', params: { id: s.id } });

function ListenButton({ sermon, list }) {
  const { playing } = useSermonAudio(sermon);
  if (!sermon.audio_url) return null;

  return (
    <Pressable onPress={() => playSermonAudio(sermon, list)} style={({ pressed }) => [styles.listen, pressed && { opacity: 0.88 }]} accessibilityRole="button" accessibilityLabel={playing ? 'Pause the sermon' : 'Listen to the sermon'}>
      <Ionicons name={playing ? 'pause' : 'headset'} size={17} color={COLORS.primary} />
      <AppText style={styles.listenText}>{playing ? 'Pause' : 'Listen'}</AppText>
    </Pressable>
  );
}

function Slide({ sermon: s, list, width, height }) {
  const name = s.preacher || s.church?.name || '';

  return (
    <View style={{ width, paddingHorizontal: SPACING.md }}>
      <View style={[styles.card, { height }]}>
        <LinearGradient colors={['#5B1431', COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
        {s.thumbnail_url ? <Image source={{ uri: mediaUrl(s.thumbnail_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={350} /> : null}
        <LinearGradient colors={['rgba(20,6,12,0.1)', 'rgba(20,6,12,0.35)', 'rgba(20,6,12,0.9)']} locations={[0, 0.45, 1]} style={StyleSheet.absoluteFill} />
        <Pressable onPress={() => open(s)} accessibilityRole="button" accessibilityLabel={`${name}: ${s.title}`} style={StyleSheet.absoluteFill} />

        <View style={styles.top} pointerEvents="none">
          <View style={[styles.avatar, { backgroundColor: colorFor(name) }]}>
            <AppText allowFontScaling={false} style={styles.avatarText}>
              {preacherInitials(name)}
            </AppText>
          </View>
          <AppText style={styles.label}>{s.preacher ? 'PREACHER' : 'NEW SERMON'}</AppText>
        </View>

        <View style={styles.content} pointerEvents="box-none">
          <AppText style={styles.name} numberOfLines={2} pointerEvents="none">
            {name}
          </AppText>
          <AppText style={styles.title} numberOfLines={2}>
            {s.title}
          </AppText>
          <AppText style={styles.meta} numberOfLines={1}>
            {[s.preacher ? s.church?.name : null, s.scripture].filter(Boolean).join(' · ')}
          </AppText>
          <View style={styles.buttons}>
            <ListenButton sermon={s} list={list} />
            <Pressable onPress={() => open(s)} style={({ pressed }) => [styles.open, pressed && { opacity: 0.85 }]} accessibilityRole="button" accessibilityLabel="Open the sermon">
              <Ionicons name={s.video_url ? 'videocam' : 'document-text-outline'} size={16} color="#fff" />
              <AppText style={styles.openText}>{s.video_url ? 'Watch' : 'Open'}</AppText>
            </Pressable>
          </View>
        </View>
      </View>
    </View>
  );
}

/** `sermons`: the banner sermons from GET /sermons/browse. */
export default function SermonHero({ sermons }) {
  const { width } = useWindowDimensions();
  const height = Math.min(360, Math.round((width - SPACING.md * 2) * 0.98));
  const listRef = useRef(null);
  const scrollX = useRef(new Animated.Value(0)).current;
  const index = useRef(0);
  const [paused, setPaused] = useState(false);
  const count = sermons.length;

  const goTo = useCallback(
    (next) => {
      index.current = next;
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
        data={sermons}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        keyExtractor={(s) => String(s.id)}
        getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })}
        renderItem={({ item }) => <Slide sermon={item} list={sermons} width={width} height={height} />}
        onScroll={Animated.event([{ nativeEvent: { contentOffset: { x: scrollX } } }], { useNativeDriver: false })}
        scrollEventThrottle={16}
        onScrollBeginDrag={() => setPaused(true)}
        onMomentumScrollEnd={(e) => {
          index.current = Math.round(e.nativeEvent.contentOffset.x / width);
          setPaused(false);
        }}
        initialNumToRender={2}
        windowSize={3}
      />
      {count > 1 ? (
        <View style={styles.dots} pointerEvents="none">
          {sermons.map((_, i) => {
            const input = [(i - 1) * width, i * width, (i + 1) * width];

            return (
              <Animated.View
                key={i}
                style={[
                  styles.dot,
                  {
                    width: scrollX.interpolate({ inputRange: input, outputRange: [8, 26, 8], extrapolate: 'clamp' }),
                    opacity: scrollX.interpolate({ inputRange: input, outputRange: [0.45, 1, 0.45], extrapolate: 'clamp' }),
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
  wrap: { marginTop: SPACING.sm, marginBottom: SPACING.md },
  card: { borderRadius: RADIUS.xl, overflow: 'hidden', backgroundColor: COLORS.primaryDark, ...SHADOW, shadowOpacity: 0.18 },
  top: { position: 'absolute', left: 16, top: 16, flexDirection: 'row', alignItems: 'center', gap: 10 },
  avatar: { width: 46, height: 46, borderRadius: 23, alignItems: 'center', justifyContent: 'center', borderWidth: 2.5, borderColor: 'rgba(255,255,255,0.9)' },
  avatarText: { fontFamily: FONTS.bold, fontSize: 16, color: '#fff' },
  label: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.4, color: 'rgba(255,255,255,0.9)' },
  content: { position: 'absolute', left: 18, right: 18, bottom: 22 },
  name: { fontFamily: FONTS.bold, fontSize: 30, lineHeight: 35, color: '#fff' },
  title: { fontFamily: FONTS.semibold, fontSize: 16, lineHeight: 22, color: 'rgba(255,255,255,0.94)', marginTop: 4 },
  meta: { fontFamily: FONTS.medium, fontSize: 13, color: 'rgba(255,255,255,0.75)', marginTop: 4 },
  buttons: { flexDirection: 'row', gap: 10, marginTop: 14 },
  listen: { flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: '#fff', borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  listenText: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  open: { flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 10, borderWidth: 1, borderColor: 'rgba(255,255,255,0.45)' },
  openText: { fontFamily: FONTS.semibold, fontSize: 14, color: '#fff' },
  dots: { position: 'absolute', alignSelf: 'center', bottom: 8, flexDirection: 'row', gap: 6 },
  dot: { height: 8, borderRadius: 4, backgroundColor: '#fff' },
});
