// Platform admin → People: every registered person, found by name, email or phone number. "Add to a church" puts them into one or more churches at once
// (they follow it straight away, whatever the church's approval setting says), and can tell them with a welcome.
// Data: GET /api/v1/platform/users, GET /api/v1/platform/churches?status=active and POST /api/v1/platform/users/{id}/churches.
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import ScreenHeader from '../../components/ScreenHeader';
import Sheet from '../../components/Sheet';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING, MAX_FONT_SCALE } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { formatDate } from '../../lib/format';
import { usePaged } from '../../lib/usePaged';

/** The window that picks the churches to put one person into. */
function AddToChurch({ person, onClose, onDone }) {
  const [q, setQ] = useState('');
  const [churches, setChurches] = useState([]);
  const [picked, setPicked] = useState({});
  const [tell, setTell] = useState(false);
  const [busy, setBusy] = useState(false);
  const already = new Set((person?.churches ?? []).map((c) => c.id));

  useEffect(() => {
    if (!person) return undefined;
    setPicked({});
    setQ('');
    setTell(false);
    return undefined;
  }, [person]);

  useEffect(() => {
    if (!person) return undefined;
    const t = setTimeout(() => {
      api.get('/platform/churches', { params: { status: 'active', q: q.trim() || undefined } }).then(({ data }) => setChurches(data.data)).catch(() => setChurches([]));
    }, 300);
    return () => clearTimeout(t);
  }, [person, q]);

  const ids = Object.keys(picked).filter((id) => picked[id]).map(Number);

  async function save() {
    setBusy(true);
    try {
      const { data } = await api.post(`/platform/users/${person.id}/churches`, { church_ids: ids, notify: tell });
      onDone(data.message, data.data);
    } catch (e) {
      Alert.alert('Could not add', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Sheet visible={!!person} onClose={onClose} title={person ? `Add ${person.name.split(' ')[0]} to a church` : ''} tall>
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={q} onChangeText={setQ} placeholder="Find a church" placeholderTextColor={COLORS.gray500} style={styles.input} />
      </View>
      <View style={{ gap: 8, marginVertical: 12 }}>
        {churches.map((c) => {
          const has = already.has(c.id);
          const on = !!picked[c.id];

          return (
            <Pressable key={c.id} disabled={has} onPress={() => setPicked((p) => ({ ...p, [c.id]: !p[c.id] }))} style={[styles.pick, on && styles.pickOn, has && { opacity: 0.55 }]} accessibilityRole="checkbox" accessibilityState={{ checked: on || has, disabled: has }} accessibilityLabel={c.name}>
              <ChurchAvatar church={c} size={38} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.pickName} numberOfLines={1}>
                  {c.name}
                </AppText>
                <AppText variant="caption" numberOfLines={1}>
                  {has ? 'Already follows' : c.city ?? ''}
                </AppText>
              </View>
              <Ionicons name={on || has ? 'checkbox' : 'square-outline'} size={22} color={on || has ? COLORS.primary : COLORS.gray300} />
            </Pressable>
          );
        })}
        {!churches.length ? <AppText variant="caption" style={{ textAlign: 'center' }}>No church matches.</AppText> : null}
      </View>
      <View style={styles.tell}>
        <AppText style={{ flex: 1, fontFamily: FONTS.medium, fontSize: 14, color: COLORS.black }}>Send them a welcome message</AppText>
        <Switch value={tell} onValueChange={setTell} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.onColor} />
      </View>
      <Button title={ids.length ? `Add to ${ids.length} ${ids.length === 1 ? 'church' : 'churches'}` : 'Tick a church'} onPress={save} loading={busy} disabled={!ids.length} />
    </Sheet>
  );
}

export default function PlatformPeople() {
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [adding, setAdding] = useState(null);
  const [notice, setNotice] = useState(null);
  const list = usePaged('/platform/users', { q: search || undefined });

  return (
    <View style={styles.screen}>
      <ScreenHeader title="People" subtitle="Everyone who registered" />
      <View style={{ paddingHorizontal: SPACING.md, gap: 10, paddingBottom: 8 }}>
        <View style={styles.search}>
          <Ionicons name="search" size={18} color={COLORS.gray700} />
          <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={query} onChangeText={setQuery} onSubmitEditing={() => setSearch(query.trim())} returnKeyType="search" autoCapitalize="none" placeholder="Name, email or phone number" placeholderTextColor={COLORS.gray500} style={styles.input} />
        </View>
        {notice ? (
          <Pressable onPress={() => setNotice(null)} style={styles.notice} accessibilityRole="button" accessibilityLabel="Dismiss message">
            <Ionicons name="checkmark-circle" size={18} color={COLORS.success} />
            <AppText style={styles.noticeText}>{notice}</AppText>
          </Pressable>
        ) : null}
      </View>

      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length} emptyIcon="people-outline" emptyTitle="Nobody found">
        <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingTop: 4, gap: 10, paddingBottom: 48 }} keyboardShouldPersistTaps="handled">
          {list.items.map((u) => (
            <View key={u.id} style={styles.card}>
              <View style={styles.row}>
                <Avatar person={u} size={46} />
                <View style={{ flex: 1, gap: 2 }}>
                  <AppText style={styles.name} numberOfLines={1}>
                    {u.name}
                  </AppText>
                  <AppText variant="caption" numberOfLines={1}>
                    {[u.email, u.phone].filter(Boolean).join(' · ') || '—'}
                  </AppText>
                  <AppText variant="caption">Joined {formatDate(u.joined_at)}{u.status !== 'active' ? ` · ${u.status}` : ''}</AppText>
                </View>
              </View>
              {u.churches.length ? (
                <View style={styles.wrap}>
                  {u.churches.map((c) => (
                    <View key={c.id} style={styles.tag}>
                      <AppText style={styles.tagText} numberOfLines={1}>
                        {c.name}
                      </AppText>
                    </View>
                  ))}
                </View>
              ) : (
                <AppText variant="caption">Follows no church yet</AppText>
              )}
              {u.status === 'active' ? (
                <Pressable onPress={() => setAdding(u)} style={styles.add} accessibilityRole="button" accessibilityLabel={`Add ${u.name} to a church`}>
                  <Ionicons name="add-circle-outline" size={18} color={COLORS.primary} />
                  <AppText style={styles.addText}>Add to a church</AppText>
                </Pressable>
              ) : null}
            </View>
          ))}
          {list.hasMore ? (
            <Pressable onPress={list.loadMore} style={styles.more} accessibilityRole="button">
              <AppText style={styles.moreText}>Load more</AppText>
            </Pressable>
          ) : null}
        </ScrollView>
      </StateView>

      <AddToChurch
        person={adding}
        onClose={() => setAdding(null)}
        onDone={(message) => {
          setAdding(null);
          setNotice(message);
          list.reload();
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  input: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  notice: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: 10 },
  noticeText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.black },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, gap: 8, ...SHADOW },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  name: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  tag: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 4, maxWidth: '100%' },
  tagText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },
  add: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start', paddingVertical: 4 },
  addText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primary },
  pick: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10, borderRadius: RADIUS.md, borderWidth: 1.5, borderColor: COLORS.gray100 },
  pickOn: { borderColor: COLORS.primary, backgroundColor: COLORS.selected },
  pickName: { fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.black },
  tell: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 14 },
  more: { alignSelf: 'center', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  moreText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
});
