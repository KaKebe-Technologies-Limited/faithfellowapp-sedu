// Games on Home: a live game from one of your churches (with a pulsing LIVE tag and a Join button), or an invitation to play,
// with your own record. Both open the Games screen, where you start a game, join one with its code, or answer an invitation.
import { useEffect, useRef } from 'react';
import { Animated, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { FONTS, SPACING } from '../../constants/theme';
import AppText from '../AppText';

function Stat({ icon, value, label }) {
  return (
    <View style={styles.stat}>
      <Ionicons name={icon} size={16} color="#FFD98A" />
      <AppText style={styles.statValue}>{value}</AppText>
      <AppText style={styles.statLabel}>{label}</AppText>
    </View>
  );
}

export default function QuizCard({ quiz }) {
  const pulse = useRef(new Animated.Value(1)).current;
  const live = quiz?.live?.[0];

  useEffect(() => {
    if (!live) return undefined;
    const loop = Animated.loop(Animated.sequence([Animated.timing(pulse, { toValue: 0.3, duration: 700, useNativeDriver: true }), Animated.timing(pulse, { toValue: 1, duration: 700, useNativeDriver: true })]));
    loop.start();

    return () => loop.stop();
  }, [live, pulse]);

  if (!quiz) return null;

  return (
    <Pressable onPress={() => router.push('/games')} style={({ pressed }) => [styles.wrap, pressed && { opacity: 0.95 }]} accessibilityRole="button" accessibilityLabel={live ? `Live game: ${live.quiz}. Join` : 'Games'}>
      <LinearGradient colors={['#2B1763', '#5A32B8', '#8B5CF6']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={styles.bubbleA} />
        <View style={styles.bubbleB} />
        <Ionicons name="help" size={120} color="rgba(255,255,255,0.08)" style={styles.bigIcon} />
        <View style={styles.top}>
          <View style={styles.iconBox}>
            <Ionicons name="game-controller" size={22} color="#fff" />
          </View>
          {live ? (
            <View style={styles.liveTag}>
              <Animated.View style={[styles.dot, { opacity: pulse }]} />
              <AppText style={styles.liveText}>LIVE NOW</AppText>
            </View>
          ) : (
            <AppText style={styles.kicker}>GAMES</AppText>
          )}
        </View>
        <AppText style={styles.title}>{live ? live.quiz : 'Test your Bible knowledge'}</AppText>
        <AppText style={styles.sub}>{live ? `${live.church?.name} · ${live.players_count} playing` : 'Start a game, invite friends, answer fast and climb the leaderboard.'}</AppText>
        <View style={styles.bottom}>
          {quiz.played ? (
            <View style={styles.stats}>
              <Stat icon="trophy-outline" value={quiz.best_score} label="best score" />
              <Stat icon="flag-outline" value={quiz.played} label={quiz.played === 1 ? 'game' : 'games'} />
            </View>
          ) : (
            <View style={{ flex: 1 }} />
          )}
          <View style={styles.cta}>
            <AppText style={styles.ctaText}>{live ? 'Join game' : 'Play'}</AppText>
            <Ionicons name="arrow-forward" size={16} color="#3B1F85" />
          </View>
        </View>
      </LinearGradient>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg, shadowColor: '#2B1763', shadowOpacity: 0.28, shadowRadius: 16, shadowOffset: { width: 0, height: 9 }, elevation: 7 },
  card: { borderRadius: 26, padding: 20, overflow: 'hidden' },
  bubbleA: { position: 'absolute', left: -30, top: -40, width: 140, height: 140, borderRadius: 70, backgroundColor: 'rgba(255,255,255,0.09)' },
  bubbleB: { position: 'absolute', right: 30, bottom: -50, width: 120, height: 120, borderRadius: 60, backgroundColor: 'rgba(255,255,255,0.07)' },
  bigIcon: { position: 'absolute', right: 8, top: 4 },
  top: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 12 },
  iconBox: { width: 42, height: 42, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.18)', alignItems: 'center', justifyContent: 'center' },
  kicker: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.6, color: '#FFD98A' },
  liveTag: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: '#E5384F', borderRadius: 10, paddingHorizontal: 10, paddingVertical: 4 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: '#fff' },
  liveText: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1, color: '#fff' },
  title: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 28, color: '#fff' },
  sub: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 19, color: 'rgba(255,255,255,0.85)', marginTop: 4, maxWidth: '88%' },
  bottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 18 },
  stats: { flexDirection: 'row', gap: 16 },
  stat: { alignItems: 'flex-start' },
  statValue: { fontFamily: FONTS.bold, fontSize: 17, color: '#fff', marginTop: 2 },
  statLabel: { fontFamily: FONTS.medium, fontSize: 11, color: 'rgba(255,255,255,0.7)' },
  cta: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#fff', borderRadius: 24, paddingHorizontal: 20, height: 44 },
  ctaText: { fontFamily: FONTS.bold, fontSize: 14.5, color: '#3B1F85' },
});
