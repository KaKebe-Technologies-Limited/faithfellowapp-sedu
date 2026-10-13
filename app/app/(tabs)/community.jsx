// Community tab: everything your church family and fellow believers do together. On top: messages and
// notifications. Then "Grow your circle" (people to say hey to), requests waiting for you, a live quiz or service
// when one is on, your study groups, and tiles for churches, events, sermons, prayer, devotionals and campaigns.
import { useCallback } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import GroupCard from '../../components/GroupCard';
import LiveBanner from '../../components/home/LiveBanner';
import { PersonTile } from '../../components/PersonCard';
import SectionHeader from '../../components/SectionHeader';
import TabTitle from '../../components/TabTitle';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { useFetch } from '../../lib/useFetch';

const TILES = [
  { icon: 'people-circle-outline', label: 'Groups', text: 'Study groups and cells', path: '/groups', colors: ['#00A896', '#00695C'] },
  { icon: 'game-controller-outline', label: 'Quiz games', text: 'Play Bible quizzes live', path: '/games', colors: ['#7C4DFF', '#4527A0'] },
  { icon: 'hand-left-outline', label: 'Prayer wall', text: 'Pray together', path: '/prayer', colors: ['#F57C00', '#BF360C'] },
  { icon: 'calendar-outline', label: 'Events', text: 'Youth, conferences, outreach', path: '/events', colors: ['#D34268', '#8E1C37'] },
  { icon: 'mic-outline', label: 'Sermons', text: 'Watch and listen', path: '/sermons', colors: ['#1E88E5', '#0D47A1'] },
  { icon: 'headset-outline', label: 'Podcasts', text: 'Faith-based, to listen to', path: '/podcasts', colors: ['#5E35B1', '#311B92'] },
  { icon: 'radio-outline', label: 'Gospel radio', text: 'Soft music, day and night', path: '/radio', colors: ['#EF6C00', '#C2185B'] },
  { icon: 'sunny-outline', label: 'Devotionals', text: 'A word for every day', path: '/devotionals', colors: ['#EC407A', '#AD1457'] },
  { icon: 'flag-outline', label: 'Campaigns', text: 'Give towards projects', path: '/campaigns', colors: ['#43A047', '#1B5E20'] },
  { icon: 'business-outline', label: 'Churches', text: 'Find and follow', path: '/churches', colors: ['#6D4C41', '#3E2723'] },
];

function Badge({ n }) {
  if (!n) return null;
  return (
    <View style={styles.badge}>
      <AppText style={styles.badgeText}>{n > 99 ? '99+' : n}</AppText>
    </View>
  );
}

function IconButton({ icon, label, count, path }) {
  return (
    <Pressable onPress={() => router.push(path)} style={styles.iconBtn} accessibilityRole="button" accessibilityLabel={count ? `${label}, ${count} new` : label}>
      <Ionicons name={icon} size={22} color={COLORS.black} />
      <Badge n={count} />
    </Pressable>
  );
}

