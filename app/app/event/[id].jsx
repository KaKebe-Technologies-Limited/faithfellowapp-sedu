// Event page, all inside the app: cover, when and where, who is speaking, the agenda, questions for the
// speakers, and the one action that matters — RSVP (free), choose a ticket (paid) or pay for a held seat.
// Once you are going, the sticky bar turns into "View ticket" and shows the QR code the door scans.
import { useState } from 'react';
import { Alert, Linking, Modal, Platform, Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import QRCode from 'react-native-qrcode-svg';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import CoverHero from '../../components/CoverHero';
import InfoRow from '../../components/InfoRow';
import PayForm from '../../components/PayForm';
import Pill from '../../components/Pill';
import { HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import StickyBar from '../../components/StickyBar';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { formatMoney } from '../../lib/format';
import { openPayment } from '../../lib/payments';
import { useFetch } from '../../lib/useFetch';

const longDay = (iso) => new Date(iso).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const clock = (iso) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

function Stepper({ value, min = 1, max = 10, onChange }) {
  return (
    <View style={styles.stepper}>
      <Pressable onPress={() => onChange(Math.max(min, value - 1))} hitSlop={8} accessibilityLabel="Fewer" style={styles.stepBtn}>
        <Ionicons name="remove" size={18} color={COLORS.primary} />
      </Pressable>
      <AppText style={styles.stepValue}>{value}</AppText>
      <Pressable onPress={() => onChange(Math.min(max, value + 1))} hitSlop={8} accessibilityLabel="More" style={styles.stepBtn}>
        <Ionicons name="add" size={18} color={COLORS.primary} />
      </Pressable>
    </View>
  );
}

/** The ticket: a QR code the door scanner reads, the 10-letter code as a fallback, and the guest count. */
function TicketModal({ event, rsvp, onClose, onCancel }) {
  const insets = useSafeAreaInsets();
  return (
    <Modal visible animationType="slide" onRequestClose={onClose} transparent>
      <View style={styles.backdrop}>
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 20 }]}>
          <View style={styles.grab} />
          <AppText style={styles.sheetTitle}>{event.title}</AppText>
          <AppText variant="caption" style={{ textAlign: 'center' }}>
            {longDay(event.starts_at)} · {clock(event.starts_at)}
          </AppText>
          <View style={styles.qrBox}>
            <QRCode value={rsvp.qr || rsvp.code || 'ticket'} size={200} color="#1A1A1A" backgroundColor="#FFFFFF" />
          </View>
          <AppText style={styles.code}>{rsvp.code}</AppText>
          <AppText variant="caption" style={{ textAlign: 'center' }}>
            {rsvp.quantity} {rsvp.quantity === 1 ? 'guest' : 'guests'}
            {rsvp.ticket?.name ? ` · ${rsvp.ticket.name}` : ''}
            {rsvp.checked_in ? ' · Checked in ✓' : ''}
          </AppText>
          <View style={{ gap: 10, marginTop: 18, alignSelf: 'stretch' }}>
            <Button title="Done" onPress={onClose} />
            {!rsvp.checked_in ? <Button title="Cancel my reservation" variant="outline" onPress={onCancel} /> : null}
          </View>
        </View>
      </View>
    </Modal>
  );
}

