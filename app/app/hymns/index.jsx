// The hymn book: choose a hymnal, search every hymn by number, title, first line, author or any words, open your
// favourites and the hymns you read last. Hymnals saved on the phone work with no internet. Hymns are added by the super
// admin only, and every hymn shows who wrote its words and music.
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import Chip from '../../components/Chip';
import HymnRow from '../../components/hymn/HymnRow';
import HymnalCard from '../../components/hymn/HymnalCard';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api from '../../lib/api';
import { getHymnalList, getSavedHymnal, recentHymns, savedHymnals } from '../../lib/hymnStore';

/** Searching with no connection: look through the hymnals saved on the phone. */
async function searchSaved(q) {
  const words = q.toLowerCase().split(/\s+/).filter(Boolean);
  const found = [];
  for (const { id } of await savedHymnals()) {
    const hymnal = await getSavedHymnal(id);
    for (const h of hymnal?.hymns ?? []) {
      const hay = `${h.number} ${h.title} ${h.first_line} ${h.author} ${h.section ?? ''} ${(h.sections ?? []).flatMap((s) => s.lines).join(' ')}`.toLowerCase();
      if (words.every((w) => hay.includes(w) || String(h.number) === w)) found.push({ ...h, hymnal: { id: hymnal.id, name: hymnal.name } });
    }
  }

  return found.slice(0, 40);
}