export default function CommunityTab() {
  const me = useFetch('/me');
  const live = useFetch('/live');
  const people = useFetch('/people', { params: { scope: 'church' } });
  const groups = useFetch('/groups');
  const games = useFetch('/games');

  useFocusEffect(
    useCallback(() => {
      me.reload();
      live.reload();
      people.reload();
      groups.reload();
      games.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  const user = me.data?.data;
  const now = (live.data?.data ?? []).find((l) => l.status === 'live');
  const circle = (people.data?.data ?? []).filter((p) => p.relation === 'none').slice(0, 10);
  const mine = groups.data?.data?.mine ?? [];
  const discover = groups.data?.data?.discover ?? [];
  const onNow = games.data?.data?.live?.[0];
  const requests = user?.connection_requests ?? 0;

  return (
    <View style={styles.screen}>
      <TabTitle
        title="Community"
        subtitle="Your church family, together"
        right={
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <IconButton icon="chatbubble-ellipses-outline" label="Messages" count={user?.unread_messages} path="/chats" />
            <IconButton icon="notifications-outline" label="Notifications" count={user?.unread_notifications} path="/notifications" />
          </View>
        }
      />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={false} onRefresh={() => { me.reload(); live.reload(); people.reload(); groups.reload(); games.reload(); }} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
        {now ? <LiveBanner stream={now} /> : null}

        {onNow ? (
          <Pressable onPress={() => router.push({ pathname: '/game/[id]', params: { id: String(onNow.id) } })} accessibilityRole="button" accessibilityLabel={`Quiz on now: ${onNow.quiz}`}>
            <LinearGradient colors={['#7C4DFF', '#4527A0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.quiz}>
              <Ionicons name="game-controller" size={26} color={COLORS.white} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.quizLabel}>Quiz on now · {onNow.players_count} playing</AppText>
                <AppText style={styles.quizTitle} numberOfLines={1}>{onNow.quiz}</AppText>
              </View>
              <View style={styles.play}>
                <AppText style={styles.playText}>Play</AppText>
              </View>
            </LinearGradient>
          </Pressable>
        ) : null}

        {requests ? (
          <Pressable onPress={() => router.push('/connections')} style={styles.requests} accessibilityRole="button">
            <Ionicons name="person-add-outline" size={20} color={COLORS.primary} />
            <AppText style={styles.requestsText}>{requests} {requests === 1 ? 'person wants' : 'people want'} to connect with you</AppText>
            <Ionicons name="chevron-forward" size={16} color={COLORS.primary} />
          </Pressable>
        ) : null}

        <View style={{ marginTop: SPACING.md }}>
          <SectionHeader title="Grow your circle" actionLabel="See all" onAction={() => router.push('/people')} />
          {circle.length ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
              {circle.map((p) => (
                <PersonTile key={p.id} person={p} />
              ))}
            </ScrollView>
          ) : (
            <Pressable onPress={() => router.push('/people')} style={styles.invite} accessibilityRole="button">
              <Ionicons name="people-outline" size={26} color={COLORS.primary} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.inviteTitle}>Meet people of faith</AppText>
                <AppText variant="caption">Find believers at your churches and around the app.</AppText>
              </View>
              <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
            </Pressable>
          )}
        </View>

        {mine.length || discover.length ? (
          <View style={{ marginTop: SPACING.md }}>
            <SectionHeader title={mine.length ? 'Your groups' : 'Groups you can join'} actionLabel="See all" onAction={() => router.push('/groups')} />
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.rail}>
              {(mine.length ? mine : discover).slice(0, 8).map((g) => (
                <GroupCard key={g.id} group={g} width={250} />
              ))}
            </ScrollView>
          </View>
        ) : null}

        <View style={styles.grid}>
          {TILES.map((t) => (
            <Pressable key={t.path} onPress={() => router.push(t.path)} style={({ pressed }) => [styles.tileWrap, pressed && { opacity: 0.92 }]} accessibilityRole="button" accessibilityLabel={`${t.label}: ${t.text}`}>
              <LinearGradient colors={t.colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.tile}>
                <View style={styles.icon}>
                  <Ionicons name={t.icon} size={22} color={COLORS.white} />
                </View>
                <View>
                  <AppText style={styles.label}>{t.label}</AppText>
                  <AppText style={styles.text}>{t.text}</AppText>
                </View>
              </LinearGradient>
            </Pressable>
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  iconBtn: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center', ...SHADOW, shadowOpacity: 0.05 },
  badge: { position: 'absolute', top: -3, right: -3, minWidth: 18, height: 18, borderRadius: 9, paddingHorizontal: 4, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: COLORS.canvas },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10, color: COLORS.white },
  quiz: { flexDirection: 'row', alignItems: 'center', gap: 12, marginHorizontal: SPACING.md, marginTop: SPACING.sm, borderRadius: RADIUS.xl - 4, padding: 14 },
  quizLabel: { fontFamily: FONTS.medium, fontSize: 12, color: 'rgba(255,255,255,0.85)' },
  quizTitle: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.white },
  play: { backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 9 },
  playText: { fontFamily: FONTS.bold, fontSize: 13, color: '#4527A0' },
  requests: { flexDirection: 'row', alignItems: 'center', gap: 10, marginHorizontal: SPACING.md, marginTop: SPACING.sm, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 13 },
  requestsText: { flex: 1, fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primaryDark },
  rail: { paddingHorizontal: SPACING.md, gap: 12, paddingBottom: 8 },
  invite: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, marginHorizontal: SPACING.md, ...SHADOW, shadowOpacity: 0.05 },
  inviteTitle: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, padding: SPACING.md, marginTop: 4 },
  tileWrap: { width: '47.8%' },
  tile: { height: 118, borderRadius: RADIUS.xl - 4, padding: 13, justifyContent: 'space-between', ...SHADOW },
  icon: { width: 38, height: 38, borderRadius: 13, backgroundColor: 'rgba(255,255,255,0.2)', alignItems: 'center', justifyContent: 'center' },
  label: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.white },
  text: { fontFamily: FONTS.regular, fontSize: 11.5, lineHeight: 15, color: 'rgba(255,255,255,0.85)' },
});
