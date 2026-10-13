// A note, written in the app and saved as you type. Opened from the notebook, from a Bible chapter (one note per
// chapter, with the verses you selected as its reference), or fresh (id "new"). Colours, pinning and delete are
// in the header. Notes are private to you.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import ScreenHeader, { HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';

const PAPER = { paper: '#FFFDF7', rose: '#FDF0F3', sky: '#EEF5FF', mint: '#EDF9F2', sun: '#FFF8E6' };

export default function NoteScreen() {
  const { id: idParam, context, reference } = useLocalSearchParams();
  const [id, setId] = useState(idParam === 'new' ? null : Number(idParam));
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [color, setColor] = useState('paper');
  const [pinned, setPinned] = useState(false);
  const [state, setState] = useState('idle'); // idle | dirty | saving | saved
  const refText = useRef(reference ? String(reference) : null);
  const creating = useRef(false);
  const timer = useRef(null);
  const latest = useRef({});
  latest.current = { id, title, body, color };

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      let note = null;
      if (idParam !== 'new') note = (await api.get(`/notes/${idParam}`)).data.data;
      else if (context) note = (await api.get('/notes/context', { params: { context: String(context) } })).data.data;
      if (note) {
        setId(note.id);
        setTitle(note.title ?? '');
        setBody(note.body ?? '');
        setColor(note.color);
        setPinned(note.pinned);
        if (!refText.current) refText.current = note.reference;
      }
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  }, [idParam, context]);

  useEffect(() => {
    load();
  }, [load]);

  const save = useCallback(async () => {
    const { id: noteId, title: t, body: b, color: c } = latest.current;
    if (!noteId && !t.trim() && !b.trim()) {
      setState('idle');
      return;
    }
    if (creating.current) return;
    setState('saving');
    try {
      if (noteId) {
        await api.patch(`/notes/${noteId}`, { title: t || null, body: b, color: c });
      } else {
        creating.current = true;
        const { data } = await api.post('/notes', { ...(context ? { context: String(context) } : {}), reference: refText.current, title: t || null, body: b, color: c });
        setId(data.data.id);
      }
      setState('saved');
    } catch (e) {
      setState('dirty');
      setError(errorMessage(e));
    } finally {
      creating.current = false;
    }
  }, [context]);

  // Save shortly after the last keystroke, and when leaving.
  function changed() {
    setState('dirty');
    clearTimeout(timer.current);
    timer.current = setTimeout(save, 800);
  }
  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );

  async function togglePin() {
    if (!id) return;
    try {
      const { data } = await api.post(`/notes/${id}/pin`);
      setPinned(data.data.pinned);
    } catch (e) {
      Alert.alert('Could not pin', errorMessage(e));
    }
  }

  function remove() {
    const run = async () => {
      try {
        if (id) await api.delete(`/notes/${id}`);
        router.back();
      } catch (e) {
        Alert.alert('Could not delete', errorMessage(e));
      }
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this note?', 'This cannot be undone.', [
      { text: 'Keep it', style: 'cancel' },
      { text: 'Delete', style: 'destructive', onPress: run },
    ]);
  }

  async function leave() {
    clearTimeout(timer.current);
    if (state === 'dirty') await save();
    router.back();
  }

  return (
    <View style={[styles.screen, { backgroundColor: PAPER[color] ?? PAPER.paper }]}>
      <ScreenHeader
        title={refText.current || 'Note'}
        subtitle={state === 'saving' ? 'Saving…' : state === 'saved' ? 'Saved ✓' : state === 'dirty' ? 'Unsaved changes' : undefined}
        right={
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <HeaderAction icon={pinned ? 'pin' : 'pin-outline'} label={pinned ? 'Unpin' : 'Pin'} onPress={togglePin} />
            <HeaderAction icon="trash-outline" label="Delete note" onPress={remove} />
          </View>
        }
      />
      <StateView loading={loading} error={error && loading ? error : null} onRetry={load}>
        <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {refText.current ? (
              <View style={styles.ref}>
                <Ionicons name="bookmark" size={14} color={COLORS.primary} />
                <AppText style={styles.refText}>{refText.current}</AppText>
              </View>
            ) : null}
            <TextInput value={title} onChangeText={(t) => { setTitle(t); changed(); }} placeholder="Title" placeholderTextColor={COLORS.gray500} style={styles.title} maxLength={160} />
            <TextInput value={body} onChangeText={(t) => { setBody(t); changed(); }} placeholder="Write what God is showing you…" placeholderTextColor={COLORS.gray500} style={styles.body} multiline textAlignVertical="top" scrollEnabled={false} />
            <View style={styles.colors}>
              {Object.entries(PAPER).map(([key, hex]) => (
                <Pressable key={key} onPress={() => { setColor(key); changed(); }} accessibilityRole="button" accessibilityLabel={`${key} paper`} style={[styles.dot, { backgroundColor: hex }, color === key && styles.dotOn]} />
              ))}
            </View>
            {error && !loading ? <AppText style={{ color: COLORS.danger, textAlign: 'center', marginTop: 10 }}>{error}</AppText> : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </StateView>
      <Pressable onPress={leave} style={styles.done} accessibilityRole="button">
        <AppText style={styles.doneText}>Done</AppText>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  content: { padding: SPACING.md, paddingBottom: 100 },
  ref: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 6, marginBottom: 12 },
  refText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.primary },
  title: { fontFamily: FONTS.bold, fontSize: 24, color: COLORS.black, paddingVertical: 8, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  body: { fontFamily: FONTS.regular, fontSize: 17, lineHeight: 27, color: '#1F1F1F', minHeight: 260, paddingTop: 6, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  colors: { flexDirection: 'row', gap: 12, marginTop: 20 },
  dot: { width: 34, height: 34, borderRadius: 17, borderWidth: 1.5, borderColor: 'rgba(0,0,0,0.12)' },
  dotOn: { borderColor: COLORS.primary, borderWidth: 2.5 },
  done: { position: 'absolute', right: SPACING.md, bottom: 24, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 26, paddingVertical: 13 },
  doneText: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.white },
});
