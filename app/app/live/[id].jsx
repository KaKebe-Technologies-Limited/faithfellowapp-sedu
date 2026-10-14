// Watch a church service live in the app: the stream plays at the top (HLS or a YouTube page), with the live
// chat below. Before the service it shows a countdown and switches to the stream by itself when it starts;
// afterwards it plays the recording if the church kept one. A heartbeat every 30 s counts you as a viewer.
import { useCallback, useEffect, useRef, useState } from 'react';
import { FlatList, KeyboardAvoidingView, Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import { Video } from '../../components/MediaPlayers';
import Pill from '../../components/Pill';
import { BackButton } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api from '../../lib/api';
import { dayAndTime } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

function countdown(iso) {
  const ms = new Date(iso).getTime() - Date.now();
  if (ms <= 0) return 'any moment now';
  const m = Math.floor(ms / 60000);
  const d = Math.floor(m / 1440);
  const h = Math.floor((m % 1440) / 60);
  return d ? `in ${d}d ${h}h` : h ? `in ${h}h ${m % 60}m` : `in ${m} min`;
}

export default function LiveScreen() {
  const { id } = useLocalSearchParams();
  const insets = useSafeAreaInsets();
  const { data, loading, error, retry, reload } = useFetch(`/live/${id}`);
  const live = data?.data;
  const status = live?.status;

  const [messages, setMessages] = useState([]);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [viewers, setViewers] = useState(null);
  const [, tick] = useState(0);
  const last = useRef(0);
  const list = useRef(null);

  // Scheduled: re-check every 20 s so the video appears as soon as the church goes live.
  useEffect(() => {
    if (status !== 'scheduled') return undefined;
    const t = setInterval(() => {
      tick((n) => n + 1);
      reload();
    }, 20000);
    return () => clearInterval(t);
  }, [status, reload]);

  // Live: count me as a viewer.
  useEffect(() => {
    if (status !== 'live') return undefined;
    const beat = () => api.post(`/live/${id}/heartbeat`).then((r) => setViewers(r.data.viewers)).catch(() => {});
    beat();
    const t = setInterval(beat, 30000);
    return () => clearInterval(t);
  }, [id, status]);

  // Chat: fetch what is new every 4 seconds while the service is on.
  const pull = useCallback(async () => {
    try {
      const { data: res } = await api.get(`/live/${id}/messages`, { params: { after: last.current } });
      if (res.data.length) {
        last.current = res.data[res.data.length - 1].id;
        setMessages((m) => [...m.filter((x) => !res.hidden.includes(x.id)), ...res.data].slice(-200));
      } else if (res.hidden.length) {
        setMessages((m) => m.filter((x) => !res.hidden.includes(x.id)));
      }
    } catch {
      // The next poll will try again.
    }
  }, [id]);

  useEffect(() => {
    if (!live || !live.chat_enabled || status === 'scheduled') return undefined;
    pull();
    if (status !== 'live') return undefined;
    const t = setInterval(pull, 4000);
    return () => clearInterval(t);
  }, [live?.chat_enabled, status, pull]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (messages.length) setTimeout(() => list.current?.scrollToEnd({ animated: true }), 50);
  }, [messages.length]);

  async function send() {
    const body = draft.trim();
    if (!body || sending) return;
    setSending(true);
    try {
      const { data: res } = await api.post(`/live/${id}/messages`, { body });
      last.current = Math.max(last.current, res.data.id);
      setMessages((m) => [...m, res.data]);
      setDraft('');
    } catch {
      // Chat may have closed; the next poll shows the state.
    } finally {
      setSending(false);
    }
  }

  const playback = live?.playback;
  const source = playback?.video || playback?.embed;
  const recording = live?.recording?.url;

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={retry}>
        {live ? (
          <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <View style={{ paddingTop: insets.top, backgroundColor: '#000' }}>
              {status === 'live' && source ? (
                <Video url={source} />
              ) : status !== 'live' && recording ? (
                <Video url={recording} autoPlay={false} />
              ) : (
                <View style={styles.stage}>
                  <LinearGradient colors={['#2A0A14', COLORS.primaryDark]} style={StyleSheet.absoluteFill} />
                  <Ionicons name={status === 'scheduled' ? 'time-outline' : 'radio-outline'} size={44} color={COLORS.primaryPale} />
                  <AppText style={styles.stageTitle}>
                    {status === 'scheduled' ? `Starts ${countdown(live.scheduled_at)}` : status === 'live' ? 'Connecting to the stream…' : 'This service has ended'}
                  </AppText>
                  <AppText style={styles.stageSub}>{status === 'scheduled' ? dayAndTime(live.scheduled_at) : status === 'live' ? 'The picture will appear in a moment.' : 'No recording is available yet.'}</AppText>
                </View>
              )}
              <View style={[styles.back, { top: insets.top + 8 }]}>
                <BackButton onDark />
              </View>
            </View>

            <View style={styles.info}>
              <View style={{ flex: 1, gap: 4 }}>
                <View style={{ flexDirection: 'row', gap: 6, alignItems: 'center' }}>
                  {status === 'live' ? <Pill label="LIVE" icon="radio-outline" tone="live" /> : <Pill label={status === 'scheduled' ? 'Upcoming' : 'Ended'} />}
                  {status === 'live' && viewers != null ? <AppText variant="caption">{viewers} watching</AppText> : null}
                </View>
                <AppText style={styles.title} numberOfLines={2}>
                  {live.title}
                </AppText>
                <Pressable onPress={() => live.church && router.push({ pathname: '/church/[slug]', params: { slug: live.church.slug } })} style={styles.host}>
                  <ChurchAvatar church={live.church} size={22} />
                  <AppText variant="caption">{live.church?.name}</AppText>
                </Pressable>
              </View>
            </View>

            {live.chat_enabled && status === 'scheduled' ? (
              <View style={styles.chatSoon}>
                <Ionicons name="chatbubbles-outline" size={22} color={COLORS.primary} />
                <AppText style={styles.chatSoonText}>The live chat opens when the service starts.</AppText>
              </View>
            ) : live.chat_enabled ? (
              <View style={styles.chat}>
                <AppText style={styles.chatTitle}>Live chat</AppText>
                <FlatList
                  ref={list}
                  data={messages}
                  keyExtractor={(m) => String(m.id)}
                  contentContainerStyle={{ padding: SPACING.md, paddingTop: 4, gap: 10 }}
                  ListEmptyComponent={<AppText variant="caption" style={{ textAlign: 'center', marginTop: 20 }}>{status === 'live' ? 'Say hello 👋' : 'No messages.'}</AppText>}
                  renderItem={({ item: m }) => (
                    <View style={styles.msg}>
                      <View style={[styles.msgDot, m.mine && { backgroundColor: COLORS.primary }]}>
                        <AppText style={styles.msgInitial}>{String(m.name || '?').charAt(0).toUpperCase()}</AppText>
                      </View>
                      <View style={{ flex: 1 }}>
                        <AppText style={styles.msgName}>{m.mine ? 'You' : m.name}</AppText>
                        <AppText style={styles.msgBody}>{m.body}</AppText>
                      </View>
                    </View>
                  )}
                />
                {status === 'live' ? (
                  <View style={[styles.compose, { paddingBottom: Math.max(insets.bottom, 10) }]}>
                    <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={draft} onChangeText={setDraft} placeholder="Write a message…" placeholderTextColor={COLORS.gray500} maxLength={500} onSubmitEditing={send} returnKeyType="send" style={styles.input} />
                    <Pressable onPress={send} disabled={!draft.trim() || sending} style={[styles.send, (!draft.trim() || sending) && { opacity: 0.45 }]} accessibilityLabel="Send message" accessibilityRole="button">
                      <Ionicons name="send" size={18} color={COLORS.white} />
                    </Pressable>
                  </View>
                ) : null}
              </View>
            ) : (
              <View style={{ flex: 1 }} />
            )}
          </KeyboardAvoidingView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  back: { position: 'absolute', left: SPACING.md },
  stage: { width: '100%', aspectRatio: 16 / 9, alignItems: 'center', justifyContent: 'center', gap: 6, padding: SPACING.lg },
  stageTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.white, textAlign: 'center' },
  stageSub: { fontFamily: FONTS.regular, fontSize: 14, color: COLORS.primaryPale, textAlign: 'center' },
  info: { flexDirection: 'row', padding: SPACING.md, backgroundColor: COLORS.white },
  title: { fontFamily: FONTS.bold, fontSize: 19, lineHeight: 25, color: COLORS.black },
  host: { flexDirection: 'row', alignItems: 'center', gap: 7, marginTop: 2 },
  chat: { flex: 1 },
  chatSoon: { flexDirection: 'row', alignItems: 'center', gap: 10, margin: SPACING.md, padding: 14, borderRadius: RADIUS.lg, backgroundColor: COLORS.primaryMist },
  chatSoonText: { flex: 1, fontFamily: FONTS.medium, fontSize: 14, color: COLORS.primaryDark },
  chatTitle: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black, paddingHorizontal: SPACING.md, paddingTop: 12, paddingBottom: 6 },
  msg: { flexDirection: 'row', gap: 10 },
  msgDot: { width: 30, height: 30, borderRadius: 15, backgroundColor: COLORS.primaryLight, alignItems: 'center', justifyContent: 'center' },
  msgInitial: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.white },
  msgName: { fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.gray700 },
  msgBody: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 20, color: COLORS.black },
  compose: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: SPACING.md, paddingTop: 10, backgroundColor: COLORS.white, borderTopWidth: 1, borderTopColor: COLORS.gray100 },
  input: { flex: 1, minHeight: 42, borderRadius: RADIUS.full, backgroundColor: COLORS.canvas, paddingHorizontal: 16, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  send: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
});
