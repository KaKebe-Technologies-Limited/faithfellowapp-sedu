// "Live now" card shown above the highlights when a church you follow is streaming. Tap opens the stream.
import { useEffect, useRef } from 'react';
import { Animated, Pressable, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';

export default function LiveBanner({ stream }) {
  const pulse = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 0.25, duration: 700, useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 1, duration: 700, useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [pulse]);

  if (!stream) return null;

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/live/[id]', params: { id: stream.id } })}
      accessibilityRole="button"
      accessibilityLabel={`${stream.church?.name} is live: ${stream.title}. Watch now`}
      style={styles.wrap}
    >
      <LinearGradient colors={['#D63031', '#8E1C37']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <ChurchAvatar church={stream.church} size={46} />
        <View style={styles.text}>
          <View style={styles.tag}>
            <Animated.View style={[styles.dot, { opacity: pulse }]} />
            <AppText style={styles.tagText}>LIVE NOW</AppText>
          </View>
          <AppText style={styles.title} numberOfLines={1}>
            {stream.title}
          </AppText>
          <AppText style={styles.church} numberOfLines={1}>
            {stream.church?.name}
          </AppText>
        </View>
        <View style={styles.watch}>
          <AppText style={styles.watchText}>Watch</AppText>
        </View>
      </LinearGradient>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.md },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: RADIUS.lg + 4, ...SHADOW, shadowOpacity: 0.18 },
  text: { flex: 1 },
  tag: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: COLORS.white },
  tagText: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1, color: COLORS.onColor },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.onColor, marginTop: 2 },
  church: { fontFamily: FONTS.regular, fontSize: 12, color: 'rgba(255,255,255,0.8)' },
  watch: { backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 9 },
  watchText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.danger },
});
