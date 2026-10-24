// Study groups, home fellowships and cells: the ones you are in, requests waiting for a leader's reply, and open
// groups at your churches you can ask to join. Filter by kind (Bible study, youth, women, men…).
// Data: GET /api/v1/groups.
import { useCallback, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import AppText from '../components/AppText';
import GroupCard from '../components/GroupCard';
import ScreenHeader from '../components/ScreenHeader';
import SectionHeader from '../components/SectionHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import { useFetch } from '../lib/useFetch';

export default function GroupsScreen() {
  const [type, setType] = useState('');
  const { data, loading, refreshing, error, reload, retry } = useFetch('/groups', { params: { type: type || undefined } });
  const g = data?.data;

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  const empty = g && !g.mine.length && !g.pending.length && !g.discover.length;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Groups" subtitle="Grow together in small groups" />
      <StateView loading={loading && !g} error={!g ? error : null} onRetry={retry}>
        {g ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
              {[{ key: '', label: 'All' }, ...g.types].map((t) => (
                <Pressable key={t.key} onPress={() => setType(t.key)} style={[styles.chip, type === t.key && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: type === t.key }}>
                  <AppText style={[styles.chipText, type === t.key && { color: COLORS.onColor }]}>{t.label}</AppText>
                </Pressable>
              ))}
            </ScrollView>

            {g.mine.length ? (
              <View style={{ marginTop: 8 }}>
                <SectionHeader title="My groups" />
                <View style={styles.list}>
                  {g.mine.map((x) => (
                    <GroupCard key={x.id} group={x} />
                  ))}
                </View>
              </View>
            ) : null}
            {g.pending.length ? (
              <View style={{ marginTop: 16 }}>
                <SectionHeader title="Waiting for approval" />
                <View style={styles.list}>
                  {g.pending.map((x) => (
                    <GroupCard key={x.id} group={x} />
                  ))}
                </View>
              </View>
            ) : null}
            {g.discover.length ? (
              <View style={{ marginTop: 16 }}>
                <SectionHeader title="Find a group" />
                <View style={styles.list}>
                  {g.discover.map((x) => (
                    <GroupCard key={x.id} group={x} />
                  ))}
                </View>
              </View>
            ) : null}
            {empty ? <StateView empty emptyIcon="people-circle-outline" emptyTitle="No groups here yet" emptyMessage="When your church opens a study group or cell, you will find it here." /> : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  chips: { gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 6 },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  list: { paddingHorizontal: SPACING.md, gap: 12 },
});
