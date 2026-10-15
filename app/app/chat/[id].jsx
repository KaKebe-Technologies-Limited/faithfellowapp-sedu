// A private chat with one connection. New messages arrive by polling every few seconds while the screen is open;
// older ones load when you pull "Earlier messages". Messages are plain text, up to 2,000 characters.
import { useCallback, useEffect, useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import { BackButton } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { setActiveChat } from '../../lib/push';

const clock = (iso) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
const day = (iso) => new Date(iso).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });

export default function ChatScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const [messages, setMessages] = useState([]);
  const [partner, setPartner] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [more, setMore] = useState(false);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [problem, setProblem] = useState(null);
  const scroller = useRef(null);
  const last = useRef(0);

  const load = useCallback(async () => {
    try {
      const { data } = await api.get(`/messages/${id}`);
      setMessages(data.data);
      setPartner(data.partner);
      setMore(data.has_more);
      last.current = data.data.length ? data.data[data.data.length - 1].id : 0;
      setError(null);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  // A push for the chat that is open would only repeat what is on screen.
  useEffect(() => {
    setActiveChat(id);
    return () => setActiveChat(null);
  }, [id]);

  // New messages, every 3 seconds.
  useEffect(() => {
    const t = setInterval(async () => {
      try {
        const { data } = await api.get(`/messages/${id}`, { params: { after: last.current } });
        if (data.data.length) {
          last.current = data.data[data.data.length - 1].id;
          setMessages((m) => [...m, ...data.data.filter((x) => !m.some((y) => y.id === x.id))]);
        }
        setPartner(data.partner);
      } catch {
        // The next poll will try again.
      }
    }, 3000);
    return () => clearInterval(t);
  }, [id]);

  useEffect(() => {
    if (messages.length) setTimeout(() => scroller.current?.scrollToEnd({ animated: false }), 30);
  }, [messages.length]);

  async function earlier() {
    const first = messages[0]?.id;
    if (!first) return;
    const { data } = await api.get(`/messages/${id}`, { params: { before: first } });
    setMessages((m) => [...data.data, ...m]);
    setMore(data.has_more);
  }

  async function send() {
    const body = draft.trim();
    if (!body || sending) return;
    setSending(true);
    setProblem(null);
    try {
      const { data } = await api.post(`/messages/${id}`, { body });
      last.current = Math.max(last.current, data.data.id);
      setMessages((m) => [...m, data.data]);
      setDraft('');
      // Until they answer once, only one message can be sent (the server decides; this just shows it straight away).
      if (!messages.some((x) => !x.mine) && !more) setPartner((p) => (p ? { ...p, waiting_for_reply: true } : p));
    } catch (e) {
      setProblem(errorMessage(e));
    } finally {
      setSending(false);
    }
  }

  return (
    <View style={styles.screen}>
      <View style={[styles.bar, { paddingTop: insets.top + 8 }]}>
        <BackButton />
        <Pressable onPress={() => router.push({ pathname: '/person/[id]', params: { id: String(id) } })} style={styles.who} accessibilityRole="button" accessibilityLabel="View profile">
          {partner ? <Avatar person={partner} size={38} /> : null}
          <AppText style={styles.title} numberOfLines={1}>
            {partner?.name ?? 'Chat'}
          </AppText>
        </Pressable>
      </View>

      <StateView loading={loading} error={error} onRetry={load}>
        <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
          <ScrollView ref={scroller} contentContainerStyle={styles.list} keyboardShouldPersistTaps="handled">
            {more ? (
              <Pressable onPress={earlier} style={styles.earlier} accessibilityRole="button">
                <AppText style={styles.earlierText}>Earlier messages</AppText>
              </Pressable>
            ) : null}
            {messages.length === 0 ? <AppText variant="caption" style={{ textAlign: 'center', marginTop: 30 }}>Say hello 👋</AppText> : null}
            {messages.map((m, i) => (
              <View key={m.id}>
                {i === 0 || day(m.at) !== day(messages[i - 1].at) ? <AppText style={styles.day}>{day(m.at)}</AppText> : null}
                <View style={[styles.bubbleRow, m.mine && { justifyContent: 'flex-end' }]}>
                  <View style={[styles.bubble, m.mine ? styles.mine : styles.theirs]}>
                    <AppText style={[styles.text, m.mine && { color: COLORS.white }]}>{m.body}</AppText>
                    <AppText style={[styles.time, m.mine && { color: 'rgba(255,255,255,0.75)' }]}>
                      {clock(m.at)}
                      {m.mine && m.read ? '  ✓✓' : ''}
                    </AppText>
                  </View>
                </View>
              </View>
            ))}
          </ScrollView>

          {partner && !partner.can_message ? (
            <View style={[styles.closed, { paddingBottom: insets.bottom + 12 }]}>
              <AppText style={styles.closedText}>You are no longer connected, so you cannot send new messages.</AppText>
            </View>
          ) : partner?.waiting_for_reply ? (
            <View style={[styles.closed, { paddingBottom: insets.bottom + 12 }]}>
              <Ionicons name="hourglass-outline" size={20} color={COLORS.primary} style={{ alignSelf: 'center', marginBottom: 4 }} />
              <AppText style={styles.closedText}>Your message is sent. You can write more once {String(partner.name).split(' ')[0]} replies.</AppText>
            </View>
          ) : (
            <View style={[styles.compose, { paddingBottom: Math.max(insets.bottom, 10) }]}>
              {problem ? <AppText style={styles.problem}>{problem}</AppText> : null}
              {messages.length === 0 ? <AppText variant="caption" style={styles.safety}>Keep chatting here in the app. Phone numbers and emails cannot be shared.</AppText> : null}
              <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 10 }}>
                <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={draft} onChangeText={setDraft} placeholder="Write a message…" placeholderTextColor={COLORS.gray500} multiline maxLength={2000} style={styles.input} />
                <Pressable onPress={send} disabled={!draft.trim() || sending} style={[styles.send, (!draft.trim() || sending) && { opacity: 0.45 }]} accessibilityRole="button" accessibilityLabel="Send message">
                  <Ionicons name="send" size={18} color={COLORS.white} />
                </Pressable>
              </View>
            </View>
          )}
        </KeyboardAvoidingView>
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  bar: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: SPACING.md, paddingBottom: 10, backgroundColor: COLORS.white, borderBottomWidth: 1, borderBottomColor: COLORS.gray100 },
  who: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 10 },
  title: { flex: 1, fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black },
  list: { padding: SPACING.md, paddingBottom: 12 },
  earlier: { alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 7, marginBottom: 10 },
  earlierText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.primary },
  day: { fontFamily: FONTS.medium, fontSize: 12, color: COLORS.gray700, textAlign: 'center', marginVertical: 12 },
  bubbleRow: { flexDirection: 'row', marginBottom: 6 },
  bubble: { maxWidth: '80%', borderRadius: 18, paddingHorizontal: 14, paddingVertical: 9 },
  mine: { backgroundColor: COLORS.primary, borderBottomRightRadius: 4 },
  theirs: { backgroundColor: COLORS.white, borderBottomLeftRadius: 4 },
  text: { fontFamily: FONTS.regular, fontSize: 15.5, lineHeight: 21, color: COLORS.black },
  time: { fontFamily: FONTS.regular, fontSize: 10.5, color: COLORS.gray500, alignSelf: 'flex-end', marginTop: 2 },
  compose: { backgroundColor: COLORS.white, borderTopWidth: 1, borderTopColor: COLORS.gray100, paddingHorizontal: SPACING.md, paddingTop: 10 },
  safety: { color: COLORS.gray700, textAlign: 'center', marginBottom: 6 },
  problem: { color: COLORS.danger, fontFamily: FONTS.medium, fontSize: 13, marginBottom: 6 },
  input: { flex: 1, maxHeight: 120, minHeight: 42, borderRadius: 21, backgroundColor: COLORS.canvas, paddingHorizontal: 16, paddingTop: 11, paddingBottom: 10, fontFamily: FONTS.regular, fontSize: 15.5, color: COLORS.black, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  send: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  closed: { backgroundColor: COLORS.white, padding: 14 },
  closedText: { fontFamily: FONTS.regular, fontSize: 13.5, color: COLORS.gray700, textAlign: 'center' },
});
