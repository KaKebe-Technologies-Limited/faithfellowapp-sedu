// Notification settings: whether this phone gets push notifications (and a test to prove it works), which kinds of
// news to receive by push or email, quiet hours, a daily summary and muting a whole church.
// Data: GET/PUT /api/v1/notification-settings, POST/DELETE /api/v1/devices, POST /api/v1/devices/test.
import { useCallback, useEffect, useState } from 'react';
import { Alert, Platform, ScrollView, StyleSheet, Switch, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, fieldErrors } from '../../lib/api';
import { disablePush, enablePush, isRegistered, openSystemSettings, pushPermission, pushSupported } from '../../lib/push';
import { useFetch } from '../../lib/useFetch';

const TRACK = { true: COLORS.primaryLight, false: COLORS.gray300 };

function Toggle({ value, onValueChange, label }) {
  return <Switch value={value} onValueChange={onValueChange} trackColor={TRACK} thumbColor={COLORS.white} accessibilityLabel={label} />;
}

export default function NotificationSettings() {
  const { data, loading, error, retry } = useFetch('/notification-settings');
  const [s, setS] = useState(null);
  const [phone, setPhone] = useState({ permission: 'checking', registered: false });
  const [busy, setBusy] = useState(null);
  const [hours, setHours] = useState({ start: '22:00', end: '06:00' });
  const [hoursError, setHoursError] = useState(null);

  useEffect(() => {
    if (data?.data) {
      setS(data.data);
      if (data.data.quiet_hours) setHours(data.data.quiet_hours);
    }
  }, [data]);

  const checkPhone = useCallback(async () => {
    setPhone({ permission: await pushPermission(), registered: await isRegistered() });
  }, []);
  useFocusEffect(
    useCallback(() => {
      checkPhone();
    }, [checkPhone]),
  );

  async function save(patch) {
    const before = s;
    setS((cur) => ({ ...cur, ...patch, categories: patch.categories ? cur.categories.map((c) => (patch.categories[c.key] ? { ...c, ...patch.categories[c.key] } : c)) : cur.categories }));
    try {
      const { data: res } = await api.put('/notification-settings', patch);
      setS(res.data);
      setHoursError(null);
      return true;
    } catch (e) {
      setS(before);
      const f = fieldErrors(e);
      if (patch.quiet_hours) setHoursError(f['quiet_hours.start'] || f['quiet_hours.end'] || errorMessage(e));
      else Alert.alert('Could not save', errorMessage(e));
      return false;
    }
  }

  async function turnOn() {
    setBusy('phone');
    const result = await enablePush({ ask: true });
    setBusy(null);
    await checkPhone();
    if (result.status === 'denied') {
      Alert.alert('Notifications are blocked', 'Allow notifications for FaithFellows in your phone settings, then come back.', [{ text: 'Not now', style: 'cancel' }, { text: 'Open settings', onPress: openSystemSettings }]);
    } else if (result.status === 'unsupported') {
      Alert.alert('Not available right now', result.error ? 'This phone could not be set up for notifications right now. Please check your internet connection and try again.' : 'Push notifications work in the installed app on a real phone.');
    }
  }

  async function turnOff() {
    setBusy('phone');
    await disablePush();
    setBusy(null);
    await checkPhone();
  }

  async function test() {
    setBusy('test');
    try {
      const { data: res } = await api.post('/devices/test');
      Alert.alert(res.sent ? 'Sent' : 'Could not deliver', res.sent ? 'A test notification is on its way to your phone.' : 'The push service did not accept it. Try again in a minute.');
    } catch (e) {
      Alert.alert('Could not send', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  const on = phone.permission === 'granted' && phone.registered;
  const status = !pushSupported
    ? { title: 'Not available in this preview', text: Platform.OS === 'web' ? 'Push notifications work in the app on your phone.' : 'Push needs the installed app (a development or store build); Expo Go on Android cannot receive them.' }
    : on
      ? { title: 'On for this phone', text: 'You will get news from your churches, connection requests and messages even when the app is closed.' }
      : phone.permission === 'denied'
        ? { title: 'Blocked in your phone settings', text: 'Allow notifications for FaithFellows in your phone settings to get news when the app is closed.' }
        : { title: 'Off for this phone', text: 'Turn on to hear about live services, events, prayer and messages when the app is closed.' };

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Notification settings" subtitle="Choose what reaches you" />
      <StateView loading={loading && !s} error={!s ? error : null} onRetry={retry}>
        {s ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 56, gap: 14 }} showsVerticalScrollIndicator={false}>
            <View style={styles.card}>
              <AppText style={styles.h}>{status.title}</AppText>
              <AppText variant="caption" style={styles.text}>{status.text}</AppText>
              {pushSupported ? (
                <View style={styles.row2}>
                  {on ? (
                    <>
                      <Button title="Send me a test" small onPress={test} loading={busy === 'test'} />
                      <Button title="Turn off" small variant="outline" onPress={turnOff} loading={busy === 'phone'} />
                    </>
                  ) : phone.permission === 'denied' ? (
                    <Button title="Open phone settings" small onPress={openSystemSettings} />
                  ) : (
                    <Button title="Turn on notifications" small onPress={turnOn} loading={busy === 'phone'} />
                  )}
                </View>
              ) : null}
            </View>

            <View style={styles.card}>
              <View style={styles.headRow}>
                <AppText style={[styles.h, { flex: 1 }]}>What to send me</AppText>
                <AppText style={styles.col}>Push</AppText>
                <AppText style={styles.col}>Email</AppText>
              </View>
              {s.categories.map((c) => (
                <View key={c.key} style={styles.catRow}>
                  <AppText style={styles.catLabel} numberOfLines={2}>{c.label}</AppText>
                  <View style={styles.col}>
                    <Toggle value={c.push} label={`${c.label} by push`} onValueChange={(v) => save({ categories: { [c.key]: { push: v } } })} />
                  </View>
                  <View style={styles.col}>
                    <Toggle value={c.email} label={`${c.label} by email`} onValueChange={(v) => save({ categories: { [c.key]: { email: v } } })} />
                  </View>
                </View>
              ))}
              <AppText variant="caption">These are your choices for every church. Giving receipts and live services stay on even in the daily summary.</AppText>
            </View>

            <View style={styles.card}>
              <View style={styles.headRow}>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.h}>Quiet hours</AppText>
                  <AppText variant="caption">No buzzing at night. Urgent notices still come through.</AppText>
                </View>
                <Toggle value={!!s.quiet_hours} label="Quiet hours" onValueChange={(v) => (v ? save({ quiet_hours: hours }) : save({ quiet_hours: null }))} />
              </View>
              {s.quiet_hours ? (
                <>
                  <View style={{ flexDirection: 'row', gap: 10 }}>
                    <TextField label="From (HH:MM)" value={hours.start} onChangeText={(t) => setHours((h) => ({ ...h, start: t }))} style={{ flex: 1 }} maxLength={5} keyboardType="numbers-and-punctuation" />
                    <TextField label="Until (HH:MM)" value={hours.end} onChangeText={(t) => setHours((h) => ({ ...h, end: t }))} style={{ flex: 1 }} maxLength={5} keyboardType="numbers-and-punctuation" />
                  </View>
                  {hoursError ? <AppText style={styles.err}>{hoursError}</AppText> : null}
                  <Button title="Save quiet hours" small variant="outline" onPress={() => save({ quiet_hours: hours })} disabled={hours.start === s.quiet_hours.start && hours.end === s.quiet_hours.end} style={{ alignSelf: 'flex-start' }} />
                </>
              ) : null}
            </View>

            <View style={[styles.card, styles.headRow]}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.h}>Daily summary</AppText>
                <AppText variant="caption">Get everyday news as one summary a day instead of one by one.</AppText>
              </View>
              <Toggle value={s.digest_mode} label="Daily summary" onValueChange={(v) => save({ digest_mode: v })} />
            </View>

            {s.churches.length ? (
              <View style={styles.card}>
                <AppText style={styles.h}>Mute a church</AppText>
                {s.churches.map((c) => (
                  <View key={c.id} style={styles.catRow}>
                    <AppText style={styles.catLabel} numberOfLines={2}>{c.name}</AppText>
                    <Toggle
                      value={c.muted}
                      label={`Mute ${c.name}`}
                      onValueChange={(v) => save({ muted_churches: s.churches.filter((x) => (x.id === c.id ? v : x.muted)).map((x) => x.id) })}
                    />
                  </View>
                ))}
                <AppText variant="caption">A muted church stays in your list, but sends you nothing.</AppText>
              </View>
            ) : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 10, ...SHADOW, shadowOpacity: 0.05 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  text: { fontSize: 13.5, lineHeight: 19 },
  row2: { flexDirection: 'row', gap: 10, flexWrap: 'wrap' },
  headRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  col: { width: 58, alignItems: 'center', fontFamily: FONTS.semibold, fontSize: 12.5, color: COLORS.gray700, textAlign: 'center' },
  catRow: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 40 },
  catLabel: { flex: 1, fontFamily: FONTS.medium, fontSize: 15, color: COLORS.black },
  err: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.danger ?? '#C0392B' },
});
