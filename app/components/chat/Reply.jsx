// The two pieces of "reply to a message": the quote of the message you answer, shown inside the answer's bubble, and the bar above the box you type in
// while you are writing a reply (with a cross to cancel).
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import AppText from '../AppText';

/** The quote inside a bubble. `onMine` is true when the bubble itself is one of your own (drawn on the brand colour). */
export function ReplyQuote({ name, text, onMine = false }) {
  return (
    <View style={[styles.quote, onMine ? styles.quoteMine : styles.quoteTheirs]}>
      <AppText style={[styles.quoteName, onMine && { color: COLORS.onColor }]} numberOfLines={1}>
        {name}
      </AppText>
      <AppText style={[styles.quoteText, onMine && { color: 'rgba(255,255,255,0.88)' }]} numberOfLines={2}>
        {text}
      </AppText>
    </View>
  );
}

/** "Replying to Ben" above the box you type in. */
export function ReplyBar({ name, text, onClose }) {
  return (
    <View style={styles.bar}>
      <View style={styles.barLine} />
      <View style={{ flex: 1 }}>
        <AppText style={styles.barName} numberOfLines={1}>
          Replying to {name}
        </AppText>
        <AppText style={styles.barText} numberOfLines={1}>
          {text}
        </AppText>
      </View>
      <Pressable onPress={onClose} hitSlop={10} accessibilityRole="button" accessibilityLabel="Cancel the reply">
        <Ionicons name="close-circle" size={22} color={COLORS.gray500} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  quote: { borderLeftWidth: 3, borderRadius: 8, paddingVertical: 5, paddingHorizontal: 8, marginBottom: 6 },
  quoteMine: { backgroundColor: 'rgba(255,255,255,0.18)', borderLeftColor: 'rgba(255,255,255,0.85)' },
  quoteTheirs: { backgroundColor: COLORS.gray100, borderLeftColor: COLORS.primary },
  quoteName: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.primary },
  quoteText: { fontFamily: FONTS.regular, fontSize: 13, lineHeight: 17, color: COLORS.gray700, marginTop: 1 },
  bar: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.canvas, borderRadius: RADIUS.md, paddingVertical: 8, paddingHorizontal: 10, marginBottom: 8 },
  barLine: { width: 3, alignSelf: 'stretch', borderRadius: 2, backgroundColor: COLORS.primary },
  barName: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary },
  barText: { fontFamily: FONTS.regular, fontSize: 13, color: COLORS.gray700, marginTop: 1 },
});
