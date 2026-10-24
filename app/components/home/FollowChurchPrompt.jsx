// A pop-up on Home for a member who follows no church yet (a new account, mostly): it says what following a church brings (its sermons, events, live
// services, prayer requests and giving, in real time) and suggests churches to follow with one tap. At first the suggestions come from the member's city
// and country; "Find churches near me" asks the phone for its place and shows the nearest first, with how far they are (the place is used for that
// answer only and is not saved, see POST /api/v1/churches/suggest). "Discover more churches" opens the full directory. "Maybe later" hides the
// pop-up for a day. It waits for the opening flash to finish, and stays away while a church's join link is being followed.
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Modal, Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import * as Location from 'expo-location';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { SOFT } from '../../constants/soft';
import api, { errorMessage } from '../../lib/api';
import { useFlashDone } from '../../lib/appStart';
import { usePendingJoin } from '../../lib/join';
import { getStored, setStored } from '../../lib/storage';
import AppText from '../AppText';
import Button from '../Button';
import ChurchAvatar from '../ChurchAvatar';

const HIDDEN_KEY = 'follow_prompt_hidden_until';
const HIDE_DAYS = 1;
const BENEFITS = [
  ['play-circle', 'Sermons, podcasts and live services from your church'],
  ['calendar', 'Events, notices and prayer requests as they happen'],
  ['heart', 'Give, and stay close to your church family'],
];

