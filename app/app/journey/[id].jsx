// A reading journey (Women of Faith, Psalms for every mood…): a path of blooms, one for each day. Pick a day to read its passage in your own Bible version, think
// about the question under it, and mark it read, so a bloom opens on the path. Progress stays on this phone. Content: lib/journeys.js.
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import PassageView from '../../components/PassageView';
import { Bloom, BloomCluster } from '../../components/rose/Bloom';
import ScreenHeader from '../../components/ScreenHeader';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { doneDays, journeyById, nextDay, resetJourney, setDayDone, useJourneyProgress } from '../../lib/journeys';

export default function Journey() {
  const { id } = useLocalSearchParams();
  const journey = journeyById(String(id));
  const progress = useJourneyProgress();
  const done = new Set(doneDays(progress, journey?.id));
  const next = journey ? nextDay(progress, journey) : null;
  const [picked, setPicked] = useState(null);

  // Open on the day you are up to (or the last day once the journey is finished) until you pick another.
  useEffect(() => {
    if (journey && picked === null) setPicked(next ?? journey.days.length);
  }, [journey, next, picked]);

  if (!journey) {
    return (
      <View style={styles.screen}>
        <ScreenHeader title="Journey" />
        <AppText style={{ padding: SPACING.md }}>This journey is not here any more.</AppText>
      </View>
    );
  }

  const day = journey.days[(picked ?? 1) - 1];
  const finished = done.size >= journey.days.length;
  const percent = Math.round((done.size / journey.days.length) * 100);

  function again() {
    Alert.alert('Start again?', 'The blooms go back to buds. You can walk this journey again from day 1.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Start again', style: 'destructive', onPress: () => { resetJourney(journey.id); setPicked(1); } },
    ]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title={journey.title} subtitle={finished ? 'Finished' : `${done.size} of ${journey.days.length} days`} />
      <ScrollView contentContainerStyle={styles.body} showsVerticalScrollIndicator={false}>
        <LinearGradient colors={journey.tint} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
          <BloomCluster tone="light" style={{ right: -10, top: -6 }} />
          <AppText style={styles.heroKicker}>{finished ? 'WELL DONE' : next ? `DAY ${next} IS NEXT` : ''}</AppText>
          <AppText style={styles.heroTitle}>{journey.title}</AppText>
          <AppText style={styles.heroText}>{journey.tagline}</AppText>
          <View style={styles.track}>
            <View style={[styles.fill, { width: `${percent}%` }]} />
          </View>
        </LinearGradient>

        <View style={styles.path}>
          {journey.days.map((d, i) => {
            const n = i + 1;
            const isDone = done.has(n);
            const on = picked === n;

            return (
              <Pressable key={n} onPress={() => setPicked(n)} style={styles.stop} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={`Day ${n}, ${d.title}${isDone ? ', read' : ''}`}>
                <View style={[styles.circle, on && styles.circleOn, isDone && styles.circleDone]}>
                  {isDone ? <Bloom size={30} color={COLORS.primarySoft} petals={6} /> : <AppText style={[styles.circleText, on && { color: COLORS.primary }]}>{n}</AppText>}
                </View>
              </Pressable>
            );
          })}
        </View>

        {day ? (
          <View style={styles.card}>
            <AppText style={styles.dayKicker}>DAY {picked}</AppText>
            <AppText style={styles.dayTitle}>{day.title}</AppText>
            <Pressable onPress={() => router.push({ pathname: '/bible/passage', params: { ref: day.ref } })} style={styles.ref} accessibilityRole="link" accessibilityLabel={`Open ${day.ref} in the Bible`}>
              <Ionicons name="book-outline" size={14} color={COLORS.primary} />
              <AppText style={styles.refText}>{day.ref}</AppText>
              <Ionicons name="arrow-forward" size={13} color={COLORS.primary} />
            </Pressable>
            <PassageView reference={day.ref} fontSize={17} />
            <View style={styles.thought}>
              <View style={styles.thoughtHead}>
                <Ionicons name="sparkles" size={16} color={COLORS.primary} />
                <AppText style={styles.thoughtKicker}>Something to think about</AppText>
              </View>
              <AppText style={styles.thoughtText}>{day.thought}</AppText>
            </View>
            <Button
              title={done.has(picked) ? 'Read ✿  (tap to undo)' : 'I have read this ✿'}
              variant={done.has(picked) ? 'outline' : 'primary'}
              onPress={() => {
                const was = done.has(picked);
                setDayDone(journey.id, picked, !was);
                // Moving on to the next day that is not read yet.
                if (!was && picked < journey.days.length) setPicked(picked + 1);
              }}
            />
          </View>
        ) : null}

        {finished ? (
          <View style={styles.finish}>
            <Bloom size={54} color={COLORS.primarySoft} petals={7} />
            <AppText style={styles.finishTitle}>You walked the whole way</AppText>
            <AppText style={styles.finishText}>{journey.days.length} days with {journey.title}. Keep going: choose another journey from the Bible tab.</AppText>
            <Button title="Start again" variant="outline" small onPress={again} />
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  body: { padding: SPACING.md, paddingBottom: 56, gap: 16 },
  hero: { borderRadius: RADIUS.xl, padding: 18, overflow: 'hidden', minHeight: 150, justifyContent: 'flex-end' },
  heroKicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.6, color: 'rgba(255,255,255,0.9)' },
  heroTitle: { fontFamily: FONTS.display, fontSize: 28, lineHeight: 34, color: COLORS.onColor, marginTop: 2 },
  heroText: { fontFamily: FONTS.medium, fontSize: 14, color: 'rgba(255,255,255,0.92)', marginTop: 2 },
  track: { height: 6, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.35)', marginTop: 14, overflow: 'hidden' },
  fill: { height: 6, borderRadius: 3, backgroundColor: COLORS.onColor },
  path: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  stop: { width: 46 },
  circle: { width: 46, height: 46, borderRadius: 23, backgroundColor: COLORS.white, borderWidth: 2, borderColor: COLORS.primaryPale, alignItems: 'center', justifyContent: 'center' },
  circleOn: { borderColor: COLORS.primary, backgroundColor: COLORS.primaryMist },
  circleDone: { backgroundColor: COLORS.primaryMist, borderColor: COLORS.primaryMist },
  circleText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.gray700 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: 18, gap: 10, ...SHADOW },
  dayKicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.6, color: COLORS.primary },
  dayTitle: { fontFamily: FONTS.display, fontSize: 24, lineHeight: 30, color: COLORS.black },
  ref: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6, marginBottom: 4 },
  refText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  thought: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14, gap: 6, marginVertical: 6 },
  thoughtHead: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  thoughtKicker: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary },
  thoughtText: { fontFamily: FONTS.displayRegular, fontSize: 16.5, lineHeight: 24, color: COLORS.black },
  finish: { alignItems: 'center', gap: 8, backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: 22, ...SHADOW },
  finishTitle: { fontFamily: FONTS.display, fontSize: 22, color: COLORS.black, textAlign: 'center' },
  finishText: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: COLORS.gray900, textAlign: 'center', marginBottom: 6 },
});
