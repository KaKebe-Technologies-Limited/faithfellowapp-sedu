// Platform admin → Radio broadcast: put one of the platform's songs or teachings on every radio at once (the Gospel radio, the Podcast radio and every church's
// channel), and take it off again. Phones that are listening move to it within a few seconds; "Notify phones" also sends a notification to the people who are not.
// Asks for your password. Data: GET /api/v1/platform/radio, POST and DELETE /api/v1/platform/radio/broadcast.
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import PasswordSheet from '../../components/platform/PasswordSheet';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api from '../../lib/api';
import { formatDuration } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function PlatformRadio() {
  const { data, loading, error, retry, reload } = useFetch('/platform/radio');
  const [track, setTrack] = useState(null);
  const [message, setMessage] = useState('');
  const [notify, setNotify] = useState(false);
  const [asking, setAsking] = useState(null); // 'start' | 'stop'
  const [done, setDone] = useState(null);
  const radio = data?.data;

  async function confirmed({ password }) {
    const { data: result } =
      asking === 'start'
        ? await api.post('/platform/radio/broadcast', { track, message: message.trim() || undefined, notify, password })
        : await api.delete('/platform/radio/broadcast', { data: { password } });
    setDone(result.message);
    setAsking(null);
    setTrack(null);
    setMessage('');
    setNotify(false);
    await reload();
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Radio broadcast" subtitle="On every radio at once" />
      <StateView loading={loading} error={error} onRetry={retry}>
        <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
          {done ? (
            <View style={styles.done}>
              <Ionicons name="checkmark-circle" size={20} color={COLORS.success} />
              <AppText style={styles.doneText}>{done}</AppText>
            </View>
          ) : null}

          {radio?.broadcast ? (
            <View style={styles.live}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.liveKicker}>ON EVERY RADIO NOW</AppText>
                <AppText style={styles.liveTitle} numberOfLines={2}>
                  {radio.broadcast.title}
                </AppText>
                {radio.broadcast.message ? <AppText style={styles.liveText}>{radio.broadcast.message}</AppText> : null}
              </View>
              <Pressable onPress={() => setAsking('stop')} style={styles.stop} accessibilityRole="button" accessibilityLabel="Stop the broadcast">
                <AppText style={styles.stopText}>Stop</AppText>
              </Pressable>
            </View>
          ) : null}

          <AppText style={styles.section}>Choose what to play</AppText>
          {radio?.tracks?.length ? (
            <View style={{ gap: 8 }}>
              {radio.tracks.map((t) => {
                const on = track === t.id;

                return (
                  <Pressable key={t.id} onPress={() => setTrack(on ? null : t.id)} style={[styles.pick, on && styles.pickOn]} accessibilityRole="radio" accessibilityState={{ selected: on }} accessibilityLabel={t.title}>
                    <View style={{ flex: 1 }}>
                      <AppText style={styles.pickTitle} numberOfLines={2}>
                        {t.title}
                      </AppText>
                      <AppText variant="caption" numberOfLines={1}>
                        {[t.artist, formatDuration(t.duration), t.channel].filter(Boolean).join(' · ')}
                      </AppText>
                    </View>
                    <Ionicons name={on ? 'radio-button-on' : 'radio-button-off'} size={22} color={on ? COLORS.primary : COLORS.gray300} />
                  </Pressable>
                );
              })}
            </View>
          ) : (
            <AppText variant="caption">Add songs or teaching to the Gospel radio or the Podcast radio on the website first.</AppText>
          )}

          {track ? (
            <View style={{ gap: 12, marginTop: 8 }}>
              <TextField label={`A line shown with it (optional) · ${message.length}/140`} value={message} onChangeText={setMessage} maxLength={140} placeholder="Special service from the main church" />
              <View style={styles.tell}>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.tellTitle}>Notify phones too</AppText>
                  <AppText variant="caption">Sends a notification to everyone who is not listening.</AppText>
                </View>
                <Switch value={notify} onValueChange={setNotify} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.onColor} />
              </View>
              <Button title="Play on every radio" onPress={() => setAsking('start')} />
            </View>
          ) : null}
        </ScrollView>
      </StateView>

      <PasswordSheet
        visible={!!asking}
        title={asking === 'start' ? 'Play on every radio?' : 'Stop the broadcast?'}
        message={asking === 'start' ? 'Every channel stops what it is playing and moves to this, for everyone who is listening.' : 'Every channel goes back to what it had on.'}
        confirmLabel={asking === 'start' ? 'Play now' : 'Stop'}
        onClose={() => setAsking(null)}
        onConfirm={confirmed}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  body: { padding: SPACING.md, gap: 10, paddingBottom: 48 },
  done: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: 12 },
  doneText: { flex: 1, fontFamily: FONTS.medium, fontSize: 14, color: COLORS.black },
  live: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.primary, borderRadius: RADIUS.lg, padding: 16 },
  liveKicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.4, color: 'rgba(255,255,255,0.85)' },
  liveTitle: { fontFamily: FONTS.bold, fontSize: 17, lineHeight: 22, color: COLORS.onColor, marginTop: 2 },
  liveText: { fontFamily: FONTS.regular, fontSize: 13.5, color: 'rgba(255,255,255,0.92)', marginTop: 2 },
  stop: { backgroundColor: COLORS.onColor, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 10 },
  stopText: { fontFamily: FONTS.bold, fontSize: 14, color: '#BC2649' },
  section: { fontFamily: FONTS.display, fontSize: 18, color: COLORS.black, marginTop: 10, marginBottom: 2 },
  pick: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12, borderWidth: 1.5, borderColor: 'transparent', ...SHADOW },
  pickOn: { borderColor: COLORS.primary },
  pickTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  tell: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  tellTitle: { fontFamily: FONTS.semibold, fontSize: 14.5, color: COLORS.black },
});
