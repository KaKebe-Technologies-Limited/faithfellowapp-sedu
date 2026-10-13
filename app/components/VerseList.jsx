// The Bible text: every verse on its own line with a small verse number, the member's highlight colour behind it,
// and tap-to-select (several verses at once). Used by the chapter reader, passages and reading-plan days.
import { Pressable, StyleSheet, View } from 'react-native';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import { HIGHLIGHTS } from '../lib/bible';
import AppText from './AppText';

export default function VerseList({ verses, selected = new Set(), onToggle, fontSize = 18, target, onTargetLayout }) {
  return (
    <View style={styles.list}>
      {verses.map((verse) => {
        const on = selected.has(verse.v);
        const isTarget = target && verse.v >= target[0] && verse.v <= (target[1] ?? target[0]);
        return (
          <Pressable
            key={verse.v}
            onLayout={target && onTargetLayout && verse.v === target[0] ? (e) => onTargetLayout(e.nativeEvent.layout.y) : undefined}
            onPress={onToggle ? () => onToggle(verse.v) : undefined}
            accessibilityRole={onToggle ? 'button' : undefined}
            accessibilityLabel={`Verse ${verse.v}. ${verse.t}`}
            accessibilityState={{ selected: on }}
            style={[styles.row, verse.color && { backgroundColor: `${HIGHLIGHTS[verse.color] ?? '#FFE97A'}AA` }, isTarget && !verse.color && styles.target, on && styles.on]}
          >
            <AppText style={[styles.num, { fontSize: Math.max(11, fontSize * 0.62), lineHeight: fontSize * 1.5 }]}>{verse.v}</AppText>
            <AppText style={[styles.text, { fontSize, lineHeight: fontSize * 1.55 }]}>{verse.t}</AppText>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  list: { gap: 2 },
  row: { flexDirection: 'row', gap: 10, paddingVertical: 6, paddingHorizontal: 10, borderRadius: RADIUS.md, borderWidth: 1.5, borderColor: 'transparent' },
  num: { width: 24, fontFamily: FONTS.bold, color: COLORS.primary, textAlign: 'right', paddingTop: 1 },
  text: { flex: 1, fontFamily: FONTS.regular, color: '#1F1F1F' },
  target: { backgroundColor: COLORS.primaryMist },
  on: { borderColor: COLORS.primary, borderStyle: 'solid' },
});
