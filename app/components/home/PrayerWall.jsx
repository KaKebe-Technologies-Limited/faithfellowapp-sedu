// The prayer wall on Home: the newest requests from your churches, each with a "Pray" button that tells the person someone
// prayed for them, and a button to share your own request. Sits on a soft amber band so it feels calm and different from the
// sections around it.
import { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api from '../../lib/api';
import { excerpt, timeAgo } from '../../lib/format';
import AppText from '../AppText';
import Avatar from '../Avatar';

function Request({ item, onPray }) {
  return (
    <View style={styles.request}>
      <Pressable onPress={() => router.push({ pathname: '/prayer/[id]', params: { id: item.id } })} style={({ pressed }) => pressed && { opacity: 0.9 }} accessibilityRole="button" accessibilityLabel={`Prayer request from ${item.author}`}>
      <View style={styles.requestTop}>
        <Avatar person={{ name: item.author }} size={36} />
        <View style={{ flex: 1 }}>
          <AppText style={styles.author} numberOfLines={1}>
            {item.author}
          </AppText>
          <AppText style={styles.meta} numberOfLines={1}>
            {[item.church?.name, timeAgo(item.created_at)].filter(Boolean).join(' · ')}
          </AppText>
        </View>
      </View>
      {item.title ? (
        <AppText style={styles.reqTitle} numberOfLines={1}>
          {item.title}
        </AppText>
      ) : null}
      <AppText style={styles.body} numberOfLines={3}>
        {excerpt(item.body, 170)}
      </AppText>
      </Pressable>
      <View style={styles.requestBottom}>
        <AppText style={styles.count}>
          {item.prayed_count} {item.prayed_count === 1 ? 'person has' : 'people have'} prayed
        </AppText>
        <Pressable onPress={() => onPray(item)} hitSlop={8} style={[styles.pray, item.prayed && styles.prayed]} accessibilityRole="button" accessibilityState={{ selected: !!item.prayed }} accessibilityLabel={item.prayed ? 'You prayed for this. Tap to undo' : 'I prayed for this'}>
          <Ionicons name={item.prayed ? 'heart' : 'heart-outline'} size={16} color={item.prayed ? '#fff' : '#B45309'} />
          <AppText style={[styles.prayText, item.prayed && { color: '#fff' }]}>{item.prayed ? 'Prayed' : 'I prayed'}</AppText>
        </Pressable>
      </View>
    </View>
  );
}

export default function PrayerWall({ prayers }) {
  const [items, setItems] = useState(prayers?.items ?? []);
  useEffect(() => setItems(prayers?.items ?? []), [prayers]);

  async function pray(item) {
    const on = !item.prayed;
    const change = (prayed, count) => setItems((list) => list.map((p) => (p.id === item.id ? { ...p, prayed, prayed_count: count } : p)));
    change(on, item.prayed_count + (on ? 1 : -1));
    try {
      const { data } = await api.post(`/prayer/${item.id}/pray`);
      if (typeof data?.prayed === 'boolean') change(data.prayed, data.prayed_count ?? item.prayed_count + (data.prayed ? 1 : -1));
    } catch {
      change(item.prayed, item.prayed_count);
    }
  }

  if (!prayers) return null;

  return (
    <LinearGradient colors={['#FFF6E5', '#FFEFD6', '#FAFAFA']} style={styles.band}>
      <View style={styles.head}>
        <View style={styles.headIcon}>
          <Ionicons name="hand-left" size={18} color="#B45309" />
        </View>
        <View style={{ flex: 1 }}>
          <AppText style={styles.title}>Prayer wall</AppText>
          <AppText style={styles.sub}>{prayers.open_count ? `${prayers.open_count} request${prayers.open_count === 1 ? '' : 's'} waiting for your prayers` : 'Pray together, carry one another'}</AppText>
        </View>
        <Pressable onPress={() => router.push('/prayer')} hitSlop={8} accessibilityRole="button" style={styles.all}>
          <AppText style={styles.allText}>See all</AppText>
          <Ionicons name="chevron-forward" size={14} color="#B45309" />
        </Pressable>
      </View>

      {items.map((item) => (
        <Request key={item.id} item={item} onPray={pray} />
      ))}

      <Pressable onPress={() => router.push('/prayer')} style={styles.share} accessibilityRole="button">
        <Ionicons name="add-circle-outline" size={20} color="#fff" />
        <AppText style={styles.shareText}>{items.length ? 'Share a prayer request' : 'Be the first to share a prayer request'}</AppText>
      </Pressable>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  band: { paddingTop: 20, paddingBottom: 22, marginBottom: SPACING.lg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: SPACING.md, marginBottom: 14 },
  headIcon: { width: 38, height: 38, borderRadius: 19, backgroundColor: '#FFE3B3', alignItems: 'center', justifyContent: 'center' },
  title: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black },
  sub: { fontFamily: FONTS.medium, fontSize: 12.5, color: '#8A5A12', marginTop: 1 },
  all: { flexDirection: 'row', alignItems: 'center', gap: 2 },
  allText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: '#B45309' },
  request: { marginHorizontal: SPACING.md, marginBottom: 10, backgroundColor: COLORS.white, borderRadius: 20, padding: 14, shadowColor: '#8A5A12', shadowOpacity: 0.09, shadowRadius: 10, shadowOffset: { width: 0, height: 4 }, elevation: 2 },
  requestTop: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  author: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.black },
  meta: { fontFamily: FONTS.regular, fontSize: 12, color: COLORS.gray700 },
  reqTitle: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black, marginTop: 10 },
  body: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray900, marginTop: 6 },
  requestBottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 12 },
  count: { flex: 1, fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray700 },
  pray: { flexDirection: 'row', alignItems: 'center', gap: 6, borderRadius: RADIUS.full, borderWidth: 1.5, borderColor: '#F3C98B', backgroundColor: '#FFF6E5', paddingHorizontal: 14, height: 36 },
  prayed: { backgroundColor: '#E08A1E', borderColor: '#E08A1E' },
  prayText: { fontFamily: FONTS.bold, fontSize: 13, color: '#B45309' },
  share: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginHorizontal: SPACING.md, marginTop: 4, height: 48, borderRadius: 24, backgroundColor: '#C2710C' },
  shareText: { fontFamily: FONTS.bold, fontSize: 14.5, color: '#fff' },
});
