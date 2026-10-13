// The notebook: every note you have written (Bible chapters, sermons, devotionals, free notes), searchable,
// filterable by kind, pinned ones first. "New note" opens the editor.
import { useState } from 'react';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import AppText from '../components/AppText';
import PagedScreen from '../components/PagedScreen';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { excerpt, timeAgo } from '../lib/format';

const KINDS = [
  { key: '', label: 'All' },
  { key: 'bible', label: 'Bible' },
  { key: 'plan', label: 'Plans' },
  { key: 'sermon', label: 'Sermons' },
  { key: 'devotional', label: 'Devotionals' },
  { key: 'note', label: 'My notes' },
];

export default function NotesScreen() {
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [kind, setKind] = useState('');

  const header = (
    <View style={{ paddingHorizontal: SPACING.md, gap: 10, paddingBottom: 10 }}>
      <View style={styles.search}>
        <Ionicons name="search" size={18} color={COLORS.gray700} />
        <TextInput value={query} onChangeText={setQuery} onSubmitEditing={() => setSearch(query.trim())} returnKeyType="search" placeholder="Search your notes" placeholderTextColor={COLORS.gray500} style={styles.input} />
      </View>
      <View style={styles.chips}>
        {KINDS.map((k) => (
          <Pressable key={k.key} onPress={() => setKind(k.key)} style={[styles.chip, kind === k.key && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: kind === k.key }}>
            <AppText style={[styles.chipText, kind === k.key && { color: COLORS.white }]}>{k.label}</AppText>
          </Pressable>
        ))}
      </View>
    </View>
  );

  return (
    <PagedScreen
      title="My notes"
      subtitle="Private to you"
      path="/notes"
      params={{ q: search || undefined, kind: kind || undefined }}
      header={header}
      emptyIcon="create-outline"
      emptyTitle={search || kind ? 'No notes match' : 'No notes yet'}
      emptyMessage="Write what God is showing you — in a Bible chapter, a sermon, or on its own."
      right={
        <Pressable onPress={() => router.push({ pathname: '/note/[id]', params: { id: 'new' } })} style={styles.add} accessibilityRole="button" accessibilityLabel="New note">
          <Ionicons name="add" size={20} color={COLORS.white} />
          <AppText style={styles.addText}>New</AppText>
        </Pressable>
      }
      renderItem={(n) => (
        <Pressable onPress={() => router.push({ pathname: '/note/[id]', params: { id: String(n.id) } })} style={[styles.card, { backgroundColor: n.color_hex }]} accessibilityRole="button" accessibilityLabel={`Note: ${n.display_title}`}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <AppText style={styles.kind}>{n.kind_label}</AppText>
            {n.pinned ? <Ionicons name="pin" size={13} color={COLORS.primary} /> : null}
            <AppText variant="caption" style={{ marginLeft: 'auto' }}>
              {timeAgo(n.updated_at)}
            </AppText>
          </View>
          <AppText style={styles.title} numberOfLines={1}>
            {n.display_title}
          </AppText>
          {n.body ? (
            <AppText style={styles.body} numberOfLines={3}>
              {excerpt(n.body.replace(/^[#>•*\s-]+/gm, '').replace(/[*_]{1,2}/g, ''), 200)}
            </AppText>
          ) : null}
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  input: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 13, paddingVertical: 7, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
  card: { borderRadius: RADIUS.lg, padding: 14, marginHorizontal: SPACING.md, marginBottom: 10, gap: 4, ...SHADOW, shadowOpacity: 0.04 },
  kind: { fontFamily: FONTS.bold, fontSize: 11.5, color: COLORS.primary, textTransform: 'uppercase', letterSpacing: 0.6 },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  body: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 20, color: COLORS.gray900 },
});
