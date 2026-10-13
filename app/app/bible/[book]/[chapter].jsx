// The Bible reader: one chapter, every verse on its own line. Tap verses to select them, then copy, highlight in
// one of four colours, write a note, or share. Switch translation and text size, jump to any chapter, and move
// to the next or previous chapter. Your highlights and the chapter you stopped at are remembered.
import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Modal, Platform, Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import ScreenHeader, { HeaderAction } from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import VerseList from '../../../components/VerseList';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import api, { errorMessage } from '../../../lib/api';
import { HIGHLIGHTS, getFontSize, referenceLabel, setFontSize, setLastRead, shareText, verseKey } from '../../../lib/bible';
import { isOffline, useChapter } from '../../../lib/bibleOffline';
import { saveChapter } from '../../../lib/bibleStore';
import { useFetch } from '../../../lib/useFetch';

function Sheet({ visible, onClose, title, children }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose} accessibilityLabel="Close" />
      <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
        <View style={styles.grab} />
        <AppText style={styles.sheetTitle}>{title}</AppText>
        {children}
      </View>
    </Modal>
  );
}

export default function ReaderScreen() {
  const params = useLocalSearchParams();
  const book = String(params.book).toUpperCase();
  const chapter = Number(params.chapter);
  const target = useMemo(() => (params.v ? String(params.v).split('-').map(Number).filter(Boolean) : null), [params.v]);
  const insets = useSafeAreaInsets();

  const [t, setT] = useState(undefined); // undefined = the member's own translation
  const { chapterInfo, loading, error, offline, retry } = useChapter(book, chapter, t);

  const [verses, setVerses] = useState([]);
  const [selected, setSelected] = useState(new Set());
  const [fontSize, setSize] = useState(18);
  const [sheet, setSheet] = useState(null); // 'chapters' | 'translations' | 'size'
  const [busy, setBusy] = useState(false);
  const scroller = useRef(null);
  const listTop = useRef(0);
  const translations = useFetch('/bible', { enabled: sheet === 'translations' });

  useEffect(() => {
    getFontSize().then(setSize);
  }, []);

  // A new chapter or translation starts at the top; the same chapter refreshing (a copy from the phone replaced by the
  // server's answer, with your highlights) only updates the verses, so the page does not jump while you read.
  const chapterKey = chapterInfo ? `${chapterInfo.translation}/${chapterInfo.book}/${chapterInfo.chapter}` : null;
  useEffect(() => {
    if (!chapterInfo) return;
    setSelected(new Set());
    setLastRead(chapterInfo.book, chapterInfo.chapter, chapterInfo.book_name);
    scroller.current?.scrollTo({ y: 0, animated: false });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chapterKey]);

  useEffect(() => {
    if (chapterInfo) setVerses(chapterInfo.verses);
  }, [chapterInfo]);

  function toggle(v) {
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(v)) next.delete(v);
      else next.add(v);
      return next;
    });
  }

  const chosen = verses.filter((v) => selected.has(v.v));

  async function copy() {
    await Clipboard.setStringAsync(shareText({ bookName: chapterInfo.book_name, chapter, verses: chosen, translation: chapterInfo.translation }));
    setSelected(new Set());
    if (Platform.OS !== 'web') Alert.alert('Copied', `${referenceLabel(chapterInfo.book_name, chapter, chosen.map((v) => v.v))} is on your clipboard.`);
  }

  function share() {
    Share.share({ message: shareText({ bookName: chapterInfo.book_name, chapter, verses: chosen, translation: chapterInfo.translation }) }).catch(() => {});
  }

  async function highlight(color) {
    setBusy(true);
    try {
      await api.post('/bible/highlights', { verses: chosen.map((v) => verseKey(book, chapter, v.v)), color });
      const next = verses.map((v) => (selected.has(v.v) ? { ...v, color } : v));
      setVerses(next);
      saveChapter(chapterInfo.translation, book, chapter, next); // keep the colours for reading offline
      setSelected(new Set());
    } catch (e) {
      Alert.alert(isOffline(e) ? 'You are offline' : 'Could not highlight', isOffline(e) ? 'Highlighting needs a connection. You can still read, copy and share.' : errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function note() {
    const numbers = chosen.map((v) => v.v);
    router.push({
      pathname: '/note/[id]',
      params: { id: 'new', context: `bible:${book}.${chapter}`, reference: numbers.length ? referenceLabel(chapterInfo.book_name, chapter, numbers) : `${chapterInfo.book_name} ${chapter}` },
    });
  }

  async function pickTranslation(key) {
    setSheet(null);
    try {
      await api.put('/bible/translation', { translation: key });
      setT(key);
    } catch (e) {
      Alert.alert('Could not change translation', errorMessage(e));
    }
  }

  const go = (to) => to && router.replace({ pathname: '/bible/[book]/[chapter]', params: { book: to.book, chapter: String(to.chapter) } });
  const bumpSize = (delta) => {
    const next = Math.max(14, Math.min(30, fontSize + delta));
    setSize(next);
    setFontSize(next);
  };

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title={chapterInfo ? `${chapterInfo.book_name} ${chapter}` : 'Bible'}
        subtitle={chapterInfo ? chapterInfo.translation.toUpperCase() : undefined}
        right={
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <HeaderAction icon="text" label="Text size" onPress={() => setSheet('size')} />
            <HeaderAction icon="swap-horizontal" label="Change translation" onPress={() => setSheet('translations')} />
            <HeaderAction icon="grid-outline" label="Choose chapter" onPress={() => setSheet('chapters')} />
          </View>
        }
      />
      <StateView loading={loading} error={error} onRetry={retry}>
        {chapterInfo ? (
          <ScrollView ref={scroller} contentContainerStyle={{ padding: SPACING.md, paddingBottom: 140 + insets.bottom }} showsVerticalScrollIndicator={false}>
            {offline ? (
              <View style={styles.offline}>
                <Ionicons name="cloud-offline-outline" size={15} color={COLORS.primaryDark} />
                <AppText style={styles.offlineText}>Offline copy · highlights and notes need a connection</AppText>
              </View>
            ) : null}
            <AppText style={styles.hint}>Tap a verse to copy, highlight, note or share it.</AppText>
            <View onLayout={(e) => { listTop.current = e.nativeEvent.layout.y; }}>
              <VerseList verses={verses} selected={selected} onToggle={toggle} fontSize={fontSize} target={target} onTargetLayout={(y) => setTimeout(() => scroller.current?.scrollTo({ y: Math.max(0, listTop.current + y - 90), animated: true }), 150)} />
            </View>
            <View style={styles.pager}>
              <Pressable disabled={!chapterInfo.previous} onPress={() => go(chapterInfo.previous)} style={[styles.pageBtn, !chapterInfo.previous && { opacity: 0.35 }]} accessibilityRole="button">
                <Ionicons name="chevron-back" size={18} color={COLORS.primary} />
                <AppText style={styles.pageText} numberOfLines={1}>
                  {chapterInfo.previous?.label ?? ''}
                </AppText>
              </Pressable>
              <Pressable disabled={!chapterInfo.next} onPress={() => go(chapterInfo.next)} style={[styles.pageBtn, { justifyContent: 'flex-end' }, !chapterInfo.next && { opacity: 0.35 }]} accessibilityRole="button">
                <AppText style={styles.pageText} numberOfLines={1}>
                  {chapterInfo.next?.label ?? ''}
                </AppText>
                <Ionicons name="chevron-forward" size={18} color={COLORS.primary} />
              </Pressable>
            </View>
            <Button title={`Notes on ${chapterInfo.book_name} ${chapter}`} variant="outline" onPress={() => note()} style={{ marginTop: 14 }} />
          </ScrollView>
        ) : null}
      </StateView>

      {chosen.length ? (
        <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 10) + 6 }]}>
          <AppText style={styles.barTitle}>{referenceLabel(chapterInfo.book_name, chapter, chosen.map((v) => v.v))}</AppText>
          <View style={styles.barRow}>
            <View style={styles.colors}>
              {Object.entries(HIGHLIGHTS).map(([key, hex]) => (
                <Pressable key={key} disabled={busy} onPress={() => highlight(key)} accessibilityRole="button" accessibilityLabel={`Highlight ${key}`} style={[styles.swatch, { backgroundColor: hex }]} />
              ))}
              <Pressable disabled={busy} onPress={() => highlight(null)} accessibilityRole="button" accessibilityLabel="Remove highlight" style={[styles.swatch, styles.clear]}>
                <Ionicons name="close" size={14} color={COLORS.gray700} />
              </Pressable>
            </View>
            <View style={styles.actions}>
              <Pressable onPress={copy} style={styles.action} accessibilityRole="button" accessibilityLabel="Copy">
                <Ionicons name="copy-outline" size={20} color={COLORS.primary} />
                <AppText style={styles.actionText}>Copy</AppText>
              </Pressable>
              <Pressable onPress={note} style={styles.action} accessibilityRole="button" accessibilityLabel="Note">
                <Ionicons name="create-outline" size={20} color={COLORS.primary} />
                <AppText style={styles.actionText}>Note</AppText>
              </Pressable>
              <Pressable onPress={share} style={styles.action} accessibilityRole="button" accessibilityLabel="Share">
                <Ionicons name="share-social-outline" size={20} color={COLORS.primary} />
                <AppText style={styles.actionText}>Share</AppText>
              </Pressable>
              <Pressable onPress={() => setSelected(new Set())} style={styles.action} accessibilityRole="button" accessibilityLabel="Clear selection">
                <Ionicons name="close-circle-outline" size={20} color={COLORS.gray700} />
                <AppText style={[styles.actionText, { color: COLORS.gray700 }]}>Done</AppText>
              </Pressable>
            </View>
          </View>
        </View>
      ) : null}

      <Sheet visible={sheet === 'chapters'} onClose={() => setSheet(null)} title={chapterInfo?.book_name ?? 'Chapters'}>
        <ScrollView style={{ maxHeight: 360 }} contentContainerStyle={styles.grid}>
          {Array.from({ length: chapterInfo?.chapters ?? 0 }, (_, i) => i + 1).map((n) => (
            <Pressable key={n} onPress={() => { setSheet(null); if (n !== chapter) go({ book, chapter: n }); }} style={[styles.chapterBtn, n === chapter && styles.chapterOn]} accessibilityRole="button" accessibilityLabel={`Chapter ${n}`}>
              <AppText style={[styles.chapterText, n === chapter && { color: COLORS.white }]}>{n}</AppText>
            </Pressable>
          ))}
        </ScrollView>
        <Button title="All books" variant="outline" onPress={() => { setSheet(null); router.replace('/(tabs)/bible'); }} style={{ marginTop: 10 }} />
      </Sheet>

      <Sheet visible={sheet === 'translations'} onClose={() => setSheet(null)} title="Translation">
        <StateView loading={translations.loading} error={translations.error} onRetry={translations.retry}>
          <View style={{ gap: 8 }}>
            {(translations.data?.data?.translations ?? []).map((tr) => {
              const current = tr.key === (t ?? translations.data?.data?.translation);
              return (
                <Pressable key={tr.key} onPress={() => pickTranslation(tr.key)} style={[styles.tr, current && styles.trOn]} accessibilityRole="radio" accessibilityState={{ selected: current }}>
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.trName}>{tr.name}</AppText>
                    <AppText variant="caption">{tr.short} · {tr.language}</AppText>
                  </View>
                  <Ionicons name={current ? 'radio-button-on' : 'radio-button-off'} size={22} color={current ? COLORS.primary : COLORS.gray300} />
                </Pressable>
              );
            })}
          </View>
        </StateView>
      </Sheet>

      <Sheet visible={sheet === 'size'} onClose={() => setSheet(null)} title="Text size">
        <View style={styles.sizeRow}>
          <Pressable onPress={() => bumpSize(-2)} style={styles.sizeBtn} accessibilityRole="button" accessibilityLabel="Smaller text">
            <AppText style={{ fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary }}>A</AppText>
          </Pressable>
          <AppText style={{ fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black }}>{fontSize}</AppText>
          <Pressable onPress={() => bumpSize(2)} style={styles.sizeBtn} accessibilityRole="button" accessibilityLabel="Larger text">
            <AppText style={{ fontFamily: FONTS.bold, fontSize: 26, color: COLORS.primary }}>A</AppText>
          </Pressable>
        </View>
        <AppText style={{ fontFamily: FONTS.regular, fontSize, lineHeight: fontSize * 1.55, color: '#1F1F1F', marginTop: 14 }}>
          For God so loved the world, that he gave his only begotten Son.
        </AppText>
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  hint: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray700, textAlign: 'center', marginBottom: 10 },
  offline: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6, marginBottom: 10 },
  offlineText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primaryDark },
  pager: { flexDirection: 'row', gap: 12, marginTop: 22 },
  pageBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 11 },
  pageText: { flexShrink: 1, fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  bar: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 22, borderTopRightRadius: 22, paddingTop: 12, paddingHorizontal: SPACING.md, gap: 10, ...SHADOW, shadowOpacity: 0.14 },
  barTitle: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black },
  barRow: { gap: 12 },
  colors: { flexDirection: 'row', gap: 12, alignItems: 'center' },
  swatch: { width: 32, height: 32, borderRadius: 16, borderWidth: 2, borderColor: 'rgba(0,0,0,0.06)' },
  clear: { backgroundColor: COLORS.gray100, alignItems: 'center', justifyContent: 'center' },
  actions: { flexDirection: 'row', justifyContent: 'space-between' },
  action: { alignItems: 'center', gap: 2, paddingHorizontal: 8, minWidth: 64 },
  actionText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.5)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 12 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black, textAlign: 'center', marginBottom: 14 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, justifyContent: 'center' },
  chapterBtn: { width: 54, height: 46, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  chapterOn: { backgroundColor: COLORS.primary },
  chapterText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
  tr: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: RADIUS.lg, borderWidth: 1.5, borderColor: COLORS.gray100 },
  trOn: { borderColor: COLORS.primary, backgroundColor: '#FFF7FB' },
  trName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  sizeRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 30 },
  sizeBtn: { width: 56, height: 56, borderRadius: 28, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
});
