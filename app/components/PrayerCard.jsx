// A prayer request on the prayer wall: who asked (or "Anonymous"), the request, status chips and the big
// "I prayed" button that counts everyone who prayed. Tapping the card opens the full request.
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import api from '../lib/api';
import { excerpt, initials, timeAgo } from '../lib/format';
import AppText from './AppText';
import Pill from './Pill';

export function PrayedButton({ prayer, onChange }) {
  const [prayed, setPrayed] = useState(!!prayer.prayed);
  const [count, setCount] = useState(prayer.prayed_count ?? 0);

  async function toggle() {
    const next = !prayed;
    setPrayed(next);
    setCount((c) => Math.max(0, c + (next ? 1 : -1)));
    try {
      const { data } = await api.post(`/prayer/${prayer.id}/pray`);
      setPrayed(!!data.prayed);
      setCount(data.count);
      onChange?.(data);
    } catch {
      setPrayed(!next);
      setCount((c) => Math.max(0, c + (next ? -1 : 1)));
    }
  }

  return (
    <Pressable onPress={toggle} style={[styles.prayed, prayed && styles.prayedOn]} accessibilityRole="button" accessibilityState={{ selected: prayed }} accessibilityLabel={`I prayed, ${count}`}>
      <AppText style={{ fontSize: 15 }}>🙏</AppText>
      <AppText style={[styles.prayedText, prayed && { color: COLORS.white }]}>{prayed ? 'You prayed' : 'I prayed'} · {count}</AppText>
    </Pressable>
  );
}

const STATUS = {
  pending: { label: 'Waiting for approval', tone: 'urgent' },
  declined: { label: 'Not shared', tone: 'urgent' },
  answered: { label: 'Answered ✓', tone: 'soft' },
};

export default function PrayerCard({ prayer }) {
  const status = STATUS[prayer.status];
  const open = () => router.push({ pathname: '/prayer/[id]', params: { id: prayer.id } });

  return (
    <Pressable onPress={open} style={({ pressed }) => [styles.card, pressed && { opacity: 0.94 }]} accessibilityLabel={`Prayer request: ${prayer.title || excerpt(prayer.body, 40)}`}>
      <View style={styles.head}>
        <View style={styles.person}>
          <AppText style={styles.initial}>{prayer.author === 'Anonymous' ? '🙏' : initials(prayer.author)}</AppText>
        </View>
        <View style={{ flex: 1 }}>
          <AppText style={styles.name}>{prayer.is_mine ? 'You' : prayer.author}</AppText>
          <AppText variant="caption" numberOfLines={1}>
            {[prayer.church?.name, timeAgo(prayer.created_at)].filter(Boolean).join(' · ')}
          </AppText>
        </View>
        {status ? <Pill label={status.label} tone={status.tone} /> : null}
      </View>
      {prayer.title ? <AppText style={styles.title}>{prayer.title}</AppText> : null}
      <AppText style={styles.body}>{excerpt(prayer.body, 220)}</AppText>
      {prayer.status === 'answered' && prayer.answer_note ? (
        <View style={styles.answer}>
          <AppText style={styles.answerText}>{excerpt(prayer.answer_note, 140)}</AppText>
        </View>
      ) : null}
      {prayer.status === 'open' || prayer.status === 'answered' ? (
        <View style={{ marginTop: 12, flexDirection: 'row' }}>
          <PrayedButton prayer={prayer} />
        </View>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: SPACING.md, marginHorizontal: SPACING.md, marginBottom: 12, borderTopWidth: 4, borderTopColor: COLORS.primaryPale, ...SHADOW },
  head: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  person: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primaryLight, alignItems: 'center', justifyContent: 'center' },
  initial: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.white },
  name: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black },
  title: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 22, color: COLORS.black, marginTop: 12 },
  body: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 23, color: '#2B2B2B', marginTop: 8 },
  answer: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: 10, marginTop: 10 },
  answerText: { fontFamily: FONTS.medium, fontSize: 13.5, lineHeight: 19, color: COLORS.primaryDark },
  prayed: { flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 9 },
  prayedOn: { backgroundColor: COLORS.primary },
  prayedText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.primary },
});
