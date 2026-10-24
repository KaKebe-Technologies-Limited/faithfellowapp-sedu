// People in "Grow your circle": a tall card for rails (photo, name, where they are, a "Say Hey" button) and a plain list
// row (photo, name, why we suggest them, how far away, a "Say Hey" button; no age). The button asks to connect; once asked it
// says "Requested", and once connected "Message".
import { useState } from 'react';
import { Alert, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW } from '../constants/theme';
import api, { errorMessage } from '../lib/api';
import AppText from './AppText';
import Avatar from './Avatar';

/** One place that knows how a person's relationship button behaves. */
export function useRelation(person, onChange) {
  const [relation, setRelation] = useState(person.relation);
  const [busy, setBusy] = useState(false);

  async function act() {
    if (relation === 'connected') {
      router.push({ pathname: '/chat/[id]', params: { id: String(person.id) } });
      return;
    }
    if (relation === 'pending_sent') return;
    setBusy(true);
    try {
      const { data } = await api.post(`/people/${person.id}/connect`);
      setRelation(data.data.relation);
      onChange?.(data.data.relation);
    } catch (e) {
      Alert.alert('Could not connect', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const label = { none: 'Say Hey 👋', pending_sent: 'Requested', pending_received: 'Accept', connected: 'Message' }[relation] ?? 'Say Hey 👋';
  return { relation, label, busy, act };
}

const place = (p) => p.city ?? '';

/** One line on why this person is listed: what you share, else the church you share, else what they care about. */
const reason = (p) => (p.reasons?.length ? p.reasons.slice(0, 2).join(' · ') : p.shared_churches?.length ? p.shared_churches.map((c) => c.name).join(', ') : p.interests?.length ? p.interests.slice(0, 3).join(' · ') : place(p) || 'Fellow believer');

export function PersonTile({ person, onChange }) {
  const { label, busy, act, relation } = useRelation(person, onChange);
  return (
    <Pressable onPress={() => router.push({ pathname: '/person/[id]', params: { id: String(person.id) } })} style={({ pressed }) => [styles.tile, pressed && { opacity: 0.94 }]} accessibilityLabel={`${person.name}, view profile`}>
      <Avatar person={person} size={74} />
      <AppText style={styles.name} numberOfLines={1}>
        {person.name}
      </AppText>
      <AppText variant="caption" numberOfLines={1} style={{ textAlign: 'center' }}>
        {person.distance_label ?? (place(person) || 'Fellow believer')}
      </AppText>
      <Pressable onPress={act} disabled={busy || relation === 'pending_sent'} style={[styles.hey, (relation === 'pending_sent' || relation === 'connected') && styles.heyQuiet]} accessibilityRole="button" accessibilityLabel={label}>
        <AppText style={[styles.heyText, (relation === 'pending_sent' || relation === 'connected') && { color: COLORS.primary }]}>{busy ? '…' : label}</AppText>
      </Pressable>
    </Pressable>
  );
}

export function PersonRow({ person, onChange, children }) {
  const { label, busy, act, relation } = useRelation(person, onChange);
  return (
    <Pressable onPress={() => router.push({ pathname: '/person/[id]', params: { id: String(person.id) } })} style={({ pressed }) => [styles.row, pressed && { opacity: 0.94 }]} accessibilityLabel={`${person.name}, view profile`}>
      <Avatar person={person} size={50} />
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={styles.rowName} numberOfLines={1}>
          {person.name}
        </AppText>
        <AppText variant="caption" numberOfLines={1} style={{ color: person.reasons?.length ? COLORS.primary : COLORS.gray700 }}>
          {reason(person)}
        </AppText>
        {person.distance_label ? (
          <View style={styles.shared}>
            <Ionicons name="location-outline" size={12} color={COLORS.gray700} />
            <AppText style={[styles.sharedText, { color: COLORS.gray700 }]} numberOfLines={1}>
              {person.distance_label}
            </AppText>
          </View>
        ) : null}
      </View>
      {children ?? (
        <Pressable onPress={act} disabled={busy || relation === 'pending_sent'} style={[styles.hey, styles.heyRow, (relation === 'pending_sent' || relation === 'connected') && styles.heyQuiet]} accessibilityRole="button" accessibilityLabel={label}>
          <AppText style={[styles.heyText, (relation === 'pending_sent' || relation === 'connected') && { color: COLORS.primary }]}>{busy ? '…' : label}</AppText>
        </Pressable>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  tile: { width: 148, alignItems: 'center', gap: 4, backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, paddingVertical: 16, paddingHorizontal: 10, ...SHADOW },
  name: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black, marginTop: 8, maxWidth: 128 },
  hey: { marginTop: 10, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 8 },
  heyRow: { marginTop: 0, paddingHorizontal: 14 },
  heyQuiet: { backgroundColor: COLORS.primaryMist },
  heyText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.onColor },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 16, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.gray300 },
  rowName: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  shared: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  sharedText: { flex: 1, fontFamily: FONTS.medium, fontSize: 11.5, color: COLORS.primary },
});
