// Your conversations: each person you have messaged, the last message and how many are unread.
// Data: GET /api/v1/messages. Start a new chat from someone's profile (you must be connected).
import { useCallback } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../components/AppText';
import Avatar from '../components/Avatar';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import { excerpt, timeAgo } from '../lib/format';
import { useFetch } from '../lib/useFetch';

export default function ChatsScreen() {
  const { data, loading, refreshing, error, reload, retry } = useFetch('/messages');
  const chats = data?.data ?? [];

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Messages" subtitle="Private chats with your connections" />
      <StateView loading={loading && !data} error={!data ? error : null} onRetry={retry} empty={!!data && chats.length === 0} emptyIcon="chatbubbles-outline" emptyTitle="No messages yet" emptyMessage="Connect with someone, then say hello." emptyAction={{ title: 'My connections', onPress: () => router.replace('/connections') }}>
        <FlatList
          data={chats}
          keyExtractor={(c) => String(c.user.id)}
          contentContainerStyle={{ paddingBottom: 24 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          renderItem={({ item: c }) => (
            <Pressable onPress={() => router.push({ pathname: '/chat/[id]', params: { id: String(c.user.id) } })} style={styles.row} accessibilityRole="button" accessibilityLabel={`Chat with ${c.user.name}${c.unread ? `, ${c.unread} unread` : ''}`}>
              <Avatar person={c.user} size={52} />
              <View style={{ flex: 1, gap: 2 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                  <AppText style={styles.name} numberOfLines={1}>
                    {c.user.name}
                  </AppText>
                  <AppText variant="caption">{timeAgo(c.last.at)}</AppText>
                </View>
                <AppText style={[styles.last, c.unread > 0 && { fontFamily: FONTS.semibold, color: COLORS.black }]} numberOfLines={1}>
                  {c.last.mine ? 'You: ' : ''}
                  {excerpt(c.last.body, 80)}
                </AppText>
              </View>
              {c.unread > 0 ? (
                <View style={styles.badge}>
                  <AppText style={styles.badgeText}>{c.unread}</AppText>
                </View>
              ) : null}
            </Pressable>
          )}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: SPACING.md, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: COLORS.gray100, backgroundColor: COLORS.white },
  name: { flex: 1, fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  last: { fontFamily: FONTS.regular, fontSize: 14, color: COLORS.gray700 },
  badge: { minWidth: 22, height: 22, borderRadius: 11, paddingHorizontal: 6, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  badgeText: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.white },
});
