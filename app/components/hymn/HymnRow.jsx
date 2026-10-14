// One hymn in a list: its number in a round badge, the title, the first line and who wrote it, and a star for favourites.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW } from '../../constants/theme';
import AppText from '../AppText';

export default function HymnRow({ hymn, showHymnal = false, onToggle }) {
  return (
    <View style={styles.row}>
    <Pressable
      onPress={() => router.push({ pathname: '/hymn/[id]', params: { id: hymn.id } })}
      style={({ pressed }) => [styles.main, pressed && { opacity: 0.85 }]}
      accessibilityRole="button"
      accessibilityLabel={`${hymn.number ? `Hymn ${hymn.number}, ` : ''}${hymn.title}`}
    >
      <View style={styles.number}>
        <AppText style={styles.numberText} adjustsFontSizeToFit numberOfLines={1}>
          {hymn.number ?? '♪'}
        </AppText>
      </View>
      <View style={{ flex: 1 }}>
        <AppText style={styles.title} numberOfLines={1}>
          {hymn.title}
        </AppText>
        {hymn.first_line ? (
          <AppText style={styles.line} numberOfLines={1}>
            {hymn.first_line}
          </AppText>
        ) : null}
        <AppText style={styles.meta} numberOfLines={1}>
          {[hymn.author, showHymnal ? hymn.hymnal?.name : null].filter(Boolean).join(' · ')}
        </AppText>
      </View>
    </Pressable>
      {onToggle ? (
        <Pressable onPress={() => onToggle(hymn)} hitSlop={10} accessibilityRole="button" accessibilityLabel={hymn.favorite ? 'Remove from favourites' : 'Add to favourites'} style={styles.star}>
          <Ionicons name={hymn.favorite ? 'star' : 'star-outline'} size={22} color={hymn.favorite ? '#F5B800' : COLORS.gray500} />
        </Pressable>
      ) : hymn.favorite ? (
        <Ionicons name="star" size={18} color="#F5B800" />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  main: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingVertical: 12, paddingHorizontal: 12, marginHorizontal: 16, marginBottom: 10, ...SHADOW, shadowOpacity: 0.05 },
  number: { width: 46, height: 46, borderRadius: 23, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4 },
  numberText: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary },
  title: { fontFamily: FONTS.bold, fontSize: 16.5, color: COLORS.black },
  line: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 13.5, color: COLORS.gray900, marginTop: 1 },
  meta: { fontFamily: FONTS.medium, fontSize: 12, color: COLORS.gray700, marginTop: 2 },
  star: { padding: 4 },
});
