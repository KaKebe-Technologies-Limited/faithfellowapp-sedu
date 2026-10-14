// Quiz games: join a live Bible quiz with the PIN the host shows, jump into one that is on now at your churches,
// and look back at the games you played. Hosting a game is done by church staff in the Admin area.
// Data: GET /api/v1/games, POST /games/join.
import { useCallback, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../components/AppText';
import Button from '../components/Button';
import ChurchAvatar from '../components/ChurchAvatar';
import ScreenHeader from '../components/ScreenHeader';
import SectionHeader from '../components/SectionHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../constants/theme';
import api, { errorMessage, fieldErrors } from '../lib/api';
import { timeAgo } from '../lib/format';
import { useFetch } from '../lib/useFetch';

export default function GamesScreen() {
  const { data, loading, refreshing, error, reload, retry } = useFetch('/games');
  const [pin, setPin] = useState('');
  const [joining, setJoining] = useState(false);
  const [pinError, setPinError] = useState(null);
  const g = data?.data;

  useFocusEffect(
    useCallback(() => {
      reload();
      const t = setInterval(reload, 15000);
      return () => clearInterval(t);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  async function join() {
    if (pin.length < 4) {
      setPinError('Enter the PIN shown on the big screen.');
      return;
    }
    setJoining(true);
    setPinError(null);
    try {
      const { data: res } = await api.post('/games/join', { pin });
      setPin('');
      router.push({ pathname: '/game/[id]', params: { id: String(res.data.id) } });
    } catch (e) {
      setPinError(fieldErrors(e).pin || errorMessage(e));
    } finally {
      setJoining(false);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Quiz games" subtitle="Play Bible quizzes live" />
      <StateView loading={loading && !g} error={!g ? error : null} onRetry={retry}>
        {g ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <LinearGradient colors={['#7C4DFF', '#4527A0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.join}>
              <Ionicons name="game-controller" size={28} color={COLORS.white} />
              <AppText style={styles.joinTitle}>Join a game</AppText>
              <AppText style={styles.joinText}>Type the PIN shown on the screen at church.</AppText>
              <View style={styles.pinRow}>
                <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={pin} onChangeText={(t) => setPin(t.replace(/\D/g, '').slice(0, 8))} placeholder="PIN" placeholderTextColor="rgba(255,255,255,0.55)" keyboardType="number-pad" style={styles.pinInput} maxLength={8} onSubmitEditing={join} accessibilityLabel="Game PIN" />
                <Button title="Join" variant="light" loading={joining} onPress={join} style={{ minWidth: 96 }} />
              </View>
              {pinError ? <AppText style={styles.pinError}>{pinError}</AppText> : null}
            </LinearGradient>

            <View style={{ marginTop: SPACING.md }}>
              <SectionHeader title="On now" />
              {g.live.length ? (
                g.live.map((s) => (
                  <Pressable key={s.id} onPress={() => router.push({ pathname: '/game/[id]', params: { id: String(s.id) } })} style={styles.card} accessibilityRole="button" accessibilityLabel={`Join ${s.quiz}`}>
                    <ChurchAvatar church={s.church} size={44} />
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title} numberOfLines={1}>
                        {s.quiz}
                      </AppText>
                      <AppText variant="caption" numberOfLines={1}>
                        {s.church.name} · {s.players_count} playing · {s.status === 'lobby' ? 'starting soon' : 'in progress'}
                      </AppText>
                    </View>
                    <View style={styles.play}>
                      <AppText style={styles.playText}>Play</AppText>
                    </View>
                  </Pressable>
                ))
              ) : (
                <View style={styles.none}>
                  <Ionicons name="hourglass-outline" size={22} color={COLORS.primary} />
                  <AppText style={{ flex: 1, color: COLORS.gray900 }}>No game is on right now. When a church host starts one, it appears here and you get a notice.</AppText>
                </View>
              )}
            </View>

            {g.mine.length ? (
              <View style={{ marginTop: SPACING.md }}>
                <SectionHeader title="Your games" />
                {g.mine.map((m) => (
                  <View key={`${m.session_id}`} style={styles.card}>
                    <View style={styles.trophy}>
                      <Ionicons name="trophy-outline" size={20} color="#C25B00" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title}>{m.quiz}</AppText>
                      <AppText variant="caption">{m.correct} correct · {timeAgo(m.at)}</AppText>
                    </View>
                    <AppText style={styles.score}>{m.score}</AppText>
                  </View>
                ))}
              </View>
            ) : null}

            {g.recent.length ? (
              <View style={{ marginTop: SPACING.md }}>
                <SectionHeader title="Recent games at your churches" />
                {g.recent.map((s) => (
                  <View key={s.id} style={styles.card}>
                    <ChurchAvatar church={s.church} size={40} />
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.title} numberOfLines={1}>{s.quiz}</AppText>
                      <AppText variant="caption">{s.players_count} played · {timeAgo(s.finished_at)}</AppText>
                    </View>
                  </View>
                ))}
              </View>
            ) : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  join: { marginHorizontal: SPACING.md, borderRadius: RADIUS.xl, padding: SPACING.lg, gap: 4 },
  joinTitle: { fontFamily: FONTS.bold, fontSize: 24, color: COLORS.white, marginTop: 4 },
  joinText: { fontFamily: FONTS.regular, fontSize: 14, color: 'rgba(255,255,255,0.85)' },
  pinRow: { flexDirection: 'row', gap: 10, marginTop: 14, alignItems: 'center' },
  pinInput: { flex: 1, minWidth: 0, height: 52, borderRadius: RADIUS.lg, backgroundColor: 'rgba(255,255,255,0.18)', color: COLORS.white, fontFamily: FONTS.bold, fontSize: 24, letterSpacing: 6, textAlign: 'center' },
  pinError: { color: '#FFD7E5', fontFamily: FONTS.semibold, fontSize: 13, marginTop: 8 },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW, shadowOpacity: 0.05 },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  play: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 9 },
  playText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.white },
  none: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14, marginHorizontal: SPACING.md },
  trophy: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#FFF3E0', alignItems: 'center', justifyContent: 'center' },
  score: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.primary },
});
