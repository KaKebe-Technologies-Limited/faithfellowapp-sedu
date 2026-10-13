// Community tab: everything your church family does together, in one place — your churches, events, sermons,
// the prayer wall, devotionals, giving campaigns and live services. A "live now" banner sits on top when a
// church you follow is streaming.
import { ScrollView, StyleSheet, View } from 'react-native';
import { Pressable } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { useCallback } from 'react';
import AppText from '../../components/AppText';
import LiveBanner from '../../components/home/LiveBanner';
import TabTitle from '../../components/TabTitle';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { useFetch } from '../../lib/useFetch';

const TILES = [
  { icon: 'business-outline', label: 'Churches', text: 'Find and follow', path: '/churches', colors: ['#D34268', '#8E1C37'] },
  { icon: 'calendar-outline', label: 'Events', text: 'Youth, conferences, outreach', path: '/events', colors: ['#7C4DFF', '#4527A0'] },
  { icon: 'mic-outline', label: 'Sermons', text: 'Watch and listen', path: '/sermons', colors: ['#00A896', '#00695C'] },
  { icon: 'hand-left-outline', label: 'Prayer wall', text: 'Pray together', path: '/prayer', colors: ['#F57C00', '#BF360C'] },
  { icon: 'sunny-outline', label: 'Devotionals', text: 'A word for every day', path: '/devotionals', colors: ['#EC407A', '#AD1457'] },
  { icon: 'flag-outline', label: 'Campaigns', text: 'Give towards projects', path: '/campaigns', colors: ['#1E88E5', '#0D47A1'] },
];

export default function CommunityTab() {
  const live = useFetch('/live');
  const now = (live.data?.data ?? []).find((l) => l.status === 'live');

  useFocusEffect(
    useCallback(() => {
      live.reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  return (
    <View style={styles.screen}>
      <TabTitle title="Community" subtitle="Your church family, together" />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false}>
        {now ? <LiveBanner stream={now} /> : null}
        <View style={styles.grid}>
          {TILES.map((t) => (
            <Pressable key={t.path} onPress={() => router.push(t.path)} style={({ pressed }) => [styles.tileWrap, pressed && { opacity: 0.92 }]} accessibilityRole="button" accessibilityLabel={`${t.label}: ${t.text}`}>
              <LinearGradient colors={t.colors} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.tile}>
                <View style={styles.icon}>
                  <Ionicons name={t.icon} size={24} color={COLORS.white} />
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
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, padding: SPACING.md },
  tileWrap: { width: '47.8%' },
  tile: { height: 138, borderRadius: RADIUS.xl - 4, padding: 14, justifyContent: 'space-between', ...SHADOW },
  icon: { width: 42, height: 42, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.2)', alignItems: 'center', justifyContent: 'center' },
  label: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.white },
  text: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 16, color: 'rgba(255,255,255,0.85)' },
});
