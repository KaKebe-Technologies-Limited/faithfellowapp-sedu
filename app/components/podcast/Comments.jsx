// What listeners say: a box to write a comment and the comments under it, newest first. Used under the Podcast radio, under a podcast channel of a church
// and under a podcast episode (`base` is the address the comments belong to, for example /podcast-episodes/12 or /radio/stations/podcast). It is shown in the sheet
// that the Comments button opens (CommentsButton).
// A comment can be reported by anyone and removed by its author, by the podcast's maker and by the platform. No phone numbers, emails or links.
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, MAX_FONT_SCALE, RADIUS, SPACING } from '../../constants/theme';
import { PURPLE } from '../../constants/podcastTheme';
import api, { errorMessage } from '../../lib/api';
import { timeAgo } from '../../lib/format';
import AppText from '../AppText';
import Avatar from '../Avatar';

const REASONS = [
  ['spam', 'Spam or scam'],
  ['abuse', 'Harassment or abuse'],
  ['inappropriate', 'Inappropriate'],
];

/**
 * `limit`: comments per page. `dark`: for the dark player screens. `hideTitle`: when the sheet around it already says "Comments".
 */
export default function Comments({ base, limit = 15, dark = false, title = 'Comments', hideTitle = false }) {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ total: 0, current_page: 0, last_page: 1 });
  const [loading, setLoading] = useState(true);
  const [more, setMore] = useState(false);
  const [text, setText] = useState('');
  const [posting, setPosting] = useState(false);
  const [problem, setProblem] = useState(null);

  const load = useCallback(
    async (page) => {
      try {
        const { data } = await api.get(`${base}/comments`, { params: { per_page: limit, page } });
        setItems((cur) => (page === 1 ? data.data : [...cur, ...data.data.filter((c) => !cur.some((x) => x.id === c.id))]));
        setMeta(data.meta);
      } catch {
        // The box stays empty; writing still works once the connection is back.
      } finally {
        setLoading(false);
        setMore(false);
      }
    },
    [base, limit],
  );

  useEffect(() => {
    setLoading(true);
    setItems([]);
    load(1);
  }, [load]);

  async function send() {
    const body = text.trim();
    if (body.length < 2 || posting) return;
    setPosting(true);
    setProblem(null);
    try {
      const { data } = await api.post(`${base}/comments`, { body });
      setItems((cur) => [data.data, ...cur]);
      setMeta((m) => ({ ...m, total: m.total + 1 }));
      setText('');
    } catch (e) {
      setProblem(errorMessage(e));
    } finally {
      setPosting(false);
    }
  }

  function remove(comment) {
    Alert.alert('Delete this comment?', undefined, [
      { text: 'Keep it', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: async () => {
          try {
            await api.delete(`/podcast-comments/${comment.id}`);
            setItems((cur) => cur.filter((c) => c.id !== comment.id));
            setMeta((m) => ({ ...m, total: Math.max(0, m.total - 1) }));
          } catch (e) {
            Alert.alert('Could not delete', errorMessage(e));
          }
        },
      },
    ]);
  }

  function report(comment) {
    Alert.alert('Report this comment', 'Why are you reporting it?', [
      ...REASONS.map(([reason, label]) => ({
        text: label,
        onPress: async () => {
          try {
            await api.post(`/podcast-comments/${comment.id}/report`, { reason });
            setItems((cur) => cur.filter((c) => c.id !== comment.id));
            Alert.alert('Thank you', 'We will look at this comment. It is hidden while we do.');
          } catch (e) {
            Alert.alert('Could not report', errorMessage(e));
          }
        },
      })),
      { text: 'Cancel', style: 'cancel' },
    ]);
  }

  function menu(comment) {
    const actions = [];
    if (!comment.mine) actions.push({ text: 'Report', onPress: () => report(comment) });
    if (comment.can_delete) actions.push({ text: 'Delete', style: 'destructive', onPress: () => remove(comment) });
    Alert.alert('Comment', undefined, [...actions, { text: 'Cancel', style: 'cancel' }]);
  }

  const ink = dark ? '#fff' : COLORS.black;
  const soft = dark ? 'rgba(255,255,255,0.62)' : COLORS.gray700;
  const canSend = text.trim().length >= 2 && !posting;

  return (
    <View>
      {hideTitle ? null : (
        <View style={styles.head}>
          <Ionicons name="chatbubbles" size={18} color={dark ? PURPLE.pale : PURPLE.main} />
          <AppText style={[styles.title, { color: ink }]}>
            {title}
            {meta.total ? <AppText style={{ color: soft, fontFamily: FONTS.medium }}>{`  ${meta.total}`}</AppText> : null}
          </AppText>
        </View>
      )}

      <View style={[styles.composer, dark ? styles.composerDark : styles.composerLight]}>
        <TextInput
          maxFontSizeMultiplier={MAX_FONT_SCALE}
          value={text}
          onChangeText={setText}
          placeholder="Write a comment…"
          placeholderTextColor={dark ? 'rgba(255,255,255,0.45)' : COLORS.gray500}
          multiline
          numberOfLines={1}
          maxLength={600}
          style={[styles.input, { color: ink }]}
          accessibilityLabel="Write a comment"
        />
        <Pressable onPress={send} disabled={!canSend} accessibilityRole="button" accessibilityLabel="Send the comment" style={[styles.send, !canSend && { opacity: 0.4 }]}>
          {posting ? <ActivityIndicator color="#fff" size="small" /> : <Ionicons name="send" size={17} color="#fff" />}
        </Pressable>
      </View>
      {problem ? <AppText style={styles.problem}>{problem}</AppText> : null}
      <AppText style={[styles.hint, { color: soft }]}>Be kind. No phone numbers, emails or links.</AppText>

      {loading ? (
        <ActivityIndicator color={PURPLE.main} style={{ marginTop: SPACING.md }} />
      ) : items.length === 0 ? (
        <AppText style={[styles.empty, { color: soft }]}>No comments yet. Be the first to share what this meant to you.</AppText>
      ) : (
        items.map((c) => (
          <View key={c.id} style={styles.comment}>
            <Avatar person={{ name: c.author.name, avatar_url: c.author.avatar_url }} size={36} />
            <View style={{ flex: 1 }}>
              <View style={styles.who}>
                <AppText style={[styles.name, { color: ink }]} numberOfLines={1}>
                  {c.author.name}
                </AppText>
                <AppText style={[styles.when, { color: soft }]}>{timeAgo(c.created_at)}</AppText>
              </View>
              <AppText style={[styles.body, { color: dark ? 'rgba(255,255,255,0.88)' : COLORS.gray900 }]}>{c.body}</AppText>
            </View>
            <Pressable onPress={() => menu(c)} hitSlop={10} accessibilityRole="button" accessibilityLabel="Comment options" style={styles.more}>
              <Ionicons name="ellipsis-horizontal" size={18} color={soft} />
            </Pressable>
          </View>
        ))
      )}

      {meta.current_page < meta.last_page ? (
        <Pressable
          onPress={() => {
            setMore(true);
            load(meta.current_page + 1);
          }}
          accessibilityRole="button"
          style={styles.seeAll}
        >
          {more ? <ActivityIndicator color={PURPLE.main} /> : <AppText style={[styles.seeAllText, dark && { color: PURPLE.pale }]}>Show older comments</AppText>}
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 },
  title: { fontFamily: FONTS.bold, fontSize: 18 },
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: 8, borderRadius: RADIUS.lg, paddingLeft: 14, paddingRight: 8, paddingVertical: 8 },
  composerLight: { backgroundColor: COLORS.white, borderWidth: 1, borderColor: PURPLE.pale },
  composerDark: { backgroundColor: 'rgba(255,255,255,0.08)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.14)' },
  input: { flex: 1, minHeight: 38, maxHeight: 120, fontFamily: FONTS.regular, fontSize: 15, paddingTop: 8, paddingBottom: 8, textAlignVertical: 'center' },
  send: { width: 38, height: 38, borderRadius: 19, backgroundColor: PURPLE.main, alignItems: 'center', justifyContent: 'center' },
  problem: { fontFamily: FONTS.medium, fontSize: 13, color: '#E0536B', marginTop: 6 },
  hint: { fontFamily: FONTS.regular, fontSize: 11.5, marginTop: 6, marginBottom: 6 },
  empty: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 21, textAlign: 'center', paddingVertical: SPACING.md },
  comment: { flexDirection: 'row', gap: 10, paddingVertical: 10 },
  who: { flexDirection: 'row', alignItems: 'baseline', gap: 8 },
  name: { fontFamily: FONTS.semibold, fontSize: 14, flexShrink: 1 },
  when: { fontFamily: FONTS.regular, fontSize: 12 },
  body: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, marginTop: 2 },
  more: { paddingTop: 2, paddingLeft: 4 },
  seeAll: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 4, paddingVertical: 12 },
  seeAllText: { fontFamily: FONTS.semibold, fontSize: 14, color: PURPLE.main },
});
