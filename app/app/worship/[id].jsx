// Worship mode: one verse (or the refrain) at a time, in very large type, edge to edge, for the front of the church or a
// candle-lit evening. Tap the right side or press Next for the next verse, the left side to go back; light and dark themes;
// the screen stays awake. At the end it offers the next hymn.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useKeepAwake } from 'expo-keep-awake';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import StateView from '../../components/StateView';
import { FONTS } from '../../constants/theme';
import { errorMessage } from '../../lib/api';
import { getStored, setStored } from '../../lib/storage';
import { loadHymn } from '../../lib/hymnStore';

function Round({ icon, label, onPress, color }) {
  return (
    <Pressable onPress={onPress} hitSlop={8} accessibilityRole="button" accessibilityLabel={label} style={[styles.round, { backgroundColor: color }]}>
      <Ionicons name={icon} size={22} color="#fff" />
    </Pressable>
  );
}

export default function WorshipScreen() {
  useKeepAwake();
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [state, setState] = useState({ loading: true, error: null, hymn: null });
  const [at, setAt] = useState(0);
  const [dark, setDark] = useState(true);
  const [size, setSize] = useState(40);
  const [box, setBox] = useState({ w: 320, h: 400 });

  const load = useCallback(async () => {
    try {
      const { hymn } = await loadHymn(id);
      setState({ loading: false, error: null, hymn });
      setAt(0);
    } catch (e) {
      setState({ loading: false, error: errorMessage(e), hymn: null });
    }
  }, [id]);

  useEffect(() => {
    load();
    getStored('worship_dark', true).then(setDark);
    getStored('worship_size', 40).then(setSize);
  }, [load]);

  const sections = useMemo(() => state.hymn?.sections ?? [], [state.hymn]);
  const last = sections.length - 1;
  const section = sections[Math.min(at, last)];
  const done = at > last;

  const palette = dark ? { bg: '#0D0D11', ink: '#F7F3EA', soft: 'rgba(255,255,255,0.5)', chip: 'rgba(255,255,255,0.14)', accent: '#FF8AA0' } : { bg: '#FFFDF7', ink: '#1A1A1A', soft: 'rgba(0,0,0,0.5)', chip: 'rgba(0,0,0,0.1)', accent: '#BC2649' };

  // The text is never bigger than the stage allows, so a long verse still fits on one screen without scrolling.
  const fitSize = (lines) => {
    for (let s = size; s > 20; s -= 2) {
      const rows = lines.reduce((sum, l) => sum + Math.max(1, Math.ceil((l.length * 0.5 * s) / (box.w - 16))), 0);
      if (rows * s * 1.38 <= box.h - 110) return s;
    }

    return 20;
  };

  const next = () => setAt((n) => Math.min(n + 1, sections.length));
  const back = () => setAt((n) => Math.max(0, n - 1));
  const toggleTheme = () => {
    setDark(!dark);
    setStored('worship_dark', !dark);
  };
  const resize = (delta) => {
    const value = Math.max(26, Math.min(72, size + delta));
    setSize(value);
    setStored('worship_size', value);
  };

  return (
    <View style={[styles.screen, { backgroundColor: palette.bg, paddingTop: insets.top + 6, paddingBottom: insets.bottom + 10 }]}>
      <StateView loading={state.loading} error={state.error} onRetry={load}>
        {state.hymn ? (
          <>
            <View style={styles.top}>
              <Round icon="close" label="Close worship mode" color={palette.chip} onPress={() => router.back()} />
              <View style={{ flex: 1, alignItems: 'center' }}>
                <AppText style={[styles.small, { color: palette.soft }]} numberOfLines={1}>
                  {state.hymn.number ? `Hymn ${state.hymn.number}` : state.hymn.hymnal?.name}
                </AppText>
                <AppText style={[styles.heading, { color: palette.ink }]} numberOfLines={1}>
                  {state.hymn.title}
                </AppText>
              </View>
              <Round icon={dark ? 'sunny-outline' : 'moon-outline'} label={dark ? 'Light theme' : 'Dark theme'} color={palette.chip} onPress={toggleTheme} />
            </View>

            <View style={styles.dots}>
              {sections.map((s, i) => (
                <View key={i} style={[styles.dot, { backgroundColor: i === Math.min(at, last) ? palette.accent : palette.chip, width: s.type === 'refrain' ? 14 : 22 }]} />
              ))}
            </View>

            <View style={styles.stage} onLayout={(e) => setBox({ w: e.nativeEvent.layout.width, h: e.nativeEvent.layout.height })}>
              {done ? null : (
                <>
                  <Pressable style={styles.zone} onPress={back} accessibilityLabel="Previous verse" accessibilityRole="button" />
                  <Pressable style={[styles.zone, { right: 0, left: '35%' }]} onPress={next} accessibilityLabel="Next verse" accessibilityRole="button" />
                </>
              )}
              <View style={[styles.center, { pointerEvents: done ? 'auto' : 'none' }]}>
                {done ? (
                  <View style={{ alignItems: 'center', gap: 18 }}>
                    <AppText style={[styles.endTitle, { color: palette.ink }]}>Amen</AppText>
                    {state.hymn.next_id ? (
                      <Pressable onPress={() => router.replace({ pathname: '/worship/[id]', params: { id: state.hymn.next_id } })} style={[styles.nextHymn, { backgroundColor: palette.accent }]} accessibilityRole="button">
                        <AppText style={styles.nextHymnText}>Next hymn</AppText>
                        <Ionicons name="arrow-forward" size={18} color="#fff" />
                      </Pressable>
                    ) : null}
                    <Pressable onPress={() => setAt(0)} accessibilityRole="button">
                      <AppText style={[styles.small, { color: palette.soft, fontSize: 15 }]}>Sing it again</AppText>
                    </Pressable>
                  </View>
                ) : section ? (
                  <View style={{ alignItems: 'center' }}>
                    <AppText style={[styles.label, { color: palette.accent }]}>{section.type === 'refrain' ? 'REFRAIN' : `VERSE ${section.label}`}</AppText>
                    {section.lines.map((l, n) => (
                      <AppText key={n} style={{ fontFamily: section.type === 'refrain' ? FONTS.semibold : FONTS.medium, fontStyle: section.type === 'refrain' ? 'italic' : 'normal', fontSize: fitSize(section.lines), lineHeight: Math.round(fitSize(section.lines) * 1.38), color: palette.ink, textAlign: 'center' }}>
                        {l}
                      </AppText>
                    ))}
                  </View>
                ) : null}
              </View>
            </View>

            <View style={styles.bottom}>
              <Round icon="remove" label="Smaller text" color={palette.chip} onPress={() => resize(-4)} />
              <Pressable onPress={back} disabled={at === 0} style={[styles.step, { backgroundColor: palette.chip }, at === 0 && { opacity: 0.35 }]} accessibilityRole="button" accessibilityLabel="Previous">
                <Ionicons name="chevron-back" size={24} color={palette.ink} />
              </Pressable>
              <Pressable onPress={next} disabled={done} style={[styles.step, styles.stepNext, { backgroundColor: palette.accent }, done && { opacity: 0.35 }]} accessibilityRole="button" accessibilityLabel="Next">
                <Ionicons name="chevron-forward" size={28} color="#fff" />
              </Pressable>
              <Round icon="add" label="Larger text" color={palette.chip} onPress={() => resize(4)} />
            </View>
            {state.hymn.author ? (
              <AppText style={[styles.credit, { color: palette.soft }]} numberOfLines={1}>
                {[state.hymn.author, state.hymn.copyright_label].filter(Boolean).join(' · ')}
              </AppText>
            ) : null}
          </>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, paddingHorizontal: 16 },
  top: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  round: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
  small: { fontFamily: FONTS.semibold, fontSize: 12.5, letterSpacing: 0.6 },
  heading: { fontFamily: FONTS.bold, fontSize: 17 },
  dots: { flexDirection: 'row', justifyContent: 'center', gap: 5, marginTop: 14, flexWrap: 'wrap' },
  dot: { height: 6, borderRadius: 3 },
  stage: { flex: 1, marginVertical: 8 },
  zone: { position: 'absolute', top: 0, bottom: 0, left: 0, width: '35%', zIndex: 2 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', paddingVertical: 20, paddingHorizontal: 6 },
  label: { fontFamily: FONTS.bold, fontSize: 13, letterSpacing: 2, marginBottom: 18 },
  endTitle: { fontFamily: FONTS.bold, fontSize: 54 },
  nextHymn: { flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: 30, paddingHorizontal: 26, paddingVertical: 14, zIndex: 5 },
  nextHymnText: { fontFamily: FONTS.bold, fontSize: 17, color: '#fff' },
  bottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  step: { flex: 1, height: 56, borderRadius: 28, alignItems: 'center', justifyContent: 'center' },
  stepNext: { flex: 1.6 },
  credit: { fontFamily: FONTS.regular, fontSize: 11.5, textAlign: 'center', marginTop: 8 },
});
