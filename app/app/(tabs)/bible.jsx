// Bible tab: search for a verse ("John 3:16"), continue where you stopped, jump to your reading plans, notes and
// highlights, choose the Bible version (ESV first, then KJV, WEB and more), and browse all 66 books in order, numbered 1 to 66
// (Genesis is 1, Revelation is 66): pick a book, then a chapter. Data: GET /api/v1/bible.
import { useCallback, useState } from 'react';
import { Alert, Modal, Pressable, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import SectionHeader from '../../components/SectionHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { HIGHLIGHTS, getLastRead } from '../../lib/bible';
import { useBibleIndex } from '../../lib/bibleOffline';

const open = (book, chapter, v) => router.push({ pathname: '/bible/[book]/[chapter]', params: { book, chapter: String(chapter), ...(v ? { v: String(v) } : {}) } });

export default function BibleTab() {
  const insets = useSafeAreaInsets();
  const { bible, loading, refreshing, error, reload, retry, offline } = useBibleIndex();
  const [testament, setTestament] = useState('OT');
  const [picked, setPicked] = useState(null);
  const [versions, setVersions] = useState(false);
  const [query, setQuery] = useState('');
  const [last, setLast] = useState(null);

  useFocusEffect(
    useCallback(() => {
      getLastRead().then(setLast);
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  function search() {
    const ref = query.trim();
    if (ref) router.push({ pathname: '/bible/passage', params: { ref } });
  }

  // Every book keeps its place in the whole Bible: Genesis is 1, Malachi 39, Matthew 40, Revelation 66.
  const numbered = (bible?.books ?? []).map((b, i) => ({ ...b, number: i + 1 }));
  const books = numbered.filter((b) => b.testament === testament);
  const current = bible?.translations?.find((t) => t.key === bible.translation);

  async function choose(key) {
    setVersions(false);
    try {
      await api.put('/bible/translation', { translation: key });
      reload();
    } catch (e) {
      Alert.alert('Could not change the version', errorMessage(e));
    }
  }

  return (
    <View style={[styles.screen, { paddingTop: insets.top }]}>
      <StateView loading={loading && !bible} error={!bible ? error : null} onRetry={retry}>
        <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />} keyboardShouldPersistTaps="handled">
          <View style={styles.head}>
            <View style={{ flex: 1 }}>
              <AppText style={styles.title}>Bible</AppText>
              <Pressable onPress={() => setVersions(true)} style={styles.version} accessibilityRole="button" accessibilityLabel={`Bible version: ${current?.name ?? ''}. Change`}>
                <AppText style={styles.versionText}>{current ? `${current.short} · ${current.name}` : 'Choose a version'}{offline ? ' · offline' : ''}</AppText>
                <Ionicons name="chevron-down" size={14} color={COLORS.primary} />
              </Pressable>
            </View>
            <Pressable onPress={() => router.push('/bible/offline')} style={styles.offlineBtn} accessibilityRole="button" accessibilityLabel="Offline Bible">
              <Ionicons name="cloud-download-outline" size={20} color={COLORS.primary} />
            </Pressable>
          </View>

          <View style={styles.search}>
            <Ionicons name="search" size={18} color={COLORS.gray700} />
            <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={query} onChangeText={setQuery} onSubmitEditing={search} returnKeyType="search" placeholder="Go to a verse — John 3:16, Psalm 23" placeholderTextColor={COLORS.gray500} style={styles.searchInput} autoCapitalize="none" />
          </View>

          {last ? (
            <Pressable onPress={() => open(last.book, last.chapter)} accessibilityRole="button" accessibilityLabel={`Continue reading ${last.bookName} ${last.chapter}`}>
              <LinearGradient colors={[COLORS.primaryLight, COLORS.primaryDark]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.continue}>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.contLabel}>Continue reading</AppText>
                  <AppText style={styles.contTitle}>{last.bookName} {last.chapter}</AppText>
                </View>
                <View style={styles.contPlay}>
                  <Ionicons name="book" size={22} color={COLORS.primary} />
                </View>
              </LinearGradient>
            </Pressable>
          ) : null}

          <View style={styles.tiles}>
            {[
              { icon: 'journal-outline', label: 'Reading plans', path: '/plans' },
              { icon: 'create-outline', label: 'My notes', path: '/notes' },
              { icon: 'color-wand-outline', label: 'Highlights', path: '/highlights' },
            ].map((t) => (
              <Pressable key={t.path} onPress={() => router.push(t.path)} style={styles.tile} accessibilityRole="button">
                <View style={styles.tileIcon}>
                  <Ionicons name={t.icon} size={22} color={COLORS.primary} />
                </View>
                <AppText style={styles.tileText}>{t.label}</AppText>
              </Pressable>
            ))}
          </View>

          {bible?.highlights?.length ? (
            <View style={{ marginTop: SPACING.md }}>
              <SectionHeader title="Your highlights" actionLabel="See all" onAction={() => router.push('/highlights')} />
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: SPACING.md, gap: 12 }}>
                {bible.highlights.map((h) => (
                  <Pressable key={h.key} onPress={() => open(h.book, h.chapter, h.verse)} style={[styles.hl, { backgroundColor: `${HIGHLIGHTS[h.color] ?? '#FFE97A'}66` }]} accessibilityRole="button">
                    <AppText style={styles.hlRef}>{h.label}</AppText>
                    {h.text ? (
                      <AppText style={styles.hlText} numberOfLines={4}>
                        {h.text}
                      </AppText>
                    ) : null}
                  </Pressable>
                ))}
              </ScrollView>
            </View>
          ) : null}

          <View style={{ marginTop: SPACING.lg }}>
            <View style={styles.seg}>
              {[
                { key: 'OT', label: 'Old Testament' },
                { key: 'NT', label: 'New Testament' },
              ].map((s) => (
                <Pressable key={s.key} onPress={() => setTestament(s.key)} style={[styles.segBtn, testament === s.key && styles.segOn]} accessibilityRole="tab" accessibilityState={{ selected: testament === s.key }}>
                  <AppText style={[styles.segText, testament === s.key && { color: COLORS.onColor }]}>{s.label}</AppText>
                </Pressable>
              ))}
            </View>
            <View style={styles.books}>
              {books.map((b, i) => (
                <Pressable key={b.code} onPress={() => (b.chapters === 1 ? open(b.code, 1) : setPicked(b))} style={[styles.book, i > 0 && styles.bookLine]} accessibilityRole="button" accessibilityLabel={`Book ${b.number}, ${b.name}, ${b.chapters} ${b.chapters === 1 ? 'chapter' : 'chapters'}`}>
                  <View style={styles.number}>
                    <AppText style={styles.numberText}>{b.number}</AppText>
                  </View>
                  <AppText style={styles.bookName} numberOfLines={1}>
                    {b.name}
                  </AppText>
                  <AppText variant="caption">{b.chapters} {b.chapters === 1 ? 'chapter' : 'chapters'}</AppText>
                  <Ionicons name="chevron-forward" size={15} color={COLORS.gray500} />
                </Pressable>
              ))}
            </View>
          </View>
        </ScrollView>
      </StateView>

      <Modal visible={versions} animationType="slide" transparent onRequestClose={() => setVersions(false)}>
        <Pressable style={styles.backdrop} onPress={() => setVersions(false)} accessibilityLabel="Close" />
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.grab} />
          <AppText style={styles.sheetTitle}>Bible version</AppText>
          <ScrollView style={{ maxHeight: 420 }}>
            {(bible?.translations ?? []).map((t) => (
              <Pressable key={t.key} onPress={() => choose(t.key)} style={styles.choice} accessibilityRole="button" accessibilityState={{ selected: t.key === bible.translation }}>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.choiceName}>{t.name}</AppText>
                  <AppText variant="caption">{t.short} · {t.language}</AppText>
                </View>
                {t.key === bible.translation ? <Ionicons name="checkmark-circle" size={22} color={COLORS.primary} /> : null}
              </Pressable>
            ))}
          </ScrollView>
        </View>
      </Modal>

      <Modal visible={!!picked} animationType="slide" transparent onRequestClose={() => setPicked(null)}>
        <Pressable style={styles.backdrop} onPress={() => setPicked(null)} accessibilityLabel="Close" />
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.grab} />
          <AppText style={styles.sheetTitle}>{picked?.name}</AppText>
          <ScrollView style={{ maxHeight: 380 }} contentContainerStyle={styles.grid}>
            {Array.from({ length: picked?.chapters ?? 0 }, (_, i) => i + 1).map((n) => (
              <Pressable key={n} onPress={() => { const code = picked.code; setPicked(null); open(code, n); }} style={styles.chapterBtn} accessibilityRole="button" accessibilityLabel={`Chapter ${n}`}>
                <AppText style={styles.chapterText}>{n}</AppText>
              </Pressable>
            ))}
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  offlineBtn: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  head: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: SPACING.md, paddingTop: 10 },
  title: { fontFamily: FONTS.display, fontSize: 30, lineHeight: 36, color: COLORS.black },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: 12, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white, ...SHADOW, shadowOpacity: 0.04 },
  searchInput: { flex: 1, minHeight: 46, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  continue: { marginHorizontal: SPACING.md, borderRadius: RADIUS.xl - 4, padding: SPACING.md, flexDirection: 'row', alignItems: 'center' },
  contLabel: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.primaryPale },
  contTitle: { fontFamily: FONTS.bold, fontSize: 22, color: COLORS.onColor },
  contPlay: { width: 46, height: 46, borderRadius: 23, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  tiles: { flexDirection: 'row', gap: 10, paddingHorizontal: SPACING.md, marginTop: 12 },
  tile: { flex: 1, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingVertical: 14, alignItems: 'center', gap: 8, ...SHADOW, shadowOpacity: 0.04 },
  tileIcon: { width: 42, height: 42, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  tileText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.black },
  hl: { width: 230, borderRadius: RADIUS.lg, padding: 14, gap: 6 },
  hlRef: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.black },
  hlText: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 19, color: COLORS.ink },
  seg: { flexDirection: 'row', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, padding: 4, marginHorizontal: SPACING.md },
  segBtn: { flex: 1, alignItems: 'center', paddingVertical: 10, borderRadius: RADIUS.full },
  segOn: { backgroundColor: COLORS.primary },
  segText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  books: { marginHorizontal: SPACING.md, marginTop: 14, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, paddingHorizontal: 12, ...SHADOW, shadowOpacity: 0.04 },
  book: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 11 },
  bookLine: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.gray300 },
  number: { width: 36, height: 36, borderRadius: 18, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  numberText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.primary },
  bookName: { flex: 1, fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  version: { flexDirection: 'row', alignItems: 'center', gap: 4, alignSelf: 'flex-start', marginTop: 4, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 5 },
  versionText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.primary },
  choice: { flexDirection: 'row', alignItems: 'center', paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: COLORS.gray300 },
  choiceName: { fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.5)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 12 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black, textAlign: 'center', marginBottom: 14 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, justifyContent: 'center' },
  chapterBtn: { width: 54, height: 46, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  chapterText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
});
