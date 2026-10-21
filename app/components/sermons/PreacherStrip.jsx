// "Preachers": a row of round badges, one for each person who preaches in the churches you follow, with how many sermons they have. Tap one to see
// only their sermons; tap again to see everyone. (The badge shows their initials on a colour of their own: a photo can be added later.)
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import AppText from '../AppText';
import { colorFor } from '../Avatar';

const TITLES = /^(pastor|pst|rev|reverend|fr|father|bishop|archbishop|apostle|prophet|prophetess|dr|doctor|elder|evangelist|minister|sr|sister|br|brother|canon|deacon|mr|mrs|ms|prof|professor|ps|ptr|ven)\.?$/i;

/** "PO" for "Pastor Okello": the titles are left out. */
export function preacherInitials(name = '') {
  const words = String(name).split(/\s+/).filter(Boolean);
  const real = words.filter((w) => !TITLES.test(w));
  const use = (real.length ? real : words).slice(0, 2);

  return use.map((w) => w[0].toUpperCase()).join('') || '?';
}

function Badge({ preacher, on, onPress }) {
  return (
    <Pressable onPress={onPress} style={styles.item} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={`${preacher.name}, ${preacher.sermons} ${preacher.sermons === 1 ? 'sermon' : 'sermons'}`}>
      <View style={[styles.ring, on && styles.ringOn]}>
        <View style={[styles.circle, { backgroundColor: colorFor(preacher.name) }]}>
          <AppText allowFontScaling={false} style={styles.initials}>
            {preacherInitials(preacher.name)}
          </AppText>
        </View>
        {on ? (
          <View style={styles.check}>
            <Ionicons name="checkmark" size={12} color="#fff" />
          </View>
        ) : null}
      </View>
      <AppText style={[styles.name, on && { color: COLORS.primary }]} numberOfLines={2}>
        {preacher.name}
      </AppText>
      <AppText style={styles.count}>
        {preacher.sermons} {preacher.sermons === 1 ? 'sermon' : 'sermons'}
      </AppText>
    </Pressable>
  );
}

/** `preachers`: [{ name, sermons }]; `selected`: the name chosen, or null; `onSelect(name | null)`. */
export default function PreacherStrip({ preachers, selected, onSelect }) {
  if (!preachers?.length) return null;

  return (
    <FlatList
      data={preachers}
      horizontal
      showsHorizontalScrollIndicator={false}
      keyExtractor={(p) => p.name}
      contentContainerStyle={styles.list}
      renderItem={({ item }) => <Badge preacher={item} on={selected === item.name} onPress={() => onSelect(selected === item.name ? null : item.name)} />}
    />
  );
}

const styles = StyleSheet.create({
  list: { paddingHorizontal: SPACING.md, gap: 14 },
  item: { width: 84, alignItems: 'center' },
  ring: { width: 74, height: 74, borderRadius: 37, alignItems: 'center', justifyContent: 'center', borderWidth: 2.5, borderColor: COLORS.primaryPale },
  ringOn: { borderColor: COLORS.primary },
  circle: { width: 62, height: 62, borderRadius: 31, alignItems: 'center', justifyContent: 'center' },
  initials: { fontFamily: FONTS.bold, fontSize: 21, color: '#fff' },
  check: { position: 'absolute', right: -1, bottom: -1, width: 22, height: 22, borderRadius: 11, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: '#fff' },
  name: { fontFamily: FONTS.semibold, fontSize: 12.5, lineHeight: 16, color: COLORS.black, textAlign: 'center', marginTop: 7 },
  count: { fontFamily: FONTS.regular, fontSize: 11, color: COLORS.gray700, marginTop: 1 },
});
