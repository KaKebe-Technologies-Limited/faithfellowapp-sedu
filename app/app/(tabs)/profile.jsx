// Profile tab: your details (GET /api/v1/me), a server health check (GET /api/ping) and sign out.
// Shows loading, error and (no followed churches) empty states.
import { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import Card from '../../components/Card';
import ChurchAvatar from '../../components/ChurchAvatar';
import StateView from '../../components/StateView';
import TabTitle from '../../components/TabTitle';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { API_ROOT, mediaUrl, ping } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { initials } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

const MENU = [
  { icon: 'notifications-outline', label: 'Notifications', path: '/notifications' },
  { icon: 'chatbubble-ellipses-outline', label: 'Messages', path: '/chats' },
  { icon: 'people-circle-outline', label: 'My connections', path: '/connections' },
  { icon: 'ticket-outline', label: 'My tickets', path: '/tickets' },
  { icon: 'create-outline', label: 'My notes', path: '/notes' },
  { icon: 'journal-outline', label: 'Reading plans', path: '/plans' },
  { icon: 'ban-outline', label: 'Blocked people', path: '/blocked' },
  { icon: 'key-outline', label: 'Change password', path: '/password' },
  { icon: 'document-text-outline', label: 'Terms of Service', path: '/legal/terms' },
  { icon: 'shield-checkmark-outline', label: 'Privacy Policy', path: '/legal/privacy' },
];

export default function ProfileScreen() {
  const { signOut } = useAuth();
  const { data, loading, refreshing, error, reload, retry } = useFetch('/me');
  const [server, setServer] = useState({ state: 'checking' });
  const me = data?.data ?? data;

  const checkServer = useCallback(async () => {
    setServer({ state: 'checking' });
    try {
      const result = await ping();
      setServer({ state: 'ok', version: result.version, ms: result.ms });
    } catch {
      setServer({ state: 'down' });
    }
  }, []);

  useEffect(() => {
    checkServer();
  }, [checkServer]);

  function confirmSignOut() {
    Alert.alert('Sign out?', 'You will need your email and password to sign in again.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign out',
        style: 'destructive',
        onPress: async () => {
          await signOut();
          router.replace('/(auth)/welcome');
        },
      },
    ]);
  }

  return (
    <View style={{ flex: 1, backgroundColor: COLORS.canvas }}>
    <TabTitle title="Me" />
    <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !me} emptyIcon="person-outline" emptyTitle="No profile found">
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { reload(); checkServer(); }} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
      >
        <Card style={styles.identity}>
          <Avatar person={me} size={84} />
          <AppText variant="title" style={{ fontSize: 22 }}>
            {me?.name}
          </AppText>
          <AppText variant="caption">{[me?.email, me?.phone].filter(Boolean).join(' · ')}</AppText>
          <View style={styles.stats}>
            <View style={styles.stat}>
              <AppText variant="heading">{me?.churches?.length ?? 0}</AppText>
              <AppText variant="caption">Churches</AppText>
            </View>
            <View style={styles.stat}>
              <AppText variant="heading">{me?.unread_notifications ?? 0}</AppText>
              <AppText variant="caption">Unread alerts</AppText>
            </View>
          </View>
          <Button title="Edit profile" variant="outline" small onPress={() => router.push('/profile/edit')} style={{ marginTop: SPACING.md }} />
        </Card>

        <AppText variant="label" style={styles.section}>
          My churches
        </AppText>
        {me?.churches?.length ? (
          <Card style={styles.list}>
            {me.churches.map((church) => (
              <View key={church.id} style={styles.church}>
                <ChurchAvatar church={church} size={36} />
                <AppText variant="body" numberOfLines={1} style={{ flex: 1 }}>
                  {church.name}
                </AppText>
              </View>
            ))}
          </Card>
        ) : (
          <Card>
            <AppText style={{ color: COLORS.gray700 }}>You are not following a church yet. Tap "Find churches" in the Community tab.</AppText>
          </Card>
        )}

        {me?.staff_of?.length ? (
          <Pressable onPress={() => router.push('/admin')} style={styles.admin} accessibilityRole="button" accessibilityLabel="Manage your church">
            <View style={styles.adminIcon}>
              <Ionicons name="shield-checkmark" size={22} color={COLORS.white} />
            </View>
            <View style={{ flex: 1 }}>
              <AppText style={styles.adminTitle}>Manage {me.staff_of.length === 1 ? me.staff_of[0].name : 'your churches'}</AppText>
              <AppText variant="caption">Announcements, events, prayer, quizzes, live and more</AppText>
            </View>
            <Ionicons name="chevron-forward" size={18} color={COLORS.primary} />
          </Pressable>
        ) : null}

        <AppText variant="label" style={styles.section}>
          My things
        </AppText>
        <Card style={styles.menu}>
          {MENU.map((item, i) => (
            <Pressable key={item.path} onPress={() => router.push(item.path)} style={[styles.menuRow, i > 0 && styles.menuBorder]} accessibilityRole="button">
              <View style={styles.menuIcon}>
                <Ionicons name={item.icon} size={18} color={COLORS.primary} />
              </View>
              <AppText style={{ flex: 1, fontFamily: FONTS.medium, color: COLORS.black }}>{item.label}</AppText>
              <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} />
            </Pressable>
          ))}
        </Card>

        <AppText variant="label" style={styles.section}>
          Connection
        </AppText>
        <Card style={styles.server}>
          <View style={[styles.dot, { backgroundColor: server.state === 'ok' ? COLORS.success : server.state === 'down' ? COLORS.danger : COLORS.warning }]} />
          <View style={{ flex: 1 }}>
            <AppText variant="label">{server.state === 'ok' ? `Connected · API v${server.version} · ${server.ms} ms` : server.state === 'down' ? 'Cannot reach the server' : 'Checking…'}</AppText>
            <AppText variant="caption" numberOfLines={1}>
              {API_ROOT}
            </AppText>
          </View>
          <Button title="Retest" variant="outline" small onPress={checkServer} />
        </Card>

        <View style={styles.actions}>
          <Button title="Sign out" onPress={confirmSignOut} />
        </View>
      </ScrollView>
    </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: SPACING.md, gap: SPACING.sm },
  identity: { alignItems: 'center', gap: 4, paddingVertical: SPACING.lg },
  avatar: { width: 84, height: 84, borderRadius: 42, marginBottom: SPACING.sm, backgroundColor: COLORS.gray100 },
  avatarFallback: { backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  stats: { flexDirection: 'row', gap: SPACING.xl, marginTop: SPACING.md },
  stat: { alignItems: 'center' },
  section: { marginTop: SPACING.md, color: COLORS.gray700, textTransform: 'uppercase', letterSpacing: 0.6 },
  list: { gap: SPACING.sm },
  church: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  admin: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg + 2, padding: 14, marginTop: SPACING.md },
  adminIcon: { width: 44, height: 44, borderRadius: 15, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  adminTitle: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.primaryDark },
  menu: { paddingVertical: 4 },
  menuRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  menuBorder: { borderTopWidth: 1, borderTopColor: COLORS.gray100 },
  menuIcon: { width: 34, height: 34, borderRadius: 11, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  server: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  dot: { width: 10, height: 10, borderRadius: 5 },
  actions: { gap: SPACING.sm, marginTop: SPACING.lg, marginBottom: SPACING.lg },
});
