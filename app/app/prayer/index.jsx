// The prayer wall: requests from your church family to pray for, answered prayers to celebrate, and your own
// requests. "Ask for prayer" opens the in-app form; each card has the "I prayed" button.
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import PagedScreen from '../../components/PagedScreen';
import PrayerCard from '../../components/PrayerCard';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';

const TABS = [
  { key: 'open', label: 'Wall' },
  { key: 'answered', label: 'Answered' },
  { key: 'mine', label: 'My requests' },
];

export default function PrayerWall() {
  const [tab, setTab] = useState('open');

  const header = (
    <View style={styles.tabs}>
      {TABS.map((t) => (
        <Pressable key={t.key} onPress={() => setTab(t.key)} style={[styles.tab, tab === t.key && styles.tabOn]} accessibilityRole="tab" accessibilityState={{ selected: tab === t.key }}>
          <AppText style={[styles.tabText, tab === t.key && { color: COLORS.white }]}>{t.label}</AppText>
        </Pressable>
      ))}
    </View>
  );

  return (
    <PagedScreen
      key={tab}
      title="Prayer"
      subtitle="Pray with your church family"
      path="/prayer"
      params={{ status: tab }}
      renderItem={(prayer) => <PrayerCard prayer={prayer} />}
      header={header}
      emptyIcon="hand-left-outline"
      emptyTitle={tab === 'mine' ? 'You have not asked for prayer yet' : tab === 'answered' ? 'No answered prayers yet' : 'No prayer requests right now'}
      emptyMessage={tab === 'mine' ? 'Share what is on your heart — your pastors and church family will pray with you.' : 'When someone shares a request, it appears here.'}
      emptyAction={tab === 'mine' ? { title: 'Ask for prayer', onPress: () => router.push('/prayer/new') } : undefined}
      right={
        <Pressable onPress={() => router.push('/prayer/new')} style={styles.add} accessibilityRole="button" accessibilityLabel="Ask for prayer">
          <Ionicons name="add" size={20} color={COLORS.white} />
          <AppText style={styles.addText}>Ask</AppText>
        </Pressable>
      }
    />
  );
}

const styles = StyleSheet.create({
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACING.md, paddingBottom: 12 },
  tab: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  tabOn: { backgroundColor: COLORS.primary },
  tabText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
});
