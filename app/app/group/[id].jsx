// A study group, cell or fellowship. Everyone at the church sees what it is and can ask to join. Members get the
// group's conversation, notices, member list and meeting record. Leaders can approve requests, add people,
// take attendance and post notices — all here. Data: GET /api/v1/groups/{id}, …/posts and the leader endpoints.
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Switch, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, mediaUrl } from '../../lib/api';
import { formatDate, timeAgo } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

function Conversation({ group }) {
  const insets = useSafeAreaInsets();
  const [posts, setPosts] = useState(null);
  const [draft, setDraft] = useState('');
  const [error, setError] = useState(null);
  const [sending, setSending] = useState(false);
  const scroller = useRef(null);
  const last = useRef(0);

  const load = useCallback(async () => {
    try {
      const { data } = await api.get(`/groups/${group.id}/posts`);
      setPosts(data.data);
      last.current = data.data.length ? data.data[data.data.length - 1].id : 0;
    } catch (e) {
      setError(errorMessage(e));
    }
  }, [group.id]);

  useEffect(() => {
    load();
    const t = setInterval(async () => {
      try {
        const { data } = await api.get(`/groups/${group.id}/posts`, { params: { after: last.current } });
        if (data.data.length) {
          last.current = data.data[data.data.length - 1].id;
          setPosts((p) => [...(p ?? []), ...data.data.filter((x) => !(p ?? []).some((y) => y.id === x.id))]);
        }
      } catch {
        // Next poll.
      }
    }, 5000);
    return () => clearInterval(t);
  }, [load, group.id]);

  useEffect(() => {
    if (posts?.length) setTimeout(() => scroller.current?.scrollToEnd({ animated: false }), 30);
  }, [posts?.length]);

  async function send() {
    const body = draft.trim();
    if (!body || sending) return;
    setSending(true);
    try {
      const { data } = await api.post(`/groups/${group.id}/posts`, { body });
      last.current = Math.max(last.current, data.data.id);
      setPosts((p) => [...(p ?? []), data.data]);
      setDraft('');
    } catch (e) {
      Alert.alert('Could not send', errorMessage(e));
    } finally {
      setSending(false);
    }
  }

  function remove(post) {
    const run = async () => {
      await api.delete(`/groups/${group.id}/posts/${post.id}`).catch(() => {});
      setPosts((p) => p.filter((x) => x.id !== post.id));
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this message?', undefined, [{ text: 'Cancel', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: run }]);
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={Platform.OS === 'ios' ? 80 : 0}>
      <StateView loading={posts === null && !error} error={error} onRetry={load}>
        <ScrollView ref={scroller} contentContainerStyle={{ padding: SPACING.md, gap: 10 }} keyboardShouldPersistTaps="handled">
          {posts?.length === 0 ? <AppText variant="caption" style={{ textAlign: 'center', marginTop: 24 }}>No messages yet — start the conversation 🙏</AppText> : null}
          {posts?.map((p) => (
            <Pressable key={p.id} onLongPress={p.can_delete ? () => remove(p) : undefined} style={[styles.post, p.mine && { flexDirection: 'row-reverse' }]} accessibilityLabel={`${p.author.name}: ${p.body}`}>
              <Avatar person={p.author} size={34} />
              <View style={[styles.bubble, p.mine ? styles.mine : styles.theirs]}>
                {!p.mine ? <AppText style={styles.author}>{p.author.name}</AppText> : null}
                <AppText style={[styles.postText, p.mine && { color: COLORS.white }]}>{p.body}</AppText>
                <AppText style={[styles.postTime, p.mine && { color: 'rgba(255,255,255,0.75)' }]}>{timeAgo(p.at)}</AppText>
              </View>
            </Pressable>
          ))}
        </ScrollView>
      </StateView>
      <View style={[styles.compose, { paddingBottom: Math.max(insets.bottom, 10) }]}>
        <TextInput value={draft} onChangeText={setDraft} placeholder="Write to the group…" placeholderTextColor={COLORS.gray500} multiline maxLength={2000} style={styles.input} />
        <Pressable onPress={send} disabled={!draft.trim() || sending} style={[styles.send, (!draft.trim() || sending) && { opacity: 0.45 }]} accessibilityRole="button" accessibilityLabel="Send">
          <Ionicons name="send" size={18} color={COLORS.white} />
        </Pressable>
      </View>
    </KeyboardAvoidingView>
  );
}

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

export default function GroupScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry, reload } = useFetch(`/groups/${id}`);
  const g = data?.data;
  const [tab, setTab] = useState(null);
  const [busy, setBusy] = useState(false);
  const [sheet, setSheet] = useState(null); // 'meeting' | 'notice' | 'add'
  const [meeting, setMeeting] = useState({ date: new Date().toISOString().slice(0, 10), topic: '', visitors: '0', present: {} });
  const [notice, setNotice] = useState({ title: '', body: '' });
  const [q, setQ] = useState('');
  const [people, setPeople] = useState([]);

  const current = tab ?? (g?.is_member ? 'chat' : 'about');
  const tabs = g?.is_member ? [['chat', 'Chat'], ['notices', 'Notices'], ['members', 'Members'], ['meetings', 'Meetings'], ['about', 'About']] : [['about', 'About']];

  async function act(run, fail) {
    setBusy(true);
    try {
      await run();
      await reload();
    } catch (e) {
      Alert.alert(fail, errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const join = () => act(() => api.post(`/groups/${id}/join`), 'Could not join');
  const leave = () => {
    const run = () => act(async () => { await api.delete(`/groups/${id}/leave`); }, 'Could not leave');
    if (Platform.OS === 'web') return run();
    Alert.alert('Leave this group?', undefined, [{ text: 'Stay', style: 'cancel' }, { text: 'Leave', style: 'destructive', onPress: run }]);
  };
  const member = (m, action) => act(() => api.put(`/groups/${id}/members/${m.membership_id}`, { action }), 'Could not update');

  function openMeeting() {
    setMeeting((m) => ({ ...m, present: Object.fromEntries(g.members.map((x) => [x.user_id, true])) }));
    setSheet('meeting');
  }
  const saveMeeting = () => act(async () => {
    await api.post(`/groups/${id}/meetings`, { meeting_date: meeting.date, topic: meeting.topic || null, visitors: Number(meeting.visitors) || 0, present: Object.entries(meeting.present).filter(([, on]) => on).map(([uid]) => Number(uid)) });
    setSheet(null);
  }, 'Could not save');
  const saveNotice = () => act(async () => {
    await api.post(`/groups/${id}/announcements`, notice);
    setNotice({ title: '', body: '' });
    setSheet(null);
  }, 'Could not post');

  useEffect(() => {
    if (sheet !== 'add') return undefined;
    const t = setTimeout(() => api.get(`/groups/${id}/candidates`, { params: { q } }).then((r) => setPeople(r.data.data)).catch(() => {}), 250);
    return () => clearTimeout(t);
  }, [sheet, q, id]);

  const add = (u) => act(async () => {
    await api.post(`/groups/${id}/members`, { user_id: u.id });
    setPeople((p) => p.filter((x) => x.id !== u.id));
  }, 'Could not add');

  return (
    <View style={styles.screen}>
      <ScreenHeader title={g?.name ?? 'Group'} subtitle={g ? `${g.type_label} · ${g.church.name}` : undefined} />
      <StateView loading={loading && !g} error={!g ? error : null} onRetry={retry}>
        {g ? (
          <View style={{ flex: 1 }}>
            <View style={styles.hero}>
              <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
              {g.cover_url ? <Image source={{ uri: mediaUrl(g.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" /> : null}
              <LinearGradient colors={['rgba(20,6,12,0.25)', 'rgba(20,6,12,0.75)']} style={StyleSheet.absoluteFill} />
              <View style={styles.heroInfo}>
                {g.schedule ? <AppText style={styles.heroLine}>🕒  {g.schedule}</AppText> : null}
                {g.location ? <AppText style={styles.heroLine}>📍  {g.location}</AppText> : null}
                <AppText style={styles.heroLine}>👥  {g.members_count} members{g.leader ? ` · led by ${g.leader.name}` : ''}</AppText>
              </View>
              {g.my_status === 'active' ? null : g.my_status === 'pending' ? (
                <View style={styles.pending}>
                  <AppText style={styles.pendingText}>Request sent — waiting for the leader</AppText>
                </View>
              ) : g.is_open ? (
                <Pressable onPress={join} disabled={busy} style={styles.joinBtn} accessibilityRole="button">
                  <AppText style={styles.joinText}>{busy ? '…' : 'Ask to join'}</AppText>
                </Pressable>
              ) : null}
            </View>

            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ flexGrow: 0 }} contentContainerStyle={styles.tabs}>
              {tabs.map(([key, label]) => (
                <Pressable key={key} onPress={() => setTab(key)} style={[styles.tab, current === key && styles.tabOn]} accessibilityRole="tab" accessibilityState={{ selected: current === key }}>
                  <AppText style={[styles.tabText, current === key && { color: COLORS.white }]}>{label}</AppText>
                </Pressable>
              ))}
            </ScrollView>

            {current === 'chat' ? <Conversation group={g} /> : (
              <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48, gap: 12 }} showsVerticalScrollIndicator={false}>
                {current === 'about' ? (
                  <>
                    <View style={styles.card}>
                      <AppText style={styles.h}>About this group</AppText>
                      <AppText style={styles.text}>{g.full_description ? g.full_description.replace(/<[^>]+>/g, '') : 'A place to grow together in faith.'}</AppText>
                    </View>
                    {g.my_status === 'active' ? <Button title="Leave group" variant="outline" onPress={leave} /> : null}
                  </>
                ) : null}

                {current === 'notices' ? (
                  <>
                    {g.can_lead ? <Button title="Post a notice" small onPress={() => setSheet('notice')} style={{ alignSelf: 'flex-start' }} /> : null}
                    {g.announcements.length === 0 ? <AppText variant="caption">No notices yet.</AppText> : null}
                    {g.announcements.map((a) => (
                      <View key={a.id} style={styles.card}>
                        <AppText style={styles.h}>{a.title}</AppText>
                        <AppText style={styles.text}>{a.body}</AppText>
                        <AppText variant="caption">{timeAgo(a.at)}</AppText>
                      </View>
                    ))}
                  </>
                ) : null}

                {current === 'members' ? (
                  <>
                    {g.can_lead ? <Button title="Add a member" small variant="outline" onPress={() => { setQ(''); setSheet('add'); }} style={{ alignSelf: 'flex-start' }} /> : null}
                    {g.pending_members.length ? (
                      <View style={styles.card}>
                        <AppText style={styles.h}>Waiting for approval</AppText>
                        {g.pending_members.map((m) => (
                          <View key={m.membership_id} style={styles.member}>
                            <Avatar person={m} size={40} />
                            <AppText style={[styles.memberName, { flex: 1 }]}>{m.name}</AppText>
                            <Pressable onPress={() => member(m, 'approve')} style={styles.approve} accessibilityRole="button"><AppText style={styles.approveText}>Approve</AppText></Pressable>
                            <Pressable onPress={() => member(m, 'remove')} hitSlop={8} accessibilityLabel={`Decline ${m.name}`}><Ionicons name="close-circle-outline" size={22} color={COLORS.gray500} /></Pressable>
                          </View>
                        ))}
                      </View>
                    ) : null}
                    <View style={styles.card}>
                      {g.members.map((m) => (
                        <View key={m.membership_id} style={styles.member}>
                          <Avatar person={m} size={40} />
                          <View style={{ flex: 1 }}>
                            <AppText style={styles.memberName}>{m.name}</AppText>
                            {m.role !== 'member' ? <AppText variant="caption" style={{ color: COLORS.primary }}>{m.role === 'leader' ? 'Leader' : 'Assistant'}</AppText> : null}
                          </View>
                          {g.can_lead && m.role === 'member' ? <Pressable onPress={() => member(m, 'remove')} hitSlop={8} accessibilityLabel={`Remove ${m.name}`}><Ionicons name="remove-circle-outline" size={22} color={COLORS.gray500} /></Pressable> : null}
                        </View>
                      ))}
                    </View>
                  </>
                ) : null}

                {current === 'meetings' ? (
                  <>
                    {g.can_lead ? <Button title="Record a meeting" small onPress={openMeeting} style={{ alignSelf: 'flex-start' }} /> : null}
                    {g.meetings.length === 0 ? <AppText variant="caption">No meetings recorded yet.</AppText> : null}
                    {g.meetings.map((m) => (
                      <View key={m.id} style={styles.card}>
                        <AppText style={styles.h}>{m.topic || 'Meeting'}</AppText>
                        <AppText style={styles.text}>{formatDate(m.date)} · {m.present} present{m.visitors ? ` · ${m.visitors} visitors` : ''}</AppText>
                      </View>
                    ))}
                  </>
                ) : null}
              </ScrollView>
            )}
          </View>
        ) : null}
      </StateView>

      <Sheet visible={sheet === 'notice'} onClose={() => setSheet(null)} title="Post a notice">
        <TextField label="Title" value={notice.title} onChangeText={(t) => setNotice((n) => ({ ...n, title: t }))} maxLength={200} />
        <TextField label="Message" value={notice.body} onChangeText={(t) => setNotice((n) => ({ ...n, body: t }))} multiline numberOfLines={5} maxLength={3000} style={{ minHeight: 100 }} />
        <Button title="Send to the group" onPress={saveNotice} loading={busy} disabled={!notice.title.trim() || !notice.body.trim()} />
      </Sheet>

      <Sheet visible={sheet === 'meeting'} onClose={() => setSheet(null)} title="Record a meeting">
        <ScrollView style={{ maxHeight: 440 }} keyboardShouldPersistTaps="handled">
          <TextField label="Date (YYYY-MM-DD)" value={meeting.date} onChangeText={(t) => setMeeting((m) => ({ ...m, date: t }))} placeholder="2026-10-14" />
          <TextField label="Topic" value={meeting.topic} onChangeText={(t) => setMeeting((m) => ({ ...m, topic: t }))} maxLength={200} />
          <TextField label="Visitors" value={meeting.visitors} onChangeText={(t) => setMeeting((m) => ({ ...m, visitors: t.replace(/\D/g, '') }))} keyboardType="number-pad" />
          <AppText style={styles.h}>Who was there?</AppText>
          {g?.members.map((m) => (
            <View key={m.user_id} style={styles.member}>
              <Avatar person={m} size={34} />
              <AppText style={[styles.memberName, { flex: 1 }]}>{m.name}</AppText>
              <Switch value={!!meeting.present[m.user_id]} onValueChange={(v) => setMeeting((x) => ({ ...x, present: { ...x.present, [m.user_id]: v } }))} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
            </View>
          ))}
        </ScrollView>
        <Button title="Save meeting" onPress={saveMeeting} loading={busy} style={{ marginTop: 10 }} />
      </Sheet>

      <Sheet visible={sheet === 'add'} onClose={() => setSheet(null)} title="Add a member">
        <TextField placeholder="Search church members" value={q} onChangeText={setQ} />
        <ScrollView style={{ maxHeight: 320 }} keyboardShouldPersistTaps="handled">
          {people.map((u) => (
            <View key={u.id} style={styles.member}>
              <Avatar person={u} size={36} />
              <AppText style={[styles.memberName, { flex: 1 }]}>{u.name}</AppText>
              <Pressable onPress={() => add(u)} style={styles.approve} accessibilityRole="button"><AppText style={styles.approveText}>Add</AppText></Pressable>
            </View>
          ))}
          {people.length === 0 ? <AppText variant="caption" style={{ textAlign: 'center', margin: 14 }}>No one to add.</AppText> : null}
        </ScrollView>
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  hero: { marginHorizontal: SPACING.md, borderRadius: RADIUS.xl - 4, overflow: 'hidden', padding: 14, gap: 10, backgroundColor: COLORS.primaryDark },
  heroInfo: { gap: 3 },
  heroLine: { fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.white },
  joinBtn: { alignSelf: 'flex-start', backgroundColor: COLORS.white, borderRadius: RADIUS.full, paddingHorizontal: 20, paddingVertical: 10 },
  joinText: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  pending: { alignSelf: 'flex-start', backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  pendingText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.white },
  tabs: { gap: 8, paddingHorizontal: SPACING.md, paddingVertical: 12 },
  tab: { paddingHorizontal: 15, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  tabOn: { backgroundColor: COLORS.primary },
  tabText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 6, ...SHADOW, shadowOpacity: 0.05 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray900 },
  member: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 7 },
  memberName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  approve: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 7 },
  approveText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.white },
  post: { flexDirection: 'row', alignItems: 'flex-end', gap: 8 },
  bubble: { maxWidth: '78%', borderRadius: 18, paddingHorizontal: 13, paddingVertical: 8 },
  mine: { backgroundColor: COLORS.primary, borderBottomRightRadius: 4 },
  theirs: { backgroundColor: COLORS.white, borderBottomLeftRadius: 4 },
  author: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.primary, marginBottom: 1 },
  postText: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 21, color: COLORS.black },
  postTime: { fontFamily: FONTS.regular, fontSize: 10.5, color: COLORS.gray500, alignSelf: 'flex-end', marginTop: 2 },
  compose: { flexDirection: 'row', alignItems: 'flex-end', gap: 10, paddingHorizontal: SPACING.md, paddingTop: 10, backgroundColor: COLORS.white, borderTopWidth: 1, borderTopColor: COLORS.gray100 },
  input: { flex: 1, maxHeight: 110, minHeight: 42, borderRadius: 21, backgroundColor: COLORS.canvas, paddingHorizontal: 16, paddingTop: 11, paddingBottom: 10, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black, ...Platform.select({ web: { outlineStyle: 'none' } }) },
  send: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.5)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 12 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black, textAlign: 'center', marginBottom: 14 },
});
