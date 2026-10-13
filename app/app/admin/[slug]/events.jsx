// Events: your church's events (past and future) and a form to create one — title, when, where, how many seats, free or
// ticketed, a cover picture. Publishing an event tells your followers. Tap an event for its guest list and door check-in.
// Data: /api/v1/admin/{church}/events.
import { useState } from 'react';
import { Alert, FlatList, Platform, Pressable, StyleSheet, Switch, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback } from 'react';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import Chip from '../../../components/Chip';
import ScreenHeader from '../../../components/ScreenHeader';
import Sheet from '../../../components/Sheet';
import StateView from '../../../components/StateView';
import TextField from '../../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { adminApi, joinDateTime, today } from '../../../lib/admin';
import api, { errorMessage, fieldErrors, mediaUrl } from '../../../lib/api';
import { dateTile, dayAndTime } from '../../../lib/format';
import { useFetch } from '../../../lib/useFetch';

const blank = () => ({ title: '', description: '', date: today(), time: '10:00', endTime: '', location: '', capacity: '', rsvp: true, qa: false, paid: false, ticketName: 'Standard', ticketPrice: '', draft: false, cover: null });

export default function AdminEvents() {
  const { slug } = useLocalSearchParams();
  const admin = adminApi(slug);
  const { data, loading, error, retry, reload } = useFetch(admin.path('/events'));
  const events = data?.data ?? [];
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(blank());
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  useFocusEffect(
    useCallback(() => {
      reload();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []),
  );

  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v }));

  async function pickCover() {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [16, 9], quality: 0.7 });
    if (!r.canceled && r.assets?.length) setForm((f) => ({ ...f, cover: r.assets[0] }));
  }

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      const body = {
        title: form.title.trim(), description: form.description.trim() || null,
        starts_at: joinDateTime(form.date, form.time), ends_at: form.endTime ? joinDateTime(form.date, form.endTime) : null,
        location_name: form.location.trim() || null, capacity: form.capacity ? Number(form.capacity) : null,
        rsvp_enabled: form.rsvp, qa_enabled: form.qa, is_paid: form.paid, status: form.draft ? 'draft' : 'published', visibility: 'public',
        ...(form.paid ? { tickets: [{ name: form.ticketName || 'Ticket', price: Number(form.ticketPrice) || 0 }] } : {}),
      };
      const { data: res } = await admin.post('/events', body);
      if (form.cover) {
        const f = new FormData();
        if (Platform.OS === 'web') f.append('cover', await (await fetch(form.cover.uri)).blob(), 'cover.jpg');
        else f.append('cover', { uri: form.cover.uri, name: 'cover.jpg', type: form.cover.mimeType || 'image/jpeg' });
        f.append('title', body.title);
        f.append('starts_at', body.starts_at);
        f.append('status', body.status);
        f.append('visibility', 'public');
        f.append('_method', 'PUT');
        await api.post(admin.path(`/events/${res.data.id}`), f, { headers: { 'Content-Type': 'multipart/form-data' }, transformRequest: (d) => d }).catch(() => {});
      }
      setOpen(false);
      setForm(blank());
      await reload();
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not save', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader
        title="Events"
        subtitle="Create events and check people in"
        right={
          <Pressable onPress={() => setOpen(true)} style={styles.add} accessibilityRole="button" accessibilityLabel="New event">
            <Ionicons name="add" size={20} color={COLORS.white} />
            <AppText style={styles.addText}>New</AppText>
          </Pressable>
        }
      />
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !events.length} emptyIcon="calendar-outline" emptyTitle="No events yet" emptyAction={{ title: 'Create an event', onPress: () => setOpen(true) }}>
        <FlatList
          data={events}
          keyExtractor={(e) => String(e.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 10 }}
          renderItem={({ item: e }) => {
            const tile = dateTile(e.starts_at);
            return (
              <Pressable onPress={() => router.push({ pathname: '/admin/[slug]/event/[id]', params: { slug, id: String(e.id) } })} style={styles.card} accessibilityRole="button" accessibilityLabel={`${e.title}, ${e.going} going`}>
                <View style={styles.tile}>
                  <AppText style={styles.tileMonth}>{tile.month}</AppText>
                  <AppText style={styles.tileDay}>{tile.day}</AppText>
                </View>
                <View style={{ flex: 1, gap: 2 }}>
                  <AppText style={styles.title} numberOfLines={2}>{e.title}</AppText>
                  <AppText variant="caption" numberOfLines={1}>{[dayAndTime(e.starts_at), e.location].filter(Boolean).join(' · ')}</AppText>
                  <AppText variant="caption" style={{ color: e.status === 'published' ? COLORS.success : COLORS.warning, fontFamily: FONTS.semibold }}>{e.status} · {e.going} going{e.is_paid ? ' · ticketed' : ''}</AppText>
                </View>
                <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
              </Pressable>
            );
          }}
        />
      </StateView>

      <Sheet visible={open} onClose={() => setOpen(false)} title="New event" tall>
        <Pressable onPress={pickCover} style={styles.cover} accessibilityRole="button" accessibilityLabel="Choose a cover picture">
          {form.cover ? <Image source={{ uri: form.cover.uri }} style={StyleSheet.absoluteFill} contentFit="cover" /> : (
            <View style={{ alignItems: 'center', gap: 4 }}>
              <Ionicons name="image-outline" size={26} color={COLORS.primary} />
              <AppText style={{ fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary }}>Add a cover picture (optional)</AppText>
              <AppText variant="caption">Without one, a picture is made for you.</AppText>
            </View>
          )}
        </Pressable>
        <TextField label="Title" value={form.title} onChangeText={set('title')} error={errors.title} />
        <TextField label="About" value={form.description} onChangeText={set('description')} multiline numberOfLines={3} style={{ minHeight: 80 }} />
        <TextField label="Date (YYYY-MM-DD)" value={form.date} onChangeText={set('date')} error={errors.starts_at} />
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1 }}><TextField label="Starts (HH:MM)" value={form.time} onChangeText={set('time')} placeholder="10:00" /></View>
          <View style={{ flex: 1 }}><TextField label="Ends (optional)" value={form.endTime} onChangeText={set('endTime')} placeholder="13:00" error={errors.ends_at} /></View>
        </View>
        <TextField label="Where" value={form.location} onChangeText={set('location')} placeholder="Main sanctuary" />
        <TextField label="Seats (leave empty for unlimited)" value={form.capacity} onChangeText={(t) => set('capacity')(t.replace(/\D/g, ''))} keyboardType="number-pad" />
        {[['rsvp', 'Let people reserve a seat'], ['qa', 'Questions for the speakers'], ['paid', 'Ticketed event'], ['draft', 'Save as a draft']].map(([key, label]) => (
          <View key={key} style={styles.switch}>
            <AppText style={styles.switchTitle}>{label}</AppText>
            <Switch value={form[key]} onValueChange={set(key)} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
          </View>
        ))}
        {form.paid ? (
          <View style={{ flexDirection: 'row', gap: 10, marginTop: 6 }}>
            <View style={{ flex: 1.4 }}><TextField label="Ticket name" value={form.ticketName} onChangeText={set('ticketName')} /></View>
            <View style={{ flex: 1 }}><TextField label="Price (UGX)" value={form.ticketPrice} onChangeText={(t) => set('ticketPrice')(t.replace(/\D/g, ''))} keyboardType="number-pad" /></View>
          </View>
        ) : null}
        <Button title={form.draft ? 'Save draft' : 'Publish event'} onPress={save} loading={busy} disabled={!form.title.trim()} style={{ marginTop: 8 }} />
      </Sheet>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  add: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 14, paddingVertical: 8 },
  addText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.white },
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, ...SHADOW, shadowOpacity: 0.05 },
  tile: { width: 54, paddingVertical: 7, borderRadius: 14, backgroundColor: COLORS.primaryMist, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 25, color: COLORS.black },
  title: { fontFamily: FONTS.bold, fontSize: 15.5, color: COLORS.black },
  cover: { height: 130, borderRadius: RADIUS.lg, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', marginBottom: 14 },
  switch: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 7 },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
});
