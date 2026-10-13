// Every verse you have highlighted, newest first. Tap one to open it in the reader (GET /api/v1/bible/highlights).
import { FlatList, Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import AppText from '../components/AppText';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import { HIGHLIGHTS } from '../lib/bible';
import { useFetch } from '../lib/useFetch';

export default function HighlightsScreen() {
  const { data, loading, error, retry } = useFetch('/bible/highlights');
  const list = data?.data ?? [];

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Highlights" subtitle="Verses you marked" />
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !list.length} emptyIcon="color-wand-outline" emptyTitle="No highlights yet" emptyMessage="Open a chapter, tap a verse and choose a colour — it will be kept here." emptyAction={{ title: 'Open the Bible', onPress: () => router.replace('/(tabs)/bible') }}>
        <FlatList
          data={list}
          keyExtractor={(h) => h.key}
          contentContainerStyle={{ padding: SPACING.md, gap: 10 }}
          renderItem={({ item: h }) => (
            <Pressable onPress={() => router.push({ pathname: '/bible/[book]/[chapter]', params: { book: h.book, chapter: String(h.chapter), v: String(h.verse) } })} style={[styles.card, { borderLeftColor: HIGHLIGHTS[h.color] ?? '#FFE97A' }]} accessibilityRole="button">
              <AppText style={styles.ref}>{h.label}</AppText>
              {h.text ? <AppText style={styles.text}>{h.text}</AppText> : <AppText variant="caption">Open to read this verse</AppText>}
            </Pressable>
          )}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, borderLeftWidth: 6, gap: 4 },
  ref: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: '#2B2B2B' },
});
