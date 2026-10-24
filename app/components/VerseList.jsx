// The Bible text: every verse on its own line with a small verse number, the member's highlight colour behind it,
// and tap-to-select (several verses at once). Used by the chapter reader, passages and reading-plan days. The reader
// passes the `page` it is drawn on (Light, Sepia, Night or Rose, see lib/readerLook.js); elsewhere the app's own look is used.
import { Pressable, StyleSheet, View } from 'react-native';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import { HIGHLIGHTS, HIGHLIGHTS_LIGHT, HIGHLIGHTS_NIGHT } from '../lib/bible';
import AppText from './AppText';

export default function VerseList({ verses, selected = new Set(), onToggle, fontSize = 18, target, onTargetLayout, page }) {
  const colors = page ? (page.night ? HIGHLIGHTS_NIGHT : HIGHLIGHTS_LIGHT) : HIGHLIGHTS;

  return (
    <View style={styles.list}>
      {verses.map((verse) => {
        const on = selected.has(verse.v);
        const isTarget = target && verse.v >= target[0] && verse.v <= (target[1] ?? target[0]);
        const numberSize = Math.max(11, fontSize * 0.62);

        return (
          <Pressable
            key={verse.v}
            onLayout={target && onTargetLayout && verse.v === target[0] ? (e) => onTargetLayout(e.nativeEvent.layout.y) : undefined}
            onPress={onToggle ? () => onToggle(verse.v) : undefined}
            accessibilityRole={onToggle ? 'button' : undefined}
            accessibilityLabel={`Verse ${verse.v}. ${verse.t}`}
            accessibilityState={{ selected: on }}
            style={[
              styles.row,
              verse.color && { backgroundColor: `${colors[verse.color] ?? '#FFE97A'}AA` },
              isTarget && !verse.color && (page ? { backgroundColor: page.accentSoft } : styles.target),
              on && (page ? { borderColor: page.accent } : styles.on),
            ]}
          >
            {page?.rose ? (
              <AppText style={[styles.badge, { backgroundColor: page.accentSoft, color: page.accent }]}>{verse.v}</AppText>
            ) : (
              <AppText style={[styles.num, { fontSize: numberSize, lineHeight: fontSize * 1.5 }, page && { color: page.accent }]}>{verse.v}</AppText>
            )}
            <AppText style={[styles.text, { fontSize, lineHeight: fontSize * 1.55 }, page && { color: page.ink }]}>{verse.t}</AppText>
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
  // Rose page: the verse number sits in a small round badge.
  badge: { width: 24, height: 24, borderRadius: 12, overflow: 'hidden', textAlign: 'center', fontFamily: FONTS.bold, fontSize: 11.5, lineHeight: 24, marginTop: 4 },
  text: { flex: 1, fontFamily: FONTS.regular, color: COLORS.ink },
  target: { backgroundColor: COLORS.primaryMist },
  on: { borderColor: COLORS.primary, borderStyle: 'solid' },
});