export default function HymnsScreen() {
  const [state, setState] = useState({ loading: true, error: null, hymnals: [], favorites: 0, languages: [], offline: false });
  const [saved, setSaved] = useState([]);
  const [recent, setRecent] = useState([]);
  const [language, setLanguage] = useState('');
  const [text, setText] = useState('');
  const [query, setQuery] = useState('');
  const [results, setResults] = useState(null); // null: not searching
  const [searching, setSearching] = useState(false);

  const load = useCallback(async () => {
    try {
      const data = await getHymnalList();
      setState({ loading: false, error: null, hymnals: data.hymnals, favorites: data.favorites, languages: data.languages, offline: data.offline });
    } catch (e) {
      const keptSaved = await savedHymnals();
      setState((s) => ({ ...s, loading: false, error: keptSaved.length ? null : 'Could not load the hymn book. Check your connection and try again.', offline: true }));
    }
    setSaved(await savedHymnals());
    setRecent((await recentHymns()).slice(0, 8));
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  useEffect(() => {
    const t = setTimeout(() => setQuery(text.trim()), 350);
    return () => clearTimeout(t);
  }, [text]);

  useEffect(() => {
    if (!query) {
      setResults(null);
      return undefined;
    }
    let alive = true;
    setSearching(true);
    api
      .get('/hymns/search', { params: { q: query, language: language || undefined } })
      .then(({ data }) => alive && setResults(data.data))
      .catch(async () => alive && setResults(await searchSaved(query)))
      .finally(() => alive && setSearching(false));

    return () => {
      alive = false;
    };
  }, [query, language]);

  const savedIds = new Set(saved.map((s) => s.id));
  const hymnals = state.hymnals.filter((h) => !language || h.language === language);

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Hymn book" subtitle="Sing along, anywhere" />
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE}
          value={text}
          onChangeText={setText}
          returnKeyType="search"
          autoCorrect={false}
          placeholder="Number, title or words of a hymn"
          placeholderTextColor={COLORS.gray500}
          style={styles.searchInput}
          accessibilityLabel="Search hymns"
        />
        {text ? (
          <Pressable onPress={() => setText('')} hitSlop={10} accessibilityRole="button" accessibilityLabel="Clear search">
            <Ionicons name="close-circle" size={18} color={COLORS.gray500} />
          </Pressable>
        ) : null}
      </View>

      {results ? (
        <ScrollView contentContainerStyle={{ paddingTop: 6, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
          {searching ? <ActivityIndicator color={COLORS.primary} style={{ margin: 16 }} /> : null}
          {!searching && !results.length ? (
            <View style={styles.none}>
              <Ionicons name="musical-notes-outline" size={36} color={COLORS.gray300} />
              <AppText style={styles.noneText}>No hymn matches “{query}”.</AppText>
              <AppText variant="caption" style={{ textAlign: 'center' }}>Try the number, the title, or a few words from a verse.</AppText>
            </View>
          ) : null}
          {results.map((h) => (
            <HymnRow key={h.id} hymn={h} showHymnal />
          ))}
        </ScrollView>
      ) : (
        <StateView loading={state.loading} error={state.error} onRetry={load} empty={!state.loading && !state.error && !state.hymnals.length && !saved.length} emptyIcon="musical-notes-outline" emptyTitle="The hymn book is being prepared" emptyMessage="Hymnals will appear here soon.">
          <ScrollView contentContainerStyle={{ paddingBottom: 48 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
            {state.offline ? (
              <View style={styles.offline}>
                <Ionicons name="cloud-offline-outline" size={16} color={COLORS.primaryDark} />
                <AppText style={styles.offlineText}>No connection: showing what is saved on your phone.</AppText>
              </View>
            ) : null}

            <Pressable onPress={() => router.push('/hymns/favorites')} style={({ pressed }) => [styles.fav, pressed && { opacity: 0.93 }]} accessibilityRole="button" accessibilityLabel={`My favourite hymns, ${state.favorites}`}>
              <LinearGradient colors={['#FFF4D6', '#FFE3EF']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.favCard}>
                <View style={styles.starBubble}>
                  <Ionicons name="star" size={22} color="#F5B800" />
                </View>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.favTitle}>My favourites</AppText>
                  <AppText variant="caption">{state.favorites ? `${state.favorites} hymn${state.favorites === 1 ? '' : 's'} you love` : 'Tap the star on a hymn to keep it here'}</AppText>
                </View>
                <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
              </LinearGradient>
            </Pressable>

            {state.languages.length > 1 ? (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
                <Chip label="All languages" on={!language} onPress={() => setLanguage('')} />
                {state.languages.map((l) => (
                  <Chip key={l.key} label={l.label} on={language === l.key} onPress={() => setLanguage(l.key)} />
                ))}
              </ScrollView>
            ) : null}

            <AppText style={styles.section}>Hymnals</AppText>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: SPACING.md, paddingBottom: 14 }}>
              {hymnals.map((h) => (
                <HymnalCard key={h.id} hymnal={h} saved={savedIds.has(h.id)} />
              ))}
            </ScrollView>

            {recent.length ? (
              <>
                <AppText style={styles.section}>Recently opened</AppText>
                {recent.map((h) => (
                  <HymnRow key={h.id} hymn={h} showHymnal />
                ))}
              </>
            ) : null}

            <View style={styles.credit}>
              <Ionicons name="ribbon-outline" size={20} color={COLORS.primary} />
              <AppText style={styles.creditText}>Every hymn names who wrote its words and its music. Public-domain texts are collected from the Open Hymnal Project. Hymns are added by the FaithFellows team.</AppText>
            </View>
          </ScrollView>
        </StateView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: SPACING.sm, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 46, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  offline: { flexDirection: 'row', alignItems: 'center', gap: 8, marginHorizontal: SPACING.md, marginBottom: 10, padding: 10, borderRadius: 12, backgroundColor: COLORS.primaryMist },
  offlineText: { flex: 1, fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.primaryDark },
  fav: { marginHorizontal: SPACING.md, marginTop: 4, marginBottom: 16, ...SHADOW, shadowOpacity: 0.06 },
  favCard: { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18, padding: 14 },
  starBubble: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
  favTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  chips: { paddingHorizontal: SPACING.md, gap: 8, paddingBottom: 10 },
  section: { fontFamily: FONTS.bold, fontSize: 19, color: COLORS.black, paddingHorizontal: SPACING.md, marginBottom: 10, marginTop: 4 },
  none: { alignItems: 'center', gap: 6, paddingTop: 50, paddingHorizontal: 32 },
  noneText: { fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black, textAlign: 'center' },
  credit: { flexDirection: 'row', gap: 10, alignItems: 'flex-start', marginHorizontal: SPACING.md, marginTop: 18, padding: 14, borderRadius: 16, backgroundColor: COLORS.white },
  creditText: { flex: 1, fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray900 },
});
