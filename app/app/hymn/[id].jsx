// One hymn, made for reading: a large clear text (A- / A+ to suit your eyes), verses and the refrain set out neatly,
// a star for favourites, share to WhatsApp and the like, hands-free auto-scroll, an audio player for the tune when there is one,
// and a worship mode for the front of the church. Every hymn ends with its credits: who wrote the words and the music, the
// licence and where the text was collected. Opened hymns are remembered, so they open with no internet too.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import HymnText from '../../components/hymn/HymnText';
import { AudioPlayerCard } from '../../components/MediaPlayers';
import { BackButton, HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { MAX_SIZE, MIN_SIZE, getHymnSize, loadHymn, setHymnSize } from '../../lib/hymnStore';

const SPEEDS = [0, 0.6, 1.2, 2];

export default function HymnScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const scroll = useRef(null);
  const offsetY = useRef(0);
  const [state, setState] = useState({ loading: true, error: null, hymn: null, offline: false });
  const [size, setSize] = useState(21);
  const [speed, setSpeed] = useState(0); // index into SPEEDS; 0 = auto-scroll is off
  const [note, setNote] = useState(null);

  const load = useCallback(async () => {
    setState((s) => ({ ...s, loading: true, error: null }));
    try {
      const { hymn, offline } = await loadHymn(id);
      setState({ loading: false, error: null, hymn, offline });
    } catch (e) {
      setState({ loading: false, error: errorMessage(e, 'This hymn is not on your phone. Open it once with a connection, or save its hymnal.'), hymn: null, offline: false });
    }
  }, [id]);

  useEffect(() => {
    load();
    setSpeed(0);
    scroll.current?.scrollTo({ y: 0, animated: false });
    offsetY.current = 0;
  }, [load]);

  useEffect(() => {
    getHymnSize().then(setSize);
  }, []);

  // Auto-scroll: a gentle, steady drift; touching the page stops it.
  useEffect(() => {
    if (!speed) return undefined;
    const timer = setInterval(() => {
      offsetY.current += SPEEDS[speed];
      scroll.current?.scrollTo({ y: offsetY.current, animated: false });
    }, 40);

    return () => clearInterval(timer);
  }, [speed]);

  function resize(delta) {
    const next = Math.max(MIN_SIZE, Math.min(MAX_SIZE, size + delta));
    setSize(next);
    setHymnSize(next);
  }

  async function toggleFavorite() {
    const hymn = state.hymn;
    const on = !hymn.favorite;
    setState((s) => ({ ...s, hymn: { ...s.hymn, favorite: on } }));
    try {
      await (on ? api.post(`/hymns/${hymn.id}/favorite`) : api.delete(`/hymns/${hymn.id}/favorite`));
      setNote(on ? 'Added to your favourites.' : 'Removed from your favourites.');
    } catch (e) {
      setState((s) => ({ ...s, hymn: { ...s.hymn, favorite: !on } }));
      setNote(errorMessage(e, 'You need an internet connection to change your favourites.'));
    }
  }

  const hymn = state.hymn;
  const go = (hymnId) => hymnId && router.replace({ pathname: '/hymn/[id]', params: { id: hymnId } });

  return (
    <View style={styles.screen}>
      <StateView loading={state.loading} error={state.error} onRetry={load}>
        {hymn ? (
          <ScrollView
            ref={scroll}
            onScroll={(e) => {
              if (!speed) offsetY.current = e.nativeEvent.contentOffset.y;
            }}
            onScrollBeginDrag={() => setSpeed(0)}
            scrollEventThrottle={32}
            contentContainerStyle={{ paddingBottom: insets.bottom + 40 }}
            showsVerticalScrollIndicator={false}
          >
            <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={[styles.hero, { paddingTop: insets.top + 8 }]}>
              <View style={styles.circleA} />
              <View style={styles.circleB} />
              <View style={styles.topRow}>
                <BackButton onDark />
                <View style={{ flex: 1 }} />
                <HeaderAction onDark icon={hymn.favorite ? 'star' : 'star-outline'} label={hymn.favorite ? 'Remove from favourites' : 'Add to favourites'} onPress={toggleFavorite} />
                <HeaderAction onDark icon="share-social-outline" label="Share this hymn" onPress={() => Share.share({ message: hymn.share_text, title: hymn.title }).catch(() => {})} />
              </View>
              {hymn.number ? <AppText style={styles.number}>HYMN {hymn.number}</AppText> : null}
              <AppText style={styles.title}>{hymn.title}</AppText>
              <AppText style={styles.hymnal}>{hymn.hymnal?.name}</AppText>
              {hymn.author ? <AppText style={styles.byline}>Words: {hymn.author}</AppText> : null}
              {hymn.composer ? <AppText style={styles.byline}>Music: {hymn.composer}</AppText> : null}
            </LinearGradient>

            <View style={styles.tools}>
              <View style={styles.sizer}>
                <Pressable onPress={() => resize(-2)} disabled={size <= MIN_SIZE} hitSlop={6} accessibilityRole="button" accessibilityLabel="Smaller text" style={[styles.sizeBtn, size <= MIN_SIZE && { opacity: 0.35 }]}>
                  <AppText style={[styles.sizeText, { fontSize: 14 }]}>A</AppText>
                </Pressable>
                <AppText style={styles.sizeValue}>{size}</AppText>
                <Pressable onPress={() => resize(2)} disabled={size >= MAX_SIZE} hitSlop={6} accessibilityRole="button" accessibilityLabel="Larger text" style={[styles.sizeBtn, size >= MAX_SIZE && { opacity: 0.35 }]}>
                  <AppText style={[styles.sizeText, { fontSize: 22 }]}>A</AppText>
                </Pressable>
              </View>
              <Pressable onPress={() => setSpeed((speed + 1) % SPEEDS.length)} style={[styles.tool, speed > 0 && styles.toolOn]} accessibilityRole="button" accessibilityLabel={speed ? 'Stop auto-scroll' : 'Start auto-scroll'}>
                <Ionicons name={speed ? 'pause' : 'chevron-down-circle-outline'} size={19} color={speed ? '#fff' : COLORS.primary} />
                <AppText style={[styles.toolText, speed > 0 && { color: '#fff' }]}>{speed ? `Scroll ${speed}×` : 'Auto-scroll'}</AppText>
              </Pressable>
              <Pressable onPress={() => router.push({ pathname: '/worship/[id]', params: { id: hymn.id } })} style={[styles.tool, styles.worship]} accessibilityRole="button" accessibilityLabel="Worship mode">
                <Ionicons name="expand-outline" size={19} color="#fff" />
                <AppText style={[styles.toolText, { color: '#fff' }]}>Worship</AppText>
              </Pressable>
            </View>
            {note ? <AppText style={styles.note}>{note}</AppText> : null}
            {state.offline ? (
              <View style={styles.offline}>
                <Ionicons name="cloud-offline-outline" size={15} color={COLORS.primaryDark} />
                <AppText style={styles.offlineText}>Opened from your phone</AppText>
              </View>
            ) : null}

            {hymn.audio_url ? (
              <View style={{ paddingHorizontal: SPACING.md, marginBottom: 12 }}>
                <AppText style={styles.small}>Listen to the tune</AppText>
                <AudioPlayerCard key={hymn.id} url={hymn.audio_url} title={hymn.title} />
              </View>
            ) : null}

            <View style={styles.lyrics}>
              <HymnText sections={hymn.sections} size={size} />
            </View>

            <View style={styles.credits}>
              <View style={styles.creditsHead}>
                <Ionicons name="ribbon-outline" size={20} color={COLORS.primary} />
                <AppText style={styles.creditsTitle}>Credits</AppText>
                <View style={styles.licence}>
                  <AppText style={styles.licenceText}>{hymn.copyright_label}</AppText>
                </View>
              </View>
              {hymn.credits.map((c) => (
                <View key={c.label} style={styles.creditRow}>
                  <AppText style={styles.creditLabel}>{c.label}</AppText>
                  <AppText style={styles.creditValue}>{c.value}</AppText>
                </View>
              ))}
              {hymn.credit_note ? <AppText style={styles.creditNote}>{hymn.credit_note}</AppText> : null}
              {hymn.meter || hymn.key_signature || hymn.scripture ? (
                <View style={styles.facts}>
                  {hymn.meter ? <Fact icon="pulse-outline" text={`Metre ${hymn.meter}`} /> : null}
                  {hymn.key_signature ? <Fact icon="musical-note-outline" text={`Key of ${hymn.key_signature}`} /> : null}
                  {hymn.scripture ? <Fact icon="book-outline" text={hymn.scripture} /> : null}
                </View>
              ) : null}
              {hymn.source_url ? (
                <Pressable onPress={() => WebBrowser.openBrowserAsync(hymn.source_url)} style={styles.sourceBtn} accessibilityRole="link">
                  <Ionicons name="open-outline" size={16} color={COLORS.primary} />
                  <AppText style={styles.sourceText}>See the original source</AppText>
                </Pressable>
              ) : null}
            </View>

            <View style={styles.nav}>
              <Pressable onPress={() => go(hymn.prev_id)} disabled={!hymn.prev_id} style={[styles.navBtn, !hymn.prev_id && { opacity: 0.35 }]} accessibilityRole="button" accessibilityLabel="Previous hymn">
                <Ionicons name="chevron-back" size={20} color={COLORS.primary} />
                <AppText style={styles.navText}>Previous</AppText>
              </Pressable>
              <Pressable onPress={() => go(hymn.next_id)} disabled={!hymn.next_id} style={[styles.navBtn, !hymn.next_id && { opacity: 0.35 }]} accessibilityRole="button" accessibilityLabel="Next hymn">
                <AppText style={styles.navText}>Next</AppText>
                <Ionicons name="chevron-forward" size={20} color={COLORS.primary} />
              </Pressable>
            </View>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

function Fact({ icon, text }) {
  return (
    <View style={styles.fact}>
      <Ionicons name={icon} size={14} color={COLORS.primary} />
      <AppText style={styles.factText}>{text}</AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  hero: { paddingHorizontal: SPACING.md, paddingBottom: 26, overflow: 'hidden', borderBottomLeftRadius: 28, borderBottomRightRadius: 28 },
  circleA: { position: 'absolute', right: -50, top: -30, width: 190, height: 190, borderRadius: 95, backgroundColor: 'rgba(255,255,255,0.09)' },
  circleB: { position: 'absolute', left: -40, bottom: -60, width: 150, height: 150, borderRadius: 75, backgroundColor: 'rgba(255,255,255,0.07)' },
  topRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 14 },
  number: { fontFamily: FONTS.bold, fontSize: 12, letterSpacing: 1.6, color: 'rgba(255,255,255,0.8)' },
  title: { fontFamily: FONTS.bold, fontSize: 28, lineHeight: 35, color: '#fff', marginTop: 4 },
  hymnal: { fontFamily: FONTS.medium, fontSize: 14, color: 'rgba(255,255,255,0.85)', marginTop: 4 },
  byline: { fontFamily: FONTS.regular, fontSize: 13.5, color: 'rgba(255,255,255,0.8)', marginTop: 2 },
  tools: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: SPACING.md, marginTop: 16 },
  sizer: { flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 4, ...SHADOW, shadowOpacity: 0.05 },
  sizeBtn: { width: 38, height: 40, alignItems: 'center', justifyContent: 'center' },
  sizeText: { fontFamily: FONTS.bold, color: COLORS.primary },
  sizeValue: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.gray700, minWidth: 22, textAlign: 'center' },
  tool: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, height: 42, borderRadius: RADIUS.full, backgroundColor: COLORS.white, ...SHADOW, shadowOpacity: 0.05 },
  toolOn: { backgroundColor: COLORS.primary },
  worship: { backgroundColor: '#1F1F27' },
  toolText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  note: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.gray900, marginHorizontal: SPACING.md, marginTop: 8 },
  offline: { flexDirection: 'row', alignItems: 'center', gap: 6, marginHorizontal: SPACING.md, marginTop: 8 },
  offlineText: { fontFamily: FONTS.medium, fontSize: 12.5, color: COLORS.primaryDark },
  small: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.gray700, marginBottom: 6 },
  lyrics: { backgroundColor: COLORS.white, marginHorizontal: SPACING.md, marginTop: 16, borderRadius: 24, paddingHorizontal: 18, paddingTop: 22, paddingBottom: 4, ...SHADOW, shadowOpacity: 0.05 },
  credits: { backgroundColor: COLORS.white, marginHorizontal: SPACING.md, marginTop: 16, borderRadius: 20, padding: 16 },
  creditsHead: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8 },
  creditsTitle: { flex: 1, fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  licence: { backgroundColor: COLORS.primaryMist, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 4 },
  licenceText: { fontFamily: FONTS.semibold, fontSize: 11.5, color: COLORS.primary },
  creditRow: { flexDirection: 'row', gap: 12, paddingVertical: 7, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: COLORS.gray100 },
  creditLabel: { width: 108, fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.gray700 },
  creditValue: { flex: 1, fontFamily: FONTS.medium, fontSize: 14, lineHeight: 20, color: COLORS.black },
  creditNote: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray900, marginTop: 8 },
  facts: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12 },
  fact: { flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: COLORS.canvas, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 5 },
  factText: { fontFamily: FONTS.medium, fontSize: 12, color: COLORS.gray900 },
  sourceBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 12 },
  sourceText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  nav: { flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: SPACING.md, marginTop: 18 },
  navBtn: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 18, height: 46, ...SHADOW, shadowOpacity: 0.05 },
  navText: { fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.primary },
});
