// A row of reactions under a piece of church content (an announcement): 👍 like, ❤️ love, 😊 smile, with how many chose each. One reaction per person: pressing
// another moves yours, pressing yours again takes it back. It answers at once and is put right if the server cannot be reached.
// `reactions` is what the API sends with the item: { types: [{ key, emoji }], counts, mine }. Data: POST /react/{type}/{id}.
import { useEffect, useRef, useState } from 'react';
import { Alert, Animated, Pressable, StyleSheet, View } from 'react-native';
import { COLORS, FONTS } from '../constants/theme';
import api, { errorMessage } from '../lib/api';
import AppText from './AppText';

function Reaction({ item, count, on, onPress }) {
  const pop = useRef(new Animated.Value(1)).current;

  const press = () => {
    Animated.sequence([Animated.timing(pop, { toValue: 1.35, duration: 110, useNativeDriver: true }), Animated.spring(pop, { toValue: 1, friction: 4, useNativeDriver: true })]).start();
    onPress();
  };

  return (
    <Pressable onPress={press} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={`${item.key}${count ? `, ${count}` : ''}${on ? ', your reaction' : ''}`} style={({ pressed }) => [styles.pill, on && styles.on, pressed && { opacity: 0.85 }]}>
      <Animated.Text style={[styles.emoji, { transform: [{ scale: pop }] }]}>{item.emoji}</Animated.Text>
      <AppText style={[styles.count, on && { color: COLORS.primary }]}>{count || ''}</AppText>
    </Pressable>
  );
}

export default function ReactionBar({ type, id, reactions }) {
  const [counts, setCounts] = useState(reactions?.counts ?? {});
  const [mine, setMine] = useState(reactions?.mine ?? null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setCounts(reactions?.counts ?? {});
    setMine(reactions?.mine ?? null);
  }, [reactions]);

  if (!reactions?.types?.length) return null;

  async function press(key) {
    if (busy) return;
    const before = { counts, mine };
    const next = { ...counts };
    if (mine) next[mine] = Math.max(0, (next[mine] ?? 1) - 1);
    if (mine !== key) next[key] = (next[key] ?? 0) + 1;
    setCounts(next);
    setMine(mine === key ? null : key);
    setBusy(true);
    try {
      const { data } = await api.post(`/react/${type}/${id}`, { type: key });
      setCounts(data.counts ?? {});
      setMine(data.mine ?? null);
    } catch (e) {
      setCounts(before.counts);
      setMine(before.mine);
      Alert.alert('Could not send your reaction', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.wrap}>
      <AppText style={styles.label}>React</AppText>
      <View style={styles.row}>
        {reactions.types.map((t) => (
          <Reaction key={t.key} item={t} count={counts[t.key] ?? 0} on={mine === t.key} onPress={() => press(t.key)} />
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 18, paddingTop: 14, borderTopWidth: 1, borderTopColor: COLORS.gray100 },
  label: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.gray700, marginBottom: 10 },
  row: { flexDirection: 'row', gap: 10, flexWrap: 'wrap' },
  pill: { flexDirection: 'row', alignItems: 'center', gap: 8, minHeight: 46, paddingHorizontal: 18, borderRadius: 999, backgroundColor: '#fff', borderWidth: 1.5, borderColor: COLORS.gray100 },
  on: { backgroundColor: COLORS.primaryMist, borderColor: COLORS.primaryPale },
  emoji: { fontSize: 24, lineHeight: 30 },
  count: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.gray900, minWidth: 8 },
});
