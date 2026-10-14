// A note, written in the app and saved as you type — on the phone first, so it works with no internet; it is sent to
// the server as soon as there is a connection. Opened from the notebook, from a Bible chapter (one note per chapter,
// with the verses you selected as its reference), a sermon, a reading plan, or fresh. Colours, pinning and delete are
// in the header. Notes are private to you.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import ScreenHeader, { HeaderAction, goBack } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { isOffline } from '../../lib/net';
import { PAPER, getNote, getNoteById, markDeleted, mergeFromServer, newContext, putNote } from '../../lib/notesStore';
import { submit } from '../../lib/outbox';

export default function NoteScreen() {
  const { id: idParam, context: contextParam, reference } = useLocalSearchParams();
  const [context, setContext] = useState(contextParam ? String(contextParam) : null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [color, setColor] = useState('paper');
  const [pinned, setPinned] = useState(false);
  const [state, setState] = useState('idle'); // idle | dirty | saving | saved | queued
  const refText = useRef(reference ? String(reference) : null);
  const timer = useRef(null);
  const touched = useRef(false);
  const latest = useRef({});
  latest.current = { context, title, body, color };

  const fill = useCallback((note) => {
    setTitle(note.title ?? '');
    setBody(note.body ?? '');
    setColor(note.color || 'paper');
    setPinned(!!note.pinned);
    if (!refText.current) refText.current = note.reference ?? null;
  }, []);

  // Start from what is on the phone, then look at the server for anything newer (never over your own typing).
  useEffect(() => {
    let alive = true;
    (async () => {
      let ctx = contextParam ? String(contextParam) : null;
      let local = ctx ? await getNote(ctx) : null;

      if (!ctx && idParam && idParam !== 'new') {
        local = await getNoteById(idParam);
        ctx = local?.context ?? null;
        if (!ctx) {
          try {
            const { data } = await api.get(`/notes/${idParam}`);
            await mergeFromServer([data.data], false);
            ctx = data.data.context;
            local = await getNote(ctx);
          } catch (e) {
            if (alive) {
              setError(isOffline(e) ? 'This note is not on your phone yet. Open it once with a connection.' : errorMessage(e));
              setLoading(false);
            }
            return;
          }
        }
      }
      ctx ??= newContext();
      if (!alive) return;
      setContext(ctx);
      if (local) fill(local);
      setLoading(false);

      try {
        const { data } = await api.get('/notes/context', { params: { context: ctx } });
        if (data.data && alive && !touched.current && !(await getNote(ctx))?.dirty) {
          await mergeFromServer([data.data], false);
          fill((await getNote(ctx)) ?? data.data);
        }
      } catch {
        // No connection: the copy on the phone is what you see.
      }
    })();
    return () => {
      alive = false;
    };
  }, [idParam, contextParam, fill]);

  /** Writes the note on the phone, then sends it (or leaves it waiting when there is no connection). */
  const save = useCallback(async () => {
    clearTimeout(timer.current);
    timer.current = null;
    const { context: ctx, title: t, body: b, color: c } = latest.current;
    if (!ctx || (!t.trim() && !b.trim() && !(await getNote(ctx)))) {
      setState('idle');
      return;
    }
    setState('saving');
    try {
      await putNote(ctx, { title: t, body: b, color: c, ...(refText.current ? { reference: refText.current } : {}) });
      const { queued } = await submit('note.save', {}, ctx);
      setState(queued ? 'queued' : 'saved');
      setError(null);
    } catch (e) {
      setState('dirty');
      setError(errorMessage(e));
    }
  }, []);

  // Save shortly after the last keystroke, and when leaving.
  function changed() {
    touched.current = true;
    setState('dirty');
    clearTimeout(timer.current);
    timer.current = setTimeout(save, 800);
  }
  useEffect(
    () => () => {
      if (timer.current) save();
    },
    [save],
  );

  async function togglePin() {
    if (!context) return;
    if (!title.trim() && !body.trim()) return;
    const next = !pinned;
    setPinned(next);
    await save();
    await putNote(context, { pinned: next });
    submit('note.pin', { pinned: next }, context).catch((e) => Alert.alert('Could not pin', errorMessage(e)));
  }

  function remove() {
    const run = async () => {
      clearTimeout(timer.current);
      timer.current = null;
      if (context) {
        await markDeleted(context);
        submit('note.delete', {}, context).catch(() => {});
      }
      goBack();
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this note?', 'This cannot be undone.', [
      { text: 'Keep it', style: 'cancel' },
      { text: 'Delete', style: 'destructive', onPress: run },
    ]);
  }

  async function leave() {
    if (timer.current || state === 'dirty') await save();
    goBack();
  }

  const subtitle = { saving: 'Saving…', saved: 'Saved ✓', queued: 'Saved on your phone · will sync', dirty: 'Unsaved changes' }[state];

  return (
    <View style={[styles.screen, { backgroundColor: PAPER[color] ?? PAPER.paper }]}>
      <ScreenHeader
        title={refText.current || 'Note'}
        subtitle={subtitle}
        right={
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <HeaderAction icon={pinned ? 'pin' : 'pin-outline'} label={pinned ? 'Unpin' : 'Pin'} onPress={togglePin} />
            <HeaderAction icon="trash-outline" label="Delete note" onPress={remove} />
          </View>
        }
      />
      <StateView loading={loading} error={error && !context ? error : null} onRetry={() => router.replace({ pathname: '/note/[id]', params: { id: String(idParam) } })}>
        <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {refText.current ? (
              <View style={styles.ref}>
                <Ionicons name="bookmark" size={14} color={COLORS.primary} />
                <AppText style={styles.refText}>{refText.current}</AppText>
              </View>
            ) : null}
            <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={title} onChangeText={(t) => { setTitle(t); changed(); }} placeholder="Title" placeholderTextColor={COLORS.gray500} style={styles.title} maxLength={160} />
            <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={body} onChangeText={(t) => { setBody(t); changed(); }} placeholder="Write what God is showing you…" placeholderTextColor={COLORS.gray500} style={styles.body} multiline textAlignVertical="top" scrollEnabled={false} />
            <View style={styles.colors}>
              {Object.entries(PAPER).map(([key, hex]) => (
                <Pressable key={key} onPress={() => { setColor(key); changed(); }} accessibilityRole="button" accessibilityLabel={`${key} paper`} style={[styles.dot, { backgroundColor: hex }, color === key && styles.dotOn]} />
              ))}
            </View>
            {error && context ? <AppText style={{ color: COLORS.danger, textAlign: 'center', marginTop: 10 }}>{error}</AppText> : null}
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
