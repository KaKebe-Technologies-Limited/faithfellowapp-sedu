// A passage such as "Matthew 3–5" or "John 3:16" read in place: each chapter section with its verses (your
// highlights shown), and an "Open chapter" link into the full reader. Used by search results and reading-plan days.
import { Platform, Pressable, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import * as Clipboard from 'expo-clipboard';
import { router } from 'expo-router';
import { shareMessage } from '../lib/activity';
import { scriptureMessage } from '../lib/bible';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import { usePassage } from '../lib/bibleOffline';
import AppText from './AppText';
import StateView from './StateView';
import VerseList from './VerseList';

export default function PassageView({ reference, fontSize = 17 }) {
  const { passage, loading, error, offline, retry } = usePassage(reference);

  return (
    <StateView loading={loading} error={error} onRetry={retry}>
      {passage ? (
        <View style={{ gap: 16 }}>
          {passage.sections.map((section) => (
            <View key={`${section.book}${section.chapter}${section.label}`}>
              <View style={styles.head}>
                <AppText style={styles.label}>{section.label}</AppText>
                {!section.error && section.verses.length ? (
                  <Pressable
                    onPress={async () => {
                      const message = scriptureMessage({ reference: section.label, translation: passage.translation, verses: section.verses });
                      await Clipboard.setStringAsync(message).catch(() => {});
                      await shareMessage(message, 'share_scripture');
                    }}
                    accessibilityRole="button"
                    accessibilityLabel={`Share ${section.label}`}
                    style={styles.open}
                  >
                    <Ionicons name="share-social-outline" size={13} color={COLORS.primary} />
                    <AppText style={styles.openText}>Share</AppText>
                  </Pressable>
                ) : null}
                <Pressable onPress={() => router.push({ pathname: '/bible/[book]/[chapter]', params: { book: section.book, chapter: String(section.chapter) } })} accessibilityRole="link" style={styles.open}>
                  <AppText style={styles.openText}>Open chapter</AppText>
                  <Ionicons name="arrow-forward" size={13} color={COLORS.primary} />
                </Pressable>
              </View>
              {section.error ? <AppText style={styles.err}>{section.error}</AppText> : <VerseList verses={section.verses} fontSize={fontSize} />}
            </View>
          ))}
          <AppText variant="caption" style={{ textAlign: 'center' }}>
            {passage.translation.toUpperCase()}{offline ? ' · offline copy' : ''}
          </AppText>
        </View>
      ) : null}
    </StateView>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6 },
  label: { flex: 1, fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  open: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6 },
  openText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },
  err: { fontFamily: FONTS.regular, fontSize: 14, color: COLORS.danger },
});
