// Profile tab: your details (GET /api/v1/me), a server health check (GET /api/ping) and sign out.
// Shows loading, error and (no followed churches) empty states.
import { useCallback, useEffect, useState } from 'react';
import { Alert, Image, Linking, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import Card from '../../components/Card';
import ChurchAvatar from '../../components/ChurchAvatar';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { API_ROOT, SITE_URL, mediaUrl, ping } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { initials } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

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
          router.replace('/(auth)/login');
        },
      },
    ]);
  }

  return (
    <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !me} emptyIcon="person-outline" emptyTitle="No profile found">
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { reload(); checkServer(); }} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
      >
        <Card style={styles.identity}>
          {me?.avatar_url ? (
            <Image source={{ uri: mediaUrl(me.avatar_url) }} style={styles.avatar} accessibilityLabel={me.name} />
          ) : (
            <View style={[styles.avatar, styles.avatarFallback]}>
              <AppText style={{ fontFamily: FONTS.bold, fontSize: 26, color: COLORS.white }}>{initials(me?.name)}</AppText>
            </View>
          )}
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
            <AppText style={{ color: COLORS.gray700 }}>You are not following a church yet. Open the Churches tab to find one.</AppText>
          </Card>
        )}

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
          <Button title="Open the website" variant="outline" onPress={() => Linking.openURL(SITE_URL)} />
          <Button title="Sign out" onPress={confirmSignOut} />
        </View>
      </ScrollView>
    </StateView>
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
  server: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  dot: { width: 10, height: 10, borderRadius: 5 },
  actions: { gap: SPACING.sm, marginTop: SPACING.lg, marginBottom: SPACING.lg },
});