export default function EventScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry, reload } = useFetch(`/events/${id}`);
  const event = data?.data;
  const rsvp = event?.my_rsvp;

  const [quantity, setQuantity] = useState(1);
  const [ticketId, setTicketId] = useState(null);
  const [busy, setBusy] = useState(false);
  const [showTicket, setShowTicket] = useState(false);
  const [showPay, setShowPay] = useState(false);
  const [question, setQuestion] = useState('');
  const [asking, setAsking] = useState(false);
  const [payError, setPayError] = useState(null);

  const tickets = event?.tickets ?? [];
  const chosen = tickets.find((t) => t.id === (ticketId ?? tickets.find((x) => x.on_sale)?.id));
  const seatsLeft = event?.seats_left;
  const maxQty = Math.max(1, Math.min(10, seatsLeft ?? 10, chosen?.remaining ?? 10));
  const total = event?.is_paid ? (chosen?.price ?? 0) * quantity : 0;

  const payOptions = useFetch(`/churches/${event?.church?.slug}/giving`, { enabled: !!event?.church?.slug && (rsvp?.status === 'pending_payment' || !!showPay) });

  async function reserve(status = 'going') {
    setBusy(true);
    try {
      const { data: res } = await api.post(`/events/${id}/rsvp`, {
        quantity,
        status,
        ...(event.is_paid ? { event_ticket_id: chosen?.id } : {}),
      });
      await reload();
      if (res.next?.action === 'pay') setShowPay(true);
      else if (status === 'going') setShowTicket(true);
    } catch (e) {
      Alert.alert('Could not reserve', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function cancel() {
    const run = async () => {
      try {
        await api.delete(`/events/${id}/rsvp`);
        setShowTicket(false);
        setShowPay(false);
        await reload();
      } catch (e) {
        Alert.alert('Could not cancel', errorMessage(e));
      }
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Cancel reservation?', 'Your seat will be given to someone else.', [
      { text: 'Keep it', style: 'cancel' },
      { text: 'Cancel reservation', style: 'destructive', onPress: run },
    ]);
  }

  async function pay({ method, phone }) {
    setBusy(true);
    setPayError(null);
    try {
      const { data: res } = await api.post(`/rsvps/${rsvp.id}/pay`, { method, phone });
      setShowPay(false);
      openPayment(res);
    } catch (e) {
      setPayError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function ask() {
    if (!question.trim()) return;
    setAsking(true);
    try {
      await api.post(`/events/${id}/questions`, { body: question.trim() });
      setQuestion('');
      await reload();
    } catch (e) {
      Alert.alert('Could not send', errorMessage(e));
    } finally {
      setAsking(false);
    }
  }

  const mapUrl = event?.location?.lat ? `https://www.google.com/maps/search/?api=1&query=${event.location.lat},${event.location.lng}` : event?.location?.address ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(event.location.address)}` : null;

  // Sticky bar: what to show depends on whether you already have a seat.
  let bar = null;
  if (event && !event.is_past) {
    if (rsvp?.status === 'going') {
      bar = <StickyBar title="You're going 🎉" subtitle={`${rsvp.quantity} ${rsvp.quantity === 1 ? 'guest' : 'guests'}`} action="View ticket" onPress={() => setShowTicket(true)} />;
    } else if (rsvp?.status === 'pending_payment') {
      bar = <StickyBar title={formatMoney(rsvp.amount)} subtitle="Seat held — pay to confirm" action="Pay now" onPress={() => setShowPay(true)} />;
    } else if (event.rsvp_enabled) {
      const soldOut = seatsLeft === 0;
      bar = event.is_paid ? (
        <StickyBar title={formatMoney(total)} subtitle={chosen ? `${quantity} × ${chosen.name}` : 'Choose a ticket'} action={soldOut ? 'Sold out' : 'Get tickets'} disabled={soldOut || !chosen} loading={busy} onPress={() => reserve('going')} />
      ) : (
        <StickyBar title="Free" subtitle={seatsLeft != null ? `${seatsLeft} seats left` : 'All are welcome'} action={soldOut ? 'Full' : "I'm going"} disabled={soldOut} loading={busy} onPress={() => reserve('going')}>
          {!soldOut ? <Stepper value={quantity} max={maxQty} onChange={setQuantity} /> : null}
        </StickyBar>
      );
    }
  }

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={retry}>
        {event ? (
          <>
            <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false}>
              <CoverHero uri={event.cover_url} height={300} right={<HeaderAction onDark icon="share-social-outline" label="Share" onPress={() => Share.share({ message: `${event.title} — ${longDay(event.starts_at)}, ${clock(event.starts_at)}${event.location?.name ? ` at ${event.location.name}` : ''}` }).catch(() => {})} />}>
                <View style={{ flexDirection: 'row', gap: 6 }}>
                  <Pill label={event.is_online ? 'Online event' : 'Event'} icon={event.is_online ? 'videocam-outline' : 'calendar-outline'} tone="dark" />
                  {event.is_past ? <Pill label="Ended" tone="dark" /> : null}
                </View>
                <AppText style={styles.heroTitle}>{event.title}</AppText>
                <Pressable onPress={() => event.church && router.push({ pathname: '/church/[slug]', params: { slug: event.church.slug } })} style={styles.host}>
                  <ChurchAvatar church={event.church} size={26} />
                  <AppText style={styles.hostName}>{event.church?.name}</AppText>
                </Pressable>
              </CoverHero>

              <View style={styles.body}>
                <InfoRow icon="calendar-outline" title={longDay(event.starts_at)} subtitle={event.ends_at ? `${clock(event.starts_at)} – ${clock(event.ends_at)}` : clock(event.starts_at)} />
                {event.is_online ? (
                  <InfoRow icon="videocam-outline" title="Online" subtitle={event.online_url ? 'Tap to join' : 'Link shared with those going'} onPress={event.online_url && rsvp?.status === 'going' ? () => Linking.openURL(event.online_url) : undefined} />
                ) : event.location?.name || event.location?.address ? (
                  <InfoRow icon="location-outline" title={event.location.name || event.location.address} subtitle={event.location.name ? event.location.address : undefined} onPress={mapUrl ? () => Linking.openURL(mapUrl) : undefined} />
                ) : null}
                <InfoRow icon="ticket-outline" title={event.is_paid ? `From ${formatMoney(Math.min(...tickets.map((t) => t.price)))}` : 'Free entry'} subtitle={event.going_count ? `${event.going_count} going${seatsLeft != null ? ` · ${seatsLeft} seats left` : ''}` : seatsLeft != null ? `${seatsLeft} seats left` : undefined} />

                {event.description ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>About this event</AppText>
                    <AppText style={styles.text}>{event.description}</AppText>
                  </View>
                ) : null}

                {event.speakers?.length ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>Speakers</AppText>
                    <View style={{ gap: 10 }}>
                      {event.speakers.map((s, i) => (
                        <View key={i} style={styles.speaker}>
                          <View style={styles.speakerDot}>
                            <AppText style={styles.speakerInitial}>{String(s.name || '?').charAt(0)}</AppText>
                          </View>
                          <View style={{ flex: 1 }}>
                            <AppText style={styles.speakerName}>{s.name}</AppText>
                            {s.role ? <AppText variant="caption" style={{ color: COLORS.primary, fontFamily: FONTS.medium }}>{s.role}</AppText> : null}
                            {s.bio ? <AppText variant="caption" numberOfLines={3}>{s.bio}</AppText> : null}
                          </View>
                        </View>
                      ))}
                    </View>
                  </View>
                ) : null}

                {event.agenda?.length ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>Agenda</AppText>
                    {event.agenda.map((a, i) => (
                      <View key={i} style={styles.agenda}>
                        <AppText style={styles.agendaTime}>{a.time}</AppText>
                        <AppText style={styles.agendaTitle}>{a.title}{a.speaker ? <AppText style={{ fontFamily: FONTS.regular, color: COLORS.gray700 }}>{`  —  ${a.speaker}`}</AppText> : null}</AppText>
                      </View>
                    ))}
                  </View>
                ) : null}

                {event.is_paid && !event.is_past && !rsvp ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>Choose your ticket</AppText>
                    <View style={{ gap: 10 }}>
                      {tickets.map((t) => {
                        const active = t.id === chosen?.id;
                        return (
                          <Pressable key={t.id} disabled={!t.on_sale} onPress={() => setTicketId(t.id)} style={[styles.ticket, active && styles.ticketOn, !t.on_sale && { opacity: 0.5 }]}>
                            <View style={{ flex: 1 }}>
                              <AppText style={styles.ticketName}>{t.name}</AppText>
                              <AppText variant="caption">{t.on_sale ? (t.remaining != null ? `${t.remaining} left` : 'Available') : 'Not on sale'}</AppText>
                            </View>
                            <AppText style={styles.ticketPrice}>{formatMoney(t.price, t.currency)}</AppText>
                          </Pressable>
                        );
                      })}
                    </View>
                    <View style={styles.qtyRow}>
                      <AppText style={styles.text}>Number of tickets</AppText>
                      <Stepper value={quantity} max={maxQty} onChange={setQuantity} />
                    </View>
                  </View>
                ) : null}

                {event.qa_enabled ? (
                  <View style={styles.section}>
                    <AppText style={styles.h2}>Ask the speakers</AppText>
                    {!event.is_past ? (
                      <View style={styles.askRow}>
                        <View style={{ flex: 1 }}>
                          <TextField placeholder="Type your question…" value={question} onChangeText={setQuestion} maxLength={500} style={{ marginBottom: 0 }} />
                        </View>
                        <Button title="Send" small loading={asking} onPress={ask} disabled={!question.trim()} />
                      </View>
                    ) : null}
                    {(event.questions ?? []).map((q) => (
                      <View key={q.id} style={styles.question}>
                        <AppText style={styles.text}>{q.body}</AppText>
                        <AppText variant="caption">{q.mine ? 'You' : q.author || 'Someone'}{q.is_answered ? ' · Answered ✓' : ''}</AppText>
                      </View>
                    ))}
                  </View>
                ) : null}

                {rsvp?.status === 'going' && !event.is_past ? (
                  <Pressable onPress={cancel} style={{ alignSelf: 'center', marginTop: 20 }} accessibilityRole="button">
                    <AppText style={{ color: COLORS.gray700, textDecorationLine: 'underline' }}>Can't make it? Cancel my reservation</AppText>
                  </Pressable>
                ) : null}
              </View>
            </ScrollView>
            {bar}
          </>
        ) : null}
      </StateView>

      {showTicket && rsvp?.status === 'going' ? <TicketModal event={event} rsvp={rsvp} onClose={() => setShowTicket(false)} onCancel={cancel} /> : null}

      <Modal visible={showPay} animationType="slide" transparent onRequestClose={() => setShowPay(false)}>
        <View style={styles.backdrop}>
          <ScrollView style={styles.paySheet} contentContainerStyle={{ padding: SPACING.lg, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
            <View style={styles.grab} />
            <AppText style={styles.sheetTitle}>Pay for your ticket</AppText>
            <AppText style={[styles.text, { textAlign: 'center', marginBottom: 16 }]}>{formatMoney(rsvp?.amount)} · your seat is held until you pay.</AppText>
            {payOptions.loading ? <StateView loading /> : <PayForm methods={payOptions.data?.data?.methods ?? []} savedPhone={payOptions.data?.data?.saved_phone} submitLabel={`Pay ${formatMoney(rsvp?.amount)}`} loading={busy} error={payError} onSubmit={pay} />}
            <Button title="Not now" variant="outline" onPress={() => setShowPay(false)} style={{ marginTop: 10 }} />
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  heroTitle: { fontFamily: FONTS.bold, fontSize: 27, lineHeight: 33, color: COLORS.white, marginTop: 4 },
  host: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  hostName: { fontFamily: FONTS.semibold, fontSize: 14, color: 'rgba(255,255,255,0.92)' },
  body: { padding: SPACING.md, paddingTop: SPACING.sm },
  section: { marginTop: SPACING.lg },
  h2: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black, marginBottom: 10 },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 23, color: COLORS.gray900 },
  speaker: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, ...SHADOW },
  speakerDot: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.primaryLight, alignItems: 'center', justifyContent: 'center' },
  speakerInitial: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.white },
  speakerName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  agenda: { flexDirection: 'row', gap: 14, paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: COLORS.gray100 },
  agendaTime: { width: 62, fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  agendaTitle: { flex: 1, fontFamily: FONTS.medium, fontSize: 15, color: COLORS.black },
  ticket: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: RADIUS.lg, backgroundColor: COLORS.white, borderWidth: 1.5, borderColor: COLORS.gray100 },
  ticketOn: { borderColor: COLORS.primary, backgroundColor: '#FFF7FB' },
  ticketName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  ticketPrice: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.primary },
  qtyRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 14 },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  stepBtn: { width: 34, height: 34, borderRadius: 17, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  stepValue: { fontFamily: FONTS.bold, fontSize: 16, minWidth: 18, textAlign: 'center', color: COLORS.black },
  askRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 },
  question: { backgroundColor: COLORS.white, borderRadius: RADIUS.md, padding: 12, marginBottom: 8, gap: 4 },
  backdrop: { flex: 1, backgroundColor: 'rgba(20,6,12,0.55)', justifyContent: 'flex-end' },
  sheet: { backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg, alignItems: 'center' },
  paySheet: { backgroundColor: COLORS.canvas, borderTopLeftRadius: 28, borderTopRightRadius: 28, maxHeight: '88%' },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 14 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 26, color: COLORS.black, textAlign: 'center', marginBottom: 4 },
  qrBox: { padding: 16, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, marginVertical: 18, borderWidth: 1, borderColor: COLORS.gray100 },
  code: { fontFamily: FONTS.bold, fontSize: 22, letterSpacing: 4, color: COLORS.black, marginBottom: 4 },
});
