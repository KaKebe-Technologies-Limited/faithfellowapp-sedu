// Updates with photos: post a few lines and up to six pictures to your church's feed (everyone, or members only),
// pin the best one, hide it, or delete it. Followers are told about a new post. Data: /api/v1/admin/{church}/posts.
import { useCallback, useState } from 'react';
import { Alert, FlatList, Platform, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useFocusEffect, useLocalSearchParams } from 'expo-router';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi } from '../../../lib/admin';
import { errorMessage, fieldErrors, mediaUrl } from '../../../lib/api';
import { timeAgo } from '../../../lib/format';
import { appendPhoto, multipart, pickPhotos } from '../../../lib/upload';
import { useFetch } from '../../../lib/useFetch';

const MAX_PHOTOS = 6;
const blank = () => ({ body: '', visibility: 'public', photos: [] });

export default function AdminPosts() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const { data, loading, error, retry, reload } = useFetch(admin.path('/posts'));
  const list = data?.data ?? [];
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(blank());
  const [editing, setEditing] = useState(null);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  function openNew() {
    setEditing(null);
    setForm(blank());
    setErrors({});
    setOpen(true);
  }

  function openEdit(post) {
    setEditing(post);
    setForm({ body: post.body ?? '', visibility: post.visibility, photos: [] });
    setErrors({});
    setOpen(true);
  }

  async function addPhotos() {
    const room = MAX_PHOTOS - form.photos.length;
    if (room <= 0) return;
    const picked = await pickPhotos({ multiple: true, limit: room });
    if (picked.length) setForm((f) => ({ ...f, photos: [...f.photos, ...picked].slice(0, MAX_PHOTOS) }));
  }

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      if (editing) {
        await admin.put(`/posts/${editing.id}`, { body: form.body.trim(), visibility: form.visibility });
      } else {
        const body = new FormData();
        body.append('body', form.body.trim());
        body.append('visibility', form.visibility);
        for (const [i, photo] of form.photos.entries()) await appendPhoto(body, 'images[]', photo, `photo${i + 1}.jpg`);
        await admin.post('/posts', body, multipart);
      }
      setOpen(false);
      await reload();
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not post', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function change(post, patch) {
    try {
      await admin.put(`/posts/${post.id}`, patch);
      await reload();
    } catch (e) {
      Alert.alert('Could not do that', errorMessage(e));
    }
  }

  function remove(post) {
    const run = async () => {
      await admin.delete(`/posts/${post.id}`).catch(() => {});
      reload();
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Delete this update?', undefined, [{ text: 'Keep', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: run }]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Updates & photos"
        subtitle="Share what is happening"
        right={
          <Pressable onPress={openNew} style={styles.add} accessibilityRole="button" accessibilityLabel="New update">
            <Ionicons name="add" size={20} color={COLORS.white} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <StateView loading={loading && !data} error={!data ? error : null} onRetry={retry} empty={!loading && !error && !list.length} emptyIcon="images-outline" emptyTitle="No updates yet" emptyMessage="Post a photo and a few words, and your members see it in their feed." emptyAction={{ title: 'Post an update', onPress: openNew }}>
        <FlatList
          data={list}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 12, paddingBottom: 48 }}
          renderItem={({ item: p }) => (
            <View style={styles.card}>
              {p.images.length ? (
                <View style={styles.photo}>
                  <Image source={{ uri: mediaUrl(p.images[0]) }} style={StyleSheet.absoluteFill} contentFit="cover" />
                  {p.images.length > 1 ? (
                    <View style={styles.count}>
                      <Ionicons name="images" size={13} color={COLORS.white} />
                      <AppText style={styles.countText}>{p.images.length}</AppText>
                    </View>
                  ) : null}
                </View>
              ) : null}
              {p.body ? <AppText style={styles.body} numberOfLines={4}>{p.body}</AppText> : null}
              <AppText variant="caption" style={{ color: p.status === 'hidden' ? COLORS.warning : COLORS.gray700, fontFamily: FONTS.semibold }}>
                {[p.scheduled ? `Goes live ${timeAgo(p.published_at)}` : timeAgo(p.published_at), p.visibility === 'members' ? 'Members only' : 'Everyone', p.is_pinned ? 'Pinned' : null, p.status === 'hidden' ? 'Hidden' : null].filter(Boolean).join(' · ')}
              </AppText>
              <View style={styles.actions}>
                <Button title="Edit" small variant="outline" onPress={() => openEdit(p)} />
                <Button title={p.is_pinned ? 'Unpin' : 'Pin'} small variant="outline" onPress={() => change(p, { is_pinned: !p.is_pinned })} />
                <Button title={p.status === 'hidden' ? 'Show' : 'Hide'} small variant="outline" onPress={() => change(p, { status: p.status === 'hidden' ? 'published' : 'hidden' })} />
                <Pressable onPress={() => remove(p)} hitSlop={8} accessibilityLabel="Delete update" style={{ marginLeft: 'auto' }}>
                  <Ionicons name="trash-outline" size={20} color={COLORS.gray500} />
                </Pressable>
              </View>
            </View>
          )}
        />
      </StateView>

      <Sheet visible={open} onClose={() => setOpen(false)} title={editing ? 'Edit update' : 'New update'} tall>
        {!editing ? (
          <View style={styles.thumbs}>
            {form.photos.map((photo, i) => (
              <View key={photo.uri + i} style={styles.thumb}>
                <Image source={{ uri: photo.uri }} style={StyleSheet.absoluteFill} contentFit="cover" />
                <Pressable onPress={() => setForm((f) => ({ ...f, photos: f.photos.filter((_, j) => j !== i) }))} style={styles.remove} accessibilityLabel={`Remove photo ${i + 1}`} hitSlop={6}>
                  <Ionicons name="close" size={14} color={COLORS.white} />
                </Pressable>
              </View>
            ))}
            {form.photos.length < MAX_PHOTOS ? (
              <Pressable onPress={addPhotos} style={[styles.thumb, styles.addPhoto]} accessibilityRole="button" accessibilityLabel="Add photos">
                <Ionicons name="camera-outline" size={24} color={COLORS.primary} />
                <AppText style={styles.addPhotoText}>{form.photos.length ? 'Add more' : 'Add photos'}</AppText>
              </Pressable>
            ) : null}
          </View>
        ) : null}
        <TextField label={form.photos.length || editing?.images?.length ? 'Caption' : 'What is happening?'} value={form.body} onChangeText={(t) => setForm((f) => ({ ...f, body: t }))} multiline numberOfLines={4} style={{ minHeight: 100 }} error={errors.body} maxLength={5000} />
        <AppText style={styles.label}>Who can see it</AppText>
        <View style={styles.wrap}>
          <Chip label="Everyone" on={form.visibility === 'public'} onPress={() => setForm((f) => ({ ...f, visibility: 'public' }))} />
          <Chip label="Members only" on={form.visibility === 'members'} onPress={() => setForm((f) => ({ ...f, visibility: 'members' }))} />
        </View>
        {errors.images || errors['images.0'] ? <AppText style={styles.err}>{errors.images || errors['images.0']}</AppText> : null}
        <Button title={editing ? 'Save changes' : 'Post to my church'} onPress={save} loading={busy} disabled={!form.body.trim() && !form.photos.length} />
        {!editing ? <AppText variant="caption" style={{ textAlign: 'center', marginTop: 8 }}>Your followers get a notification.</AppText> : null}
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, gap: 8, ...SHADOW, shadowOpacity: 0.05 },
  photo: { height: 190, borderRadius: RADIUS.lg, overflow: 'hidden', backgroundColor: COLORS.gray100 },
  count: { position: 'absolute', right: 10, bottom: 10, flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: 'rgba(0,0,0,0.55)', borderRadius: RADIUS.full, paddingHorizontal: 9, paddingVertical: 4 },
  countText: { fontFamily: FONTS.bold, fontSize: 12, color: COLORS.white },
  body: { fontFamily: FONTS.regular, fontSize: 15.5, lineHeight: 22, color: COLORS.black },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  thumbs: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  thumb: { width: 92, height: 92, borderRadius: RADIUS.md, overflow: 'hidden', backgroundColor: COLORS.gray100 },
  addPhoto: { backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', gap: 2 },
  addPhotoText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.primary },
  remove: { position: 'absolute', top: 5, right: 5, width: 22, height: 22, borderRadius: 11, backgroundColor: 'rgba(0,0,0,0.6)', alignItems: 'center', justifyContent: 'center' },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  err: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.danger, marginBottom: 8 },
});
