// A passage such as "Matthew 3–5" or "John 3:16" read in place: each chapter section with its verses (your
// highlights shown), and an "Open chapter" link into the full reader. Used by search results and reading-plan days.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import { useFetch } from '../lib/useFetch';
import AppText from './AppText';
import StateView from './StateView';
import VerseList from './VerseList';

export default function PassageView({ reference, fontSize = 17 }) {
  const { data, loading, error, retry } = useFetch('/bible/passage', { params: { ref: reference }, enabled: !!reference });
  const passage = data?.data;

  return (
    <StateView loading={loading} error={error} onRetry={retry}>
      {passage ? (
        <View style={{ gap: 16 }}>
          {passage.sections.map((section) => (
            <View key={`${section.book}${section.chapter}${section.label}`}>
              <View style={styles.head}>
                <AppText style={styles.label}>{section.label}</AppText>
                <Pressable onPress={() => router.push({ pathname: '/bible/[book]/[chapter]', params: { book: section.book, chapter: String(section.chapter) } })} accessibilityRole="link" style={styles.open}>
                  <AppText style={styles.openText}>Open chapter</AppText>
                  <Ionicons name="arrow-forward" size={13} color={COLORS.primary} />
                </Pressable>
              </View>
              {section.error ? <AppText style={styles.err}>{section.error}</AppText> : <VerseList verses={section.verses} fontSize={fontSize} />}
            </View>
          ))}
          <AppText variant="caption" style={{ textAlign: 'center' }}>
            {passage.translation.toUpperCase()}
          </AppText>
        </View>
      ) : null}
    </StateView>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 },
  label: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  open: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6 },
  openText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },
  err: { fontFamily: FONTS.regular, fontSize: 14, color: COLORS.danger },
});