export default function FollowChurchPrompt({ enabled, onFollowed }) {
  const flashDone = useFlashDone();
  const { pending } = usePendingJoin();
  const insets = useSafeAreaInsets();
  const { height } = useWindowDimensions();
  const [open, setOpen] = useState(false);
  const [rows, setRows] = useState(null);
  const [located, setLocated] = useState(false);
  const [error, setError] = useState(null);
  const [followed, setFollowed] = useState({}); // slug → 'active' | 'pending'
  const [busy, setBusy] = useState(null);
  const [locating, setLocating] = useState(false);
  const [note, setNote] = useState(null);

  const load = useCallback(async (place) => {
    setError(null);
    try {
      const { data } = await api.post('/churches/suggest', place ?? {});
      setRows(data.data);
      setLocated(!!data.located);
    } catch (e) {
      setError(errorMessage(e));
      setRows((r) => r ?? []);
    }
  }, []);

  // Opens a moment after Home has settled, unless it was put off today, the opening flash is still playing, or a join link is being followed.
  useEffect(() => {
    if (!enabled || !flashDone || pending || open) return undefined;
    let cancelled = false;
    const timer = setTimeout(async () => {
      const hiddenUntil = await getStored(HIDDEN_KEY, 0);
      if (cancelled || hiddenUntil > Date.now()) return;
      setOpen(true);
      load();
    }, 1400);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, flashDone, pending]);

  async function later() {
    await setStored(HIDDEN_KEY, Date.now() + HIDE_DAYS * 24 * 60 * 60 * 1000);
    setOpen(false);
  }

  function done() {
    setOpen(false);
    onFollowed?.();
  }

  async function follow(church) {
    setBusy(church.slug);
    try {
      const { data } = await api.post(`/churches/${church.slug}/subscription`);
      setFollowed((f) => ({ ...f, [church.slug]: data.status === 'pending' ? 'pending' : 'active' }));
    } catch (e) {
      Alert.alert('Could not follow', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  async function findNearMe() {
    setLocating(true);
    setNote(null);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        setNote('Allow location for FaithFellows in your phone settings to see the churches nearest to you.');
        return;
      }
      const here = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      await load({ latitude: Math.round(here.coords.latitude * 100) / 100, longitude: Math.round(here.coords.longitude * 100) / 100 });
    } catch {
      setNote('We could not find your location. Check that location is switched on, or discover churches by name.');
    } finally {
      setLocating(false);
    }
  }

  async function discover() {
    await later();
    router.push({ pathname: '/churches', params: { tab: 'discover' } });
  }

  const count = Object.keys(followed).length;
  const title = count ? (count === 1 ? 'You are in! 🎉' : 'You are all set! 🎉') : 'Find your church family';

  return (
    <Modal visible={open} animationType="slide" transparent onRequestClose={later}>
      <Pressable style={styles.backdrop} onPress={later} accessibilityLabel="Close" />
      <View style={styles.wrap} pointerEvents="box-none">
        <View style={[styles.sheet, { maxHeight: height * 0.9, paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.grab} />
          <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={{ paddingBottom: 4 }}>
            <View style={styles.head}>
              <LinearGradient colors={['#F27EA8', COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.icon}>
                <Ionicons name="business" size={30} color="#fff" />
              </LinearGradient>
              <AppText style={styles.title}>{title}</AppText>
              <AppText style={styles.lead}>
                {count ? 'Your home screen now shows what is happening at your church, as it happens.' : 'Follow a church to see more of the app, with everything from your church in real time.'}
              </AppText>
            </View>

            {count ? null : (
              <View style={styles.benefits}>
                {BENEFITS.map(([icon, text]) => (
                  <View key={text} style={styles.benefit}>
                    <View style={styles.benefitIcon}>
                      <Ionicons name={icon} size={17} color={COLORS.primary} />
                    </View>
                    <AppText style={styles.benefitText}>{text}</AppText>
                  </View>
                ))}
              </View>
            )}

            <AppText style={styles.section}>{located ? 'Churches near you' : 'Churches for you'}</AppText>
            {rows === null ? <ActivityIndicator color={COLORS.primary} style={{ marginVertical: 22 }} /> : null}
            {error && !rows?.length ? (
              <Pressable onPress={() => load()} accessibilityRole="button">
                <AppText style={styles.error}>We could not load churches. Tap to try again.</AppText>
              </Pressable>
            ) : null}
            {rows && !rows.length && !error ? <AppText style={styles.empty}>No churches to suggest yet. Discover them by name below.</AppText> : null}

            {(rows ?? []).map((c) => {
              const state = followed[c.slug];
              return (
                <View key={c.id} style={styles.row}>
                  <ChurchAvatar church={c} size={46} />
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <AppText style={styles.name} numberOfLines={2}>
                      {c.name}
                    </AppText>
                    <View style={styles.meta}>
                      <AppText style={styles.why} numberOfLines={1}>
                        {c.why}
                      </AppText>
                      {c.followers ? (
                        <View style={styles.count} accessibilityLabel={`${c.followers} people follow this church`}>
                          <Ionicons name="people" size={12} color={SOFT.muted} />
                          <AppText style={styles.why}>{c.followers}</AppText>
                        </View>
                      ) : null}
                    </View>
                  </View>
                  {state ? (
                    <View style={styles.following}>
                      <Ionicons name="checkmark" size={15} color={COLORS.success} />
                      <AppText style={styles.followingText}>{state === 'pending' ? 'Requested' : 'Following'}</AppText>
                    </View>
                  ) : (
                    <Button title="Follow" small loading={busy === c.slug} onPress={() => follow(c)} />
                  )}
                </View>
              );
            })}

            {note ? <AppText style={styles.error}>{note}</AppText> : null}

            <View style={styles.actions}>
              {count ? (
                <Button title="Done" onPress={done} />
              ) : (
                <>
                  {!located ? <Button title={locating ? 'Finding your place…' : 'Find churches near me'} variant="outline" loading={locating} onPress={findNearMe} /> : null}
                  {!located ? <AppText style={styles.privacy}>Your location is only used to find churches near you. It is not saved.</AppText> : null}
                </>
              )}
              <Pressable onPress={discover} style={styles.link} accessibilityRole="button">
                <AppText style={styles.linkText}>Discover more churches</AppText>
              </Pressable>
              {count ? null : (
                <Pressable onPress={later} style={styles.link} accessibilityRole="button">
                  <AppText style={styles.laterText}>Maybe later</AppText>
                </Pressable>
              )}
            </View>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.55)' },
  wrap: { flex: 1, justifyContent: 'flex-end' },
  sheet: { backgroundColor: SOFT.surface, borderTopLeftRadius: 30, borderTopRightRadius: 30, paddingHorizontal: SPACING.md + 2, paddingTop: 10 },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 14 },
  head: { alignItems: 'center', gap: 6 },
  icon: { width: 68, height: 68, borderRadius: 24, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  title: { fontFamily: FONTS.bold, fontSize: 23, lineHeight: 30, color: SOFT.ink, textAlign: 'center' },
  lead: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: COLORS.gray700, textAlign: 'center', paddingHorizontal: 6 },
  benefits: { backgroundColor: COLORS.white, borderRadius: 20, padding: 14, gap: 10, marginTop: 16 },
  benefit: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  benefitIcon: { width: 32, height: 32, borderRadius: 11, backgroundColor: 'rgba(188,38,73,0.1)', alignItems: 'center', justifyContent: 'center' },
  benefitText: { flex: 1, fontFamily: FONTS.medium, fontSize: 14, lineHeight: 19, color: SOFT.ink },
  section: { fontFamily: FONTS.bold, fontSize: 16, color: SOFT.ink, marginTop: 20, marginBottom: 10 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: 20, padding: 12, marginBottom: 10 },
  name: { fontFamily: FONTS.bold, fontSize: 15.5, color: SOFT.ink },
  meta: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 1 },
  why: { flexShrink: 1, fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted },
  count: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  following: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 8 },
  followingText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.success },
  error: { fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.danger, textAlign: 'center', marginVertical: 8 },
  empty: { fontFamily: FONTS.regular, fontSize: 14, color: SOFT.muted, textAlign: 'center', marginVertical: 14 },
  actions: { marginTop: 10, gap: 4 },
  privacy: { fontFamily: FONTS.regular, fontSize: 12, color: SOFT.muted, textAlign: 'center', marginTop: 4 },
  link: { alignItems: 'center', paddingVertical: 10 },
  linkText: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.primary },
  laterText: { fontFamily: FONTS.semibold, fontSize: 14, color: SOFT.muted },
});
