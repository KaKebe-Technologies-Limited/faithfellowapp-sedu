// A church's page, opened from a banner, a "story" tile or the directory: cover photo and badge, follow
// button, about, service times, upcoming events, recent sermons, and contact details (only shown to
// people who follow the church). Data: GET /churches/{slug}, /events?church=, /sermons?church=.
import { useState } from 'react';
import { Alert, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import Pill from '../../components/Pill';
import SectionHeader from '../../components/SectionHeader';
import Skeleton from '../../components/Skeleton';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, mediaUrl } from '../../lib/api';
import { dateTile, dayAndTime, formatDuration } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

const open = (url) => url && Linking.openURL(mediaUrl(url));

function Row({ icon, label, value, onPress }) {
  return (
    <Pressable onPress={onPress} disabled={!onPress} style={styles.contactRow} accessibilityRole={onPress ? 'link' : undefined}>
      <View style={styles.contactIcon}>
        <Ionicons name={icon} size={18} color={COLORS.primary} />
      </View>
      <View style={{ flex: 1 }}>
        <AppText variant="caption">{label}</AppText>
        <AppText style={{ fontFamily: FONTS.medium, color: COLORS.black }} numberOfLines={2}>
          {value}
        </AppText>
      </View>
      {onPress ? <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} /> : null}
    </Pressable>
  );
}

