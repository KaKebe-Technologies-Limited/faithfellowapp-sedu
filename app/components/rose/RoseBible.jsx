// What the Rose look adds to the top of the Bible tab: today's word on a soft card, the garden (a bloom for each of the last seven days you read), and the reading
// journeys (Women of Faith, Psalms for every mood, the fruit of the Spirit, loved and known). Data: GET /api/v1/inspiration, GET /api/v1/me/stats and the
// journeys kept on the phone (lib/journeys.js).
import { useCallback } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../AppText';
import { Bloom, BloomCluster, Garden } from './Bloom';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { JOURNEYS, doneDays, nextDay, useJourneyProgress } from '../../lib/journeys';
import { useFetch } from '../../lib/useFetch';

function TodaysWord({ word }) {
  if (!word?.body) return null;

  const open = () => word.reference && router.push({ pathname: '/bible/passage', params: { ref: word.reference } });

  return (
    <Pressable onPress={open} disabled={!word.reference} accessibilityRole="button" accessibilityLabel={`${word.label}. ${word.body}`} style={{ marginHorizontal: SPACING.md, marginTop: 14 }}>
      <LinearGradient colors={[COLORS.primaryMist, COLORS.pink]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.word}>
        <BloomCluster style={{ right: -34, top: -28, opacity: 0.8 }} />
        <AppText style={styles.kicker}>{String(word.label ?? "TODAY'S WORD").toUpperCase()}</AppText>
        <AppText style={styles.wordText}>{word.body}</AppText>
        {word.reference ? (
          <View style={styles.wordRef}>
            <AppText style={styles.wordRefText}>{word.reference}</AppText>
            <Ionicons name="arrow-forward" size={13} color={COLORS.primary} />
          </View>
        ) : null}
      </LinearGradient>
    </Pressable>
  );
}

function YourGarden({ stats }) {
  const week = stats?.week ?? [];
  const streak = stats?.streak?.current ?? 0;
  if (!week.length) return null;
  const read = week.filter((d) => d.read_minutes > 0).length;

  return (
    <View style={[styles.card, { marginHorizontal: SPACING.md, marginTop: 14 }]}>
      <View style={styles.cardHead}>
        <AppText style={styles.cardTitle}>Your garden</AppText>
        <AppText style={styles.cardNote}>{streak > 1 ? `${streak} days in a row` : read ? `${read} ${read === 1 ? 'day' : 'days'} this week` : 'Read today and a bloom opens'}</AppText>
      </View>
      <Garden days={week} />
    </View>
  );
}

function Journeys() {
  const progress = useJourneyProgress();

  return (
    <View style={{ marginTop: 18 }}>
      <View style={styles.sectionHead}>
        <View style={styles.sectionBar} />
        <AppText style={styles.section}>Journeys</AppText>
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: SPACING.md, gap: 12 }}>
        {JOURNEYS.map((j) => {
          const done = doneDays(progress, j.id).length;
          const next = nextDay(progress, j);

          return (
            <Pressable key={j.id} onPress={() => router.push({ pathname: '/journey/[id]', params: { id: j.id } })} accessibilityRole="button" accessibilityLabel={`${j.title}. ${done ? `${done} of ${j.days.length} days read` : `${j.days.length} days`}`}>
              <LinearGradient colors={j.tint} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.journey}>
                <View style={styles.journeyBloom} pointerEvents="none">
                  <Bloom size={92} color="rgba(255,255,255,0.28)" center="rgba(255,255,255,0.5)" petals={7} />
                </View>
                <View style={styles.journeyIcon}>
                  <Ionicons name={j.icon} size={18} color={COLORS.onColor} />
                </View>
                <AppText style={styles.journeyTitle} numberOfLines={2}>
                  {j.title}
                </AppText>
                <AppText style={styles.journeyText} numberOfLines={2}>
                  {j.tagline}
                </AppText>
                <View style={styles.journeyFoot}>
                  <AppText style={styles.journeyCount}>{!done ? `${j.days.length} days` : next ? `Day ${next} next` : 'Finished ✿'}</AppText>
                  {done ? <AppText style={styles.journeyCount}>{done}/{j.days.length}</AppText> : null}
                </View>
              </LinearGradient>
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

export default function RoseBible() {
  const stats = useFetch('/me/stats');
  const word = useFetch('/inspiration');

  useFocusEffect(
    useCallback(() => {
      stats.reload();
      word.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  return (
    <View>
      <TodaysWord word={word.data?.data} />
      <YourGarden stats={stats.data?.data} />
      <Journeys />
    </View>
  );
}

const styles = StyleSheet.create({
  word: { borderRadius: RADIUS.xl, padding: 18, overflow: 'hidden', gap: 6 },
  kicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.6, color: COLORS.primary },
  wordText: { fontFamily: FONTS.displayRegular, fontSize: 19, lineHeight: 27, color: COLORS.black },
  wordRef: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6, marginTop: 4 },
  wordRefText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: 16, gap: 12, ...SHADOW },
  cardHead: { flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between' },
  cardTitle: { fontFamily: FONTS.display, fontSize: 18, color: COLORS.black },
  cardNote: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.primary },
  sectionHead: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingHorizontal: SPACING.md, marginBottom: 12 },
  sectionBar: { width: 4, height: 20, borderRadius: 2, backgroundColor: COLORS.primary },
  section: { fontFamily: FONTS.display, fontSize: 19, color: COLORS.black },
  journey: { width: 214, minHeight: 168, borderRadius: RADIUS.xl, padding: 16, overflow: 'hidden', justifyContent: 'flex-end', gap: 3 },
  journeyBloom: { position: 'absolute', right: -22, top: -22 },
  journeyIcon: { position: 'absolute', left: 14, top: 14, width: 32, height: 32, borderRadius: 11, backgroundColor: 'rgba(255,255,255,0.28)', alignItems: 'center', justifyContent: 'center' },
  journeyTitle: { fontFamily: FONTS.display, fontSize: 20, lineHeight: 25, color: COLORS.onColor },
  journeyText: { fontFamily: FONTS.medium, fontSize: 12.5, lineHeight: 17, color: 'rgba(255,255,255,0.92)' },
  journeyFoot: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 },
  journeyCount: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.onColor },
});
