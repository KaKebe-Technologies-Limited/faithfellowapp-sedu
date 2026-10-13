// The form for creating or editing a church event, in a sheet: title, when, where (or online), seats, one ticket price,
// who can see it, published / draft / cancelled, and a cover picture. Pass `event` (from GET …/events/{id}) to edit.
// Used by the Events list (create) and the event screen (edit). Data: /api/v1/admin/{church}/events.
import { useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, Switch, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import AppText from './AppText';
import Button from './Button';
import Chip from './Chip';
import Sheet from './Sheet';
import TextField from './TextField';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import { adminApi, joinDateTime, today } from '../lib/admin';
import api, { errorMessage, fieldErrors, mediaUrl } from '../lib/api';
import { appendPhoto, multipart, pickPhotos } from '../lib/upload';

const blank = () => ({ title: '', description: '', date: today(), time: '10:00', endDate: null, endTime: '', location: '', online: false, onlineUrl: '', capacity: '', rsvp: true, qa: false, paid: false, ticketName: 'Standard', ticketPrice: '', ticketId: null, manyTickets: false, status: 'published', visibility: 'public', cover: null, coverUrl: null });

/** The editor's values from an event returned by the admin API. */
function fromEvent(e) {
  const [date, time] = String(e.starts_local ?? '').split(' ');
  const [endDate, endTime] = String(e.ends_local ?? '').split(' ');
  const ticket = e.tickets?.[0];
  return {
    ...blank(),
    title: e.title ?? '', description: e.description ?? '', date: date || today(), time: time || '10:00',
    endDate: endDate && endDate !== date ? endDate : null, endTime: endTime ?? '',
    location: e.location ?? '', online: !!e.is_online, onlineUrl: e.online_url ?? '', capacity: e.capacity ? String(e.capacity) : '',
    rsvp: !!e.rsvp_enabled, qa: !!e.qa_enabled, paid: !!e.is_paid,
    ticketName: ticket?.name ?? 'Standard', ticketPrice: ticket ? String(Math.round(ticket.price)) : '', ticketId: ticket?.id ?? null, manyTickets: (e.tickets?.length ?? 0) > 1,
    status: e.status ?? 'published', visibility: e.visibility ?? 'public', coverUrl: e.cover_url ?? null,
  };
}

export default function EventForm({ visible, onClose, slug, event = null, onSaved }) {
  const admin = adminApi(slug);
  const [form, setForm] = useState(blank());
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const editing = !!event;

  // Start from the event being edited (or an empty form) each time the sheet opens.
  useEffect(() => {
    if (visible) {
      setForm(event ? fromEvent(event) : blank());
      setErrors({});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible]);

  const set = (k) => (v) => setForm((f) => ({ ...f, [k]: v }));

  async function pickCover() {
    const [photo] = await pickPhotos({ aspect: [16, 9] });
    if (photo) setForm((f) => ({ ...f, cover: photo }));
  }

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      const body = {
        title: form.title.trim(), description: form.description.trim() || null,
        starts_at: joinDateTime(form.date, form.time), ends_at: form.endTime ? joinDateTime(form.endDate || form.date, form.endTime) : null,
        location_name: form.online ? null : form.location.trim() || null, is_online: form.online, online_url: form.online ? form.onlineUrl.trim() || null : null,
        capacity: form.capacity ? Number(form.capacity) : null,
        rsvp_enabled: form.rsvp, qa_enabled: form.qa, is_paid: form.paid, status: form.status, visibility: form.visibility,
      };
      // One ticket type here; events with several are changed on the website, so their tickets are left alone.
      if (!form.manyTickets) body.tickets = form.paid ? [{ ...(form.ticketId ? { id: form.ticketId } : {}), name: form.ticketName.trim() || 'Ticket', price: Number(form.ticketPrice) || 0 }] : [];

      const { data: res } = editing ? await admin.put(`/events/${event.id}`, body) : await admin.post('/events', body);
      if (form.cover) {
        const f = new FormData();
        await appendPhoto(f, 'cover', form.cover, 'cover.jpg');
        for (const key of ['title', 'starts_at', 'status', 'visibility']) f.append(key, body[key]);
        f.append('_method', 'PUT');
        await api.post(admin.path(`/events/${res.data.id}`), f, multipart).catch(() => Alert.alert('The event was saved', 'The cover picture could not be uploaded. Try again from the event.'));
      }
      onClose();
      onSaved?.(res.data);
    } catch (e) {
      const f = fieldErrors(e);
      setErrors(f);
      if (!Object.keys(f).length) Alert.alert('Could not save', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const cover = form.cover?.uri ?? (form.coverUrl ? mediaUrl(form.coverUrl) : null);

  return (
    <Sheet visible={visible} onClose={onClose} title={editing ? 'Edit event' : 'New event'} tall>
      <Pressable onPress={pickCover} style={styles.cover} accessibilityRole="button" accessibilityLabel="Choose a cover picture">
        {cover ? (
          <>
            <Image source={{ uri: cover }} style={StyleSheet.absoluteFill} contentFit="cover" />
            <View style={styles.change}>
              <Ionicons name="camera" size={14} color={COLORS.white} />
              <AppText style={styles.changeText}>Change picture</AppText>
            </View>
          </>
        ) : (
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

      <Row label="This is an online event" value={form.online} onChange={set('online')} />
      {form.online ? (
        <TextField label="Link to join" value={form.onlineUrl} onChangeText={set('onlineUrl')} autoCapitalize="none" keyboardType="url" placeholder="https://…" error={errors.online_url} />
      ) : (
        <TextField label="Where" value={form.location} onChangeText={set('location')} placeholder="Main sanctuary" />
      )}
      <TextField label="Seats (leave empty for unlimited)" value={form.capacity} onChangeText={(t) => set('capacity')(t.replace(/\D/g, ''))} keyboardType="number-pad" />

      <AppText style={styles.label}>Who can see it</AppText>
      <View style={styles.wrap}>
        <Chip label="Everyone" on={form.visibility === 'public'} onPress={() => set('visibility')('public')} />
        <Chip label="Members only" on={form.visibility === 'members'} onPress={() => set('visibility')('members')} />
      </View>
      <AppText style={styles.label}>Status</AppText>
      <View style={styles.wrap}>
        <Chip label="Published" on={form.status === 'published'} onPress={() => set('status')('published')} />
        <Chip label="Draft" on={form.status === 'draft'} onPress={() => set('status')('draft')} />
        {editing ? <Chip label="Cancelled" on={form.status === 'cancelled'} onPress={() => set('status')('cancelled')} /> : null}
      </View>

      <Row label="Let people reserve a seat" value={form.rsvp} onChange={set('rsvp')} />
      <Row label="Questions for the speakers" value={form.qa} onChange={set('qa')} />
      <Row label="Ticketed event" value={form.paid} onChange={set('paid')} />
      {form.paid && !form.manyTickets ? (
        <View style={{ flexDirection: 'row', gap: 10, marginTop: 6 }}>
          <View style={{ flex: 1.4 }}><TextField label="Ticket name" value={form.ticketName} onChangeText={set('ticketName')} /></View>
          <View style={{ flex: 1 }}><TextField label="Price (UGX)" value={form.ticketPrice} onChangeText={(t) => set('ticketPrice')(t.replace(/\D/g, ''))} keyboardType="number-pad" /></View>
        </View>
      ) : null}
      {form.manyTickets ? <AppText variant="caption" style={{ marginBottom: 6 }}>This event has several ticket types. Change them on the website; they stay as they are.</AppText> : null}

      <Button title={editing ? 'Save changes' : form.status === 'draft' ? 'Save draft' : 'Publish event'} onPress={save} loading={busy} disabled={!form.title.trim()} style={{ marginTop: 8 }} />
      {editing && form.status === 'cancelled' && event.status !== 'cancelled' ? <AppText variant="caption" style={{ textAlign: 'center', marginTop: 8 }}>Cancelling hides the event from sign-ups.</AppText> : null}
    </Sheet>
  );
}

function Row({ label, value, onChange }) {
  return (
    <View style={styles.row}>
      <AppText style={styles.rowTitle}>{label}</AppText>
      <Switch value={value} onValueChange={onChange} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
    </View>
  );
}

const styles = StyleSheet.create({
  cover: { height: 130, borderRadius: RADIUS.lg, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', marginBottom: 14 },
  change: { position: 'absolute', right: 10, bottom: 10, flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: 'rgba(0,0,0,0.55)', borderRadius: RADIUS.full, paddingHorizontal: 10, paddingVertical: 5 },
  changeText: { fontFamily: FONTS.semibold, fontSize: 12, color: COLORS.white },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8, marginTop: 4 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 10 },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 7 },
  rowTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
});