export default function ChurchScreen() {
  const { slug } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const church = useFetch(`/churches/${slug}`);
  const info = church.data?.data;
  const events = useFetch('/events', { params: { church: info?.id }, enabled: !!info?.id });
  const sermons = useFetch('/sermons', { params: { church: info?.id }, enabled: !!info?.id });
  const [busy, setBusy] = useState(false);

  const goBack = () => (router.canGoBack() ? router.back() : router.replace('/(tabs)'));

  async function toggleFollow() {
    setBusy(true);
    try {
      if (info.is_subscribed) await api.delete(`/churches/${info.slug}/subscription`);
      else await api.post(`/churches/${info.slug}/subscription`);
      await church.reload();
      events.reload();
      sermons.reload();
    } catch (e) {
      Alert.alert(info.is_subscribed ? 'Could not unfollow' : 'Could not follow', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function confirmUnfollow() {
    Alert.alert(`Unfollow ${info.name}?`, 'You will stop seeing their updates.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Unfollow', style: 'destructive', onPress: toggleFollow },
    ]);
  }

  const back = (
    <Pressable onPress={goBack} accessibilityRole="button" accessibilityLabel="Go back" style={[styles.back, { top: insets.top + 8 }]}>
      <Ionicons name="chevron-back" size={22} color={COLORS.black} />
    </Pressable>
  );

  if (church.loading || church.error || !info) {
    return (
      <View style={styles.screen}>
        {back}
        {church.loading ? (
          <View style={{ paddingTop: insets.top + 64, paddingHorizontal: SPACING.md, gap: SPACING.md }}>
            <Skeleton height={190} radius={RADIUS.xl} />
            <Skeleton width="60%" height={26} />
            <Skeleton height={80} radius={RADIUS.lg} />
          </View>
        ) : (
          <View style={{ paddingTop: insets.top + 56, flex: 1 }}>
            <StateView error={church.error || 'This church could not be found.'} onRetry={church.retry} />
          </View>
        )}
      </View>
    );
  }

  const upcoming = (events.data?.data ?? []).slice(0, 3);
  const recent = (sermons.data?.data ?? []).slice(0, 3);
  const place = [info.location?.city, info.denomination].filter(Boolean).join(' · ');

  return (
    <View style={styles.screen}>
      {back}
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingBottom: 40 }}
        refreshControl={
          <RefreshControl
            refreshing={church.refreshing}
            onRefresh={() => {
              church.reload();
              events.reload();
              sermons.reload();
            }}
            tintColor={COLORS.primary}
            colors={[COLORS.primary]}
          />
        }
      >
        <View style={styles.cover}>
          <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
          {info.cover_url ? <Image source={{ uri: mediaUrl(info.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} /> : null}
          <LinearGradient colors={['rgba(0,0,0,0.25)', 'rgba(0,0,0,0)', 'rgba(0,0,0,0.25)']} style={StyleSheet.absoluteFill} />
        </View>

        <View style={styles.head}>
          <View style={styles.badge}>
            <ChurchAvatar church={info} size={84} />
          </View>
          <View style={{ flex: 1 }} />
          <View style={{ paddingTop: 10 }}>
            {info.is_subscribed ? (
              <Button title="Following" variant="outline" small loading={busy} onPress={confirmUnfollow} />
            ) : (
              <Button title="Follow" small loading={busy} onPress={toggleFollow} />
            )}
          </View>
        </View>

        <View style={styles.body}>
          <AppText variant="title" style={{ fontSize: 24, lineHeight: 30 }}>
            {info.name}
          </AppText>
          {info.tagline ? <AppText style={{ color: COLORS.gray700, marginTop: 2 }}>{info.tagline}</AppText> : null}
          <View style={styles.facts}>
            {place ? <Pill label={place} icon="location-outline" /> : null}
            {info.pastor ? <Pill label={info.pastor} icon="person-outline" /> : null}
            {info.subscribers != null ? <Pill label={`${info.subscribers} followers`} icon="people-outline" /> : null}
          </View>

          {info.description ? (
            <View style={styles.card}>
              <AppText style={styles.cardTitle}>About</AppText>
              <AppText style={styles.text}>{info.description}</AppText>
            </View>
          ) : null}

          {info.service_times?.length ? (
            <View style={styles.card}>
              <AppText style={styles.cardTitle}>Service times</AppText>
              {info.service_times.map((s, i) => (
                <View key={`${s.name}-${i}`} style={[styles.timeRow, i > 0 && styles.timeBorder]}>
                  <View style={{ flex: 1 }}>
                    <AppText style={{ fontFamily: FONTS.semibold, color: COLORS.black }}>{s.name}</AppText>
                    <AppText variant="caption">{[s.day, s.language && s.language.toUpperCase()].filter(Boolean).join(' · ')}</AppText>
                  </View>
                  <AppText style={{ fontFamily: FONTS.medium, color: COLORS.primary, fontSize: 13 }}>{s.time}</AppText>
                </View>
              ))}
            </View>
          ) : null}
        </View>

        {upcoming.length ? (
          <View style={{ marginTop: SPACING.md }}>
            <SectionHeader title="Upcoming events" />
            {upcoming.map((e) => {
              const tile = dateTile(e.starts_at);
              return (
                <Pressable key={e.id} onPress={() => open(e.web_url)} style={[styles.card, styles.listCard]} accessibilityRole="button">
                  <View style={styles.tile}>
                    <AppText style={styles.tileMonth}>{tile.month}</AppText>
                    <AppText style={styles.tileDay}>{tile.day}</AppText>
                  </View>
                  <View style={{ flex: 1 }}>
                    <AppText style={{ fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black }} numberOfLines={2}>
                      {e.title}
                    </AppText>
                    <AppText variant="caption" numberOfLines={1}>
                      {[dayAndTime(e.starts_at), e.location?.name].filter(Boolean).join(' · ')}
                    </AppText>
                  </View>
                  <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} />
                </Pressable>
              );
            })}
          </View>
        ) : null}

        {recent.length ? (
          <View style={{ marginTop: SPACING.md }}>
            <SectionHeader title="Recent sermons" actionLabel="All sermons" onAction={() => router.push('/(tabs)/sermons')} />
            {recent.map((s) => (
              <Pressable key={s.id} onPress={() => router.push('/(tabs)/sermons')} style={[styles.card, styles.listCard]} accessibilityRole="button">
                <View style={styles.play}>
                  <Ionicons name="play" size={16} color={COLORS.white} style={{ marginLeft: 2 }} />
                </View>
                <View style={{ flex: 1 }}>
                  <AppText style={{ fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black }} numberOfLines={2}>
                    {s.title}
                  </AppText>
                  <AppText variant="caption" numberOfLines={1}>
                    {[s.preacher, s.duration_seconds ? formatDuration(s.duration_seconds) : null].filter(Boolean).join(' · ')}
                  </AppText>
                </View>
              </Pressable>
            ))}
          </View>
        ) : null}

        <View style={[styles.body, { marginTop: SPACING.md }]}>
          <View style={styles.card}>
            <AppText style={styles.cardTitle}>Contact</AppText>
            {info.contact ? (
              <>
                {info.contact.phone ? <Row icon="call-outline" label="Phone" value={info.contact.phone} onPress={() => Linking.openURL(`tel:${info.contact.phone}`)} /> : null}
                {info.contact.email ? <Row icon="mail-outline" label="Email" value={info.contact.email} onPress={() => Linking.openURL(`mailto:${info.contact.email}`)} /> : null}
                {info.contact.website ? <Row icon="globe-outline" label="Website" value={info.contact.website} onPress={() => open(info.contact.website)} /> : null}
                {info.location?.address ? <Row icon="location-outline" label="Address" value={info.location.address} /> : null}
              </>
            ) : (
              <View style={styles.locked}>
                <Ionicons name="lock-closed-outline" size={20} color={COLORS.primary} />
                <AppText style={{ flex: 1, color: COLORS.gray900 }}>Follow {info.name} to see their phone number, email and address.</AppText>
              </View>
            )}
          </View>

          <Button title="Open full page on the website" variant="outline" onPress={() => open(info.web_url)} style={{ marginTop: SPACING.md }} />
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  back: { position: 'absolute', left: SPACING.md, zIndex: 10, width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.92)', alignItems: 'center', justifyContent: 'center', ...SHADOW },
  cover: { height: 230, backgroundColor: COLORS.primaryDark },
  head: { flexDirection: 'row', paddingHorizontal: SPACING.md, marginTop: -42 },
  badge: { width: 96, height: 96, borderRadius: 28, backgroundColor: COLORS.white, borderWidth: 4, borderColor: COLORS.white, alignItems: 'center', justifyContent: 'center', ...SHADOW },
  body: { paddingHorizontal: SPACING.md, marginTop: SPACING.sm },
  facts: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: SPACING.md },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: SPACING.md, marginTop: SPACING.md, ...SHADOW },
  listCard: { flexDirection: 'row', alignItems: 'center', gap: 12, marginHorizontal: SPACING.md, marginTop: 0, marginBottom: 10 },
  cardTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black, marginBottom: 6 },
  text: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 22, color: '#2B2B2B' },
  timeRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10 },
  timeBorder: { borderTopWidth: 1, borderTopColor: COLORS.gray100 },
  tile: { width: 52, paddingVertical: 6, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 23, color: COLORS.black },
  play: { width: 40, height: 40, borderRadius: 20, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  contactRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8 },
  contactIcon: { width: 36, height: 36, borderRadius: 12, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  locked: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md },
});
