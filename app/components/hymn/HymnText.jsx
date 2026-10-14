// The words of a hymn, laid out to be read from an arm's length: a small numbered badge for each verse, a generous line
// height, and the refrain set apart on a tinted card with an accent bar. `size` is the text size the member chose.
import { StyleSheet, View } from 'react-native';
import { COLORS, FONTS } from '../../constants/theme';
import AppText from '../AppText';

export default function HymnText({ sections = [], size = 22, dark = false }) {
  const ink = dark ? '#F4F1EC' : COLORS.black;
  const soft = dark ? 'rgba(255,255,255,0.55)' : COLORS.primary;
  const line = Math.round(size * 1.45);
  const gap = Math.round(size * 0.3); // a clear step between two lines of the poem, so a wrapped line is not mistaken for a new one

  return (
    <View>
      {sections.map((s, i) =>
        s.type === 'refrain' ? (
          <View key={i} style={[styles.refrain, dark && styles.refrainDark]}>
            <AppText style={[styles.refrainLabel, { color: soft }]}>{(s.label || 'Refrain').toUpperCase()}</AppText>
            {s.lines.map((l, n) => (
              <AppText key={n} style={{ fontFamily: FONTS.medium, fontStyle: 'italic', fontSize: size, lineHeight: line, color: ink, marginBottom: gap }}>
                {l}
              </AppText>
            ))}
          </View>
        ) : (
          <View key={i} style={styles.verse}>
            <View style={[styles.badge, dark && styles.badgeDark]}>
              <AppText style={[styles.badgeText, { color: soft }]}>{s.label}</AppText>
            </View>
            <View style={{ flex: 1 }}>
              {s.lines.map((l, n) => (
                <AppText key={n} style={{ fontFamily: FONTS.regular, fontSize: size, lineHeight: line, color: ink, marginBottom: gap }}>
                  {l}
                </AppText>
              ))}
            </View>
          </View>
        ),
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  verse: { flexDirection: 'row', gap: 14, marginBottom: 22 },
  badge: { width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', marginTop: 3 },
  badgeDark: { backgroundColor: 'rgba(255,255,255,0.1)' },
  badgeText: { fontFamily: FONTS.bold, fontSize: 13, lineHeight: 16 },
  refrain: { marginLeft: 42, marginBottom: 22, paddingVertical: 12, paddingHorizontal: 16, borderLeftWidth: 4, borderLeftColor: COLORS.primarySoft, backgroundColor: COLORS.primaryMist, borderRadius: 12 },
  refrainDark: { backgroundColor: 'rgba(255,255,255,0.07)', borderLeftColor: '#FF7A9A' },
  refrainLabel: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.2, marginBottom: 4 },
});
