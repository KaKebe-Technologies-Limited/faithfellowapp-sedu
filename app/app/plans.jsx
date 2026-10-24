// Reading plans: the ones you are on (progress and streak), your reading circles (partners reading together),
// and plans from your churches you can start. "Join with a code" brings you into a friend's circle.
// Data: GET /api/v1/reading-plans, POST /reading-circles/join.
import { useCallback, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../components/AppText';
import Button from '../components/Button';
import ChurchAvatar from '../components/ChurchAvatar';
import ScreenHeader from '../components/ScreenHeader';
import SectionHeader from '../components/SectionHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../constants/theme';
import api, { errorMessage, fieldErrors, mediaUrl } from '../lib/api';
import { useFetch } from '../lib/useFetch';

function PlanCard({ plan }) {
  const p = plan.progress;
  return (
    <Pressable onPress={() => router.push({ pathname: '/plan/[id]', params: { id: String(plan.id) } })} style={styles.card} accessibilityRole="button" accessibilityLabel={`Plan: ${plan.title}`}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <ChurchAvatar church={plan.church} size={44} />
        <View style={{ flex: 1 }}>
          <AppText style={styles.title} numberOfLines={2}>
            {plan.title}
          </AppText>
          <AppText variant="caption" numberOfLines={1}>
            {plan.days_count} days · {plan.church.name}
          </AppText>
        </View>
        {p?.streak ? (
          <View style={styles.streak}>
            <AppText style={{ fontSize: 14 }}>🔥</AppText>
            <AppText style={styles.streakText}>{p.streak}</AppText>
          </View>
        ) : null}
      </View>
      {p ? (
        <>
          <View style={styles.track}>
            <View style={[styles.fill, { width: `${Math.max(p.percent, 2)}%` }]} />
          </View>
          <View style={styles.row}>
            <AppText variant="caption">{p.completed_at ? 'Finished 🎉' : `Day ${p.next_day} of ${plan.days_count}`}</AppText>
            <AppText style={styles.pct}>{Math.round(p.percent)}%</AppText>
          </View>
        </>
      ) : plan.description ? (
        <AppText variant="caption" numberOfLines={2} style={{ marginTop: 8 }}>
          {plan.description}
        </AppText>
      ) : null}
    </Pressable>
  );
}

export default function PlansScreen() {
  const { data, loading, refreshing, error, reload, retry } = useFetch('/reading-plans');
  const [code, setCode] = useState('');
  const [joining, setJoining] = useState(false);
  const [codeError, setCodeError] = useState(null);
  const plans = data?.data;

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  async function join() {
    if (code.trim().length !== 6) {
      setCodeError('The code has 6 letters and numbers.');
      return;
    }
    setJoining(true);
    setCodeError(null);
    try {
      const { data: res } = await api.post('/reading-circles/join', { code: code.trim() });
      setCode('');
      router.push({ pathname: '/circle/[id]', params: { id: String(res.data.id) } });
    } catch (e) {
      setCodeError(fieldErrors(e).code || errorMessage(e));
    } finally {
      setJoining(false);
    }
  }

  const empty = plans && !plans.mine.length && !plans.available.length && !plans.circles.length;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Reading plans" subtitle="A little of God's Word every day" />
      <StateView loading={loading && !plans} error={!plans ? error : null} onRetry={retry}>
        {plans ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <View style={styles.join}>
              <Ionicons name="people-outline" size={20} color={COLORS.primary} />
              <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={code} onChangeText={(t) => setCode(t.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6))} placeholder="Reading partner's code" placeholderTextColor={COLORS.gray500} style={styles.codeInput} autoCapitalize="characters" maxLength={6} onSubmitEditing={join} />
              <Button title="Join" small loading={joining} onPress={join} />
            </View>
            {codeError ? <AppText style={styles.err}>{codeError}</AppText> : null}

            {plans.mine.length ? (
              <View style={{ marginTop: SPACING.md }}>
                <SectionHeader title="My plans" />
                {plans.mine.map((p) => (
                  <PlanCard key={p.id} plan={p} />
                ))}
              </View>
            ) : null}

            {plans.circles.length ? (
              <View style={{ marginTop: SPACING.md }}>
                <SectionHeader title="My reading circles" />
                {plans.circles.map((c) => (
                  <Pressable key={c.id} onPress={() => router.push({ pathname: '/circle/[id]', params: { id: String(c.id) } })} style={styles.card} accessibilityRole="button" accessibilityLabel={`Circle: ${c.name}`}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                      <View style={styles.faces}>
                        {c.members.slice(0, 4).map((m, i) => (
                          <View key={m.id} style={[styles.face, { marginLeft: i ? -10 : 0 }]}>
                            {m.avatar_url ? <Image source={{ uri: mediaUrl(m.avatar_url) }} style={StyleSheet.absoluteFill} contentFit="cover" /> : <AppText style={styles.faceText}>{m.name.charAt(0)}</AppText>}
                          </View>
                        ))}
                      </View>
                      <View style={{ flex: 1 }}>
                        <AppText style={styles.title} numberOfLines={1}>
                          {c.name}
                        </AppText>
                        <AppText variant="caption" numberOfLines={1}>
                          {c.plan.title} · {c.schedule}
                        </AppText>
                      </View>
                      <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
                    </View>
                  </Pressable>
                ))}
              </View>
            ) : null}

            {plans.available.length ? (
              <View style={{ marginTop: SPACING.md }}>
                <SectionHeader title="Plans to start" />
                {plans.available.map((p) => (
                  <PlanCard key={p.id} plan={p} />
                ))}
              </View>
            ) : null}

            {empty ? <StateView empty emptyIcon="journal-outline" emptyTitle="No reading plans yet" emptyMessage="When your church publishes a plan, you will find it here. You can also join a friend's circle with their code." /> : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  join: { flexDirection: 'row', alignItems: 'center', gap: 10, marginHorizontal: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingHorizontal: 14, paddingVertical: 8, ...SHADOW, shadowOpacity: 0.04 },
  codeInput: { flex: 1, minHeight: 40, fontFamily: FONTS.bold, fontSize: 16, letterSpacing: 2, color: COLORS.black },
  err: { color: COLORS.danger, fontFamily: FONTS.medium, fontSize: 13, marginHorizontal: SPACING.md, marginTop: 6 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW, shadowOpacity: 0.05 },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, lineHeight: 20, color: COLORS.black },
  streak: { flexDirection: 'row', alignItems: 'center', gap: 3, backgroundColor: COLORS.warningMist, borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 5 },
  streakText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.warningInk },
  track: { height: 8, borderRadius: 4, backgroundColor: COLORS.primaryMist, marginTop: 12, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: COLORS.primary },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 },
  pct: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.primary },
  faces: { flexDirection: 'row' },
  face: { width: 36, height: 36, borderRadius: 18, backgroundColor: COLORS.primaryLight, borderWidth: 2, borderColor: COLORS.white, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  faceText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.onColor },
});
