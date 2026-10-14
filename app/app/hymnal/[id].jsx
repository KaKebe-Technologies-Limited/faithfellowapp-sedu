// One hymnal: its hymns in book order, grouped by the parts of the book when it has them (entrance, offertory, communion…),
// search by number, title, first line, author or words, filter by part or theme, and a button to save the whole hymnal on
// the phone so it works with no internet. The source and the people who put the book together are credited on this page.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import { useFocusEffect, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Chip from '../../components/Chip';
import HymnRow from '../../components/hymn/HymnRow';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { getSavedHymnal, removeSavedHymnal, saveHymnal, savedHymnals, withCache } from '../../lib/hymnStore';

const norm = (s) => String(s ?? '').toLowerCase();

/** A saved hymnal shaped like the list the server sends. */
function listFromSaved(h) {
  return { ...h, sections: h.sections ?? [], themes: [], hymns: h.hymns.map((x) => ({ ...x, search: [x.title, x.first_line, x.author, x.section, ...(x.sections ?? []).flatMap((s) => s.lines)].join(' ') })) };
}

export default function HymnalScreen() {
  const { id } = useLocalSearchParams();
  const hymnalId = Number(id);
  const [state, setState] = useState({ loading: true, error: null, hymnal: null, offline: false });
  const [saved, setSaved] = useState(null); // { saved_at, hymns } when this hymnal is on the phone
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState(null);
  const [text, setText] = useState('');
  const [theme, setTheme] = useState('');
  const [part, setPart] = useState('');
  const [more, setMore] = useState(false); // the book's credits (imprimatur, printer, committee) can be long: three lines until you ask

  const load = useCallback(async () => {
    const index = (await savedHymnals()).find((h) => h.id === hymnalId) ?? null;
    setSaved(index);
    try {
      const { data, offline } = await withCache(`hymnal-${hymnalId}`, async () => (await api.get(`/hymnals/${hymnalId}`)).data.data);
      setState({ loading: false, error: null, hymnal: data, offline });
    } catch (e) {
      const kept = index ? await getSavedHymnal(hymnalId) : null;
      if (kept) setState({ loading: false, error: null, hymnal: listFromSaved(kept), offline: true });
      else setState({ loading: false, error: errorMessage(e), hymnal: null, offline: false });
    }
  }, [hymnalId]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  useEffect(() => setNote(null), [hymnalId]);

  const sections = state.hymnal?.sections ?? [];

  // The list as rows: a heading each time the part of the book changes, then its hymns.
  const rows = useMemo(() => {
    const list = state.hymnal?.hymns ?? [];
    const words = norm(text).split(/\s+/).filter(Boolean);
    const shown = list.filter(
      (h) =>
        (!theme || (h.themes ?? []).includes(theme)) &&
        (!part || h.section === part) &&
        words.every((w) => norm(`${h.number} ${h.title} ${h.first_line} ${h.author} ${h.section} ${h.search ?? ''}`).includes(w) || String(h.number) === w),
    );
    if (!sections.length) return shown.map((h) => ({ key: `h${h.id}`, hymn: h }));
    const out = [];
    let current = null;
    for (const h of shown) {
      if (h.section !== current) {
        current = h.section;
        const meta = sections.find((s) => s.name === h.section);
        out.push({ key: `s${h.section}`, header: { name: h.section, meaning: meta?.meaning, count: meta?.count } });
      }
      out.push({ key: `h${h.id}`, hymn: h });
    }

    return out;
  }, [state.hymnal, sections, text, theme, part]);

  const shownCount = rows.filter((r) => r.hymn).length;

  async function toggle(hymn) {
    const on = !hymn.favorite;
    const apply = (value) => setState((s) => ({ ...s, hymnal: { ...s.hymnal, hymns: s.hymnal.hymns.map((h) => (h.id === hymn.id ? { ...h, favorite: value } : h)) } }));
    apply(on);
    try {
      await (on ? api.post(`/hymns/${hymn.id}/favorite`) : api.delete(`/hymns/${hymn.id}/favorite`));
    } catch (e) {
      apply(!on);
      setNote(errorMessage(e, 'You need an internet connection to change your favourites.'));
    }
  }

  async function save() {
    setBusy(true);
    setNote(null);
    try {
      const data = await saveHymnal(hymnalId);
      setSaved({ id: hymnalId, saved_at: new Date().toISOString(), hymns: data.hymns.length });
      setNote(`Saved ${data.hymns.length} hymns on your phone. They work with no internet.`);
    } catch (e) {
      setNote(errorMessage(e, 'Could not save the hymnal. Check your connection and try again.'));
    } finally {
      setBusy(false);
    }
  }

  async function removeSaved() {
    await removeSavedHymnal(hymnalId);
    setSaved(null);
    setNote('Removed from your phone.');
  }

  const h = state.hymnal;
  const themes = (h?.themes ?? []).slice(0, 14);

  const header = h ? (
    <View>
      <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <View style={styles.circle} />
        <AppText style={styles.heroName}>{h.name}</AppText>
        <AppText style={styles.heroMeta}>{[h.language_label, `${h.hymns_count ?? h.hymns.length} hymns`, h.denomination].filter(Boolean).join(' · ')}</AppText>
        {h.description ? (
          <Pressable onPress={() => setMore(!more)} accessibilityRole="button" accessibilityLabel={more ? 'Show less about this book' : 'Read more about this book'}>
            <AppText style={styles.heroText} numberOfLines={more ? undefined : 3}>
              {h.description}
            </AppText>
            {h.description.length > 140 ? <AppText style={styles.heroMore}>{more ? 'Show less' : 'Read more'}</AppText> : null}
          </Pressable>
        ) : null}
      </LinearGradient>

      <View style={styles.offlineCard}>
        <Ionicons name={saved ? 'checkmark-circle' : 'cloud-download-outline'} size={26} color={saved ? COLORS.success : COLORS.primary} />
        <View style={{ flex: 1 }}>
          <AppText style={styles.offlineTitle}>{saved ? 'Saved on your phone' : 'Use it with no internet'}</AppText>
          <AppText variant="caption">{saved ? 'Every hymn of this hymnal opens offline.' : 'Save the whole hymnal once, then read it anywhere.'}</AppText>
        </View>
        {busy ? (
          <ActivityIndicator color={COLORS.primary} />
        ) : saved ? (
          <Pressable onPress={removeSaved} hitSlop={8} accessibilityRole="button" accessibilityLabel="Remove from phone">
            <AppText style={styles.link}>Remove</AppText>
          </Pressable>
        ) : (
          <Pressable onPress={save} style={styles.saveBtn} accessibilityRole="button">
            <AppText style={styles.saveText}>Save</AppText>
          </Pressable>
        )}
      </View>
      {note ? <AppText style={styles.note}>{note}</AppText> : null}

      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={text} onChangeText={setText} placeholder="Find a hymn in this hymnal" placeholderTextColor={COLORS.gray500} style={styles.searchInput} autoCorrect={false} accessibilityLabel="Search this hymnal" />
        {text ? (
          <Pressable onPress={() => setText('')} hitSlop={10} accessibilityRole="button" accessibilityLabel="Clear search">
            <Ionicons name="close-circle" size={18} color={COLORS.gray500} />
          </Pressable>
        ) : null}
      </View>
      {sections.length ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
          <Chip label="All parts" on={!part} onPress={() => setPart('')} />
          {sections.map((s) => (
            <Chip key={s.name} label={s.name} on={part === s.name} onPress={() => setPart(part === s.name ? '' : s.name)} />
          ))}
        </ScrollView>
      ) : themes.length ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
          <Chip label="All" on={!theme} onPress={() => setTheme('')} />
          {themes.map((t) => (
            <Chip key={t.name} label={t.name} on={theme === t.name} onPress={() => setTheme(theme === t.name ? '' : t.name)} />
          ))}
        </ScrollView>
      ) : null}
      {state.offline ? <AppText style={styles.note}>No connection: showing what was kept on your phone.</AppText> : null}
    </View>
  ) : null;

  return (
    <View style={styles.screen}>
      <ScreenHeader title={h?.name ?? 'Hymnal'} subtitle={h ? `${shownCount} of ${h.hymns.length} hymns` : undefined} />
      <StateView loading={state.loading} error={state.error} onRetry={load} empty={!state.loading && !state.error && !h}>
        <FlatList
          data={rows}
          keyExtractor={(r) => r.key}
          ListHeaderComponent={header}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={{ paddingBottom: 48 }}
          initialNumToRender={14}
          windowSize={9}
          renderItem={({ item }) =>
            item.header ? (
              <View style={styles.partHead}>
                <AppText style={styles.partName}>{item.header.name}</AppText>
                {item.header.meaning ? <AppText style={styles.partMeaning}>{item.header.meaning}</AppText> : null}
              </View>
            ) : (
              <HymnRow hymn={item.hymn} onToggle={toggle} />
            )
          }
          ListEmptyComponent={<AppText style={styles.empty}>No hymn matches. Try the number or a word from the hymn.</AppText>}
          ListFooterComponent={
            h?.source_name ? (
              <Pressable onPress={() => h.source_url && WebBrowser.openBrowserAsync(h.source_url)} style={styles.source} accessibilityRole={h.source_url ? 'link' : 'text'}>
                <Ionicons name="ribbon-outline" size={18} color={COLORS.primary} />
                <AppText style={styles.sourceText}>Texts from {h.source_name}. Each hymn credits its author and composer.</AppText>
              </Pressable>
            ) : null
          }
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  hero: { marginHorizontal: SPACING.md, marginTop: 6, borderRadius: 22, padding: 18, overflow: 'hidden' },
  circle: { position: 'absolute', right: -40, top: -40, width: 150, height: 150, borderRadius: 75, backgroundColor: 'rgba(255,255,255,0.1)' },
  heroName: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 28, color: '#fff' },
  heroMeta: { fontFamily: FONTS.medium, fontSize: 13, color: 'rgba(255,255,255,0.85)', marginTop: 3 },
  heroText: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 19, color: 'rgba(255,255,255,0.9)', marginTop: 10 },
  heroMore: { fontFamily: FONTS.bold, fontSize: 13, color: '#fff', marginTop: 6, textDecorationLine: 'underline' },
  offlineCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: 16, padding: 14, marginHorizontal: SPACING.md, marginTop: 12 },
  offlineTitle: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
  saveBtn: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 9 },
  saveText: { fontFamily: FONTS.bold, fontSize: 13.5, color: '#fff' },
  link: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  note: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray900, marginHorizontal: SPACING.md, marginTop: 8 },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: 10, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  searchInput: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  chips: { paddingHorizontal: SPACING.md, gap: 8, paddingBottom: 12 },
  partHead: { paddingHorizontal: SPACING.md, paddingTop: 14, paddingBottom: 8 },
  partName: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  partMeaning: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.primary, marginTop: 1 },
  empty: { textAlign: 'center', color: COLORS.gray700, paddingVertical: 30, paddingHorizontal: 30 },
  source: { flexDirection: 'row', gap: 10, alignItems: 'flex-start', marginHorizontal: SPACING.md, marginTop: 14, padding: 14, borderRadius: 16, backgroundColor: COLORS.white },
  sourceText: { flex: 1, fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray900 },
});
