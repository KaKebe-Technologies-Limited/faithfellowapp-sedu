// A friendly card on Home for a member whose phone cannot get notifications yet (never asked, or switched off): the verse of the day, messages and
// church news only pop up on phones that are set up. "Turn on" asks for permission; if the phone has it blocked, it opens the phone's own settings.
// "Not now" hides the card for three days. Nothing shows in the web preview, in Expo Go on Android, or once notifications work.
import { useCallback, useEffect, useState } from 'react';
import { Alert, AppState, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { enablePush, openSystemSettings, pushPermission, pushSupported } from '../../lib/push';
import { getStored, setStored } from '../../lib/storage';
import AppText from '../AppText';

const HIDDEN_KEY = 'push_prompt_hidden_until';
const HIDE_DAYS = 3;

export default function NotificationsPrompt() {
  const [state, setState] = useState(null); // null (hidden) | 'undetermined' | 'denied'
  const [busy, setBusy] = useState(false);

  const check = useCallback(async () => {
    if (!pushSupported) return;
    const hiddenUntil = await getStored(HIDDEN_KEY, 0);
    if (hiddenUntil > Date.now()) return setState(null);
    const permission = await pushPermission();
    if (permission === 'granted') {
      enablePush(); // allowed but this phone may not be registered yet (or its token changed): do it quietly
      return setState(null);
    }
    setState(permission === 'undetermined' || permission === 'denied' ? permission : null);
  }, []);

  useEffect(() => {
    check();
    // Back from the phone's settings: look again.
    const sub = AppState.addEventListener('change', (s) => s === 'active' && check());
    return () => sub.remove();
  }, [check]);

  if (!state) return null;

  async function turnOn() {
    if (state === 'denied') {
      Alert.alert('Notifications are blocked', 'Allow notifications for FaithFellows in your phone settings, then come back.', [{ text: 'Not now', style: 'cancel' }, { text: 'Open settings', onPress: openSystemSettings }]);
      return;
    }
    setBusy(true);
    const result = await enablePush({ ask: true });
    setBusy(false);
    if (result.status === 'on') setState(null);
    else if (result.status === 'denied') setState('denied');
  }

  async function later() {
    await setStored(HIDDEN_KEY, Date.now() + HIDE_DAYS * 24 * 60 * 60 * 1000);
    setState(null);
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.card}>
        <View style={styles.icon}>
          <Ionicons name="notifications" size={22} color={COLORS.white} />
        </View>
        <View style={{ flex: 1 }}>
          <AppText style={styles.title}>Don't miss a thing</AppText>
          <AppText style={styles.text}>Get the verse of the day, messages and news from your churches as they happen.</AppText>
          <View style={styles.row}>
            <Pressable onPress={turnOn} disabled={busy} accessibilityRole="button" style={[styles.button, busy && { opacity: 0.6 }]}>
              <AppText style={styles.buttonText}>{state === 'denied' ? 'Open settings' : 'Turn on notifications'}</AppText>
            </Pressable>
            <Pressable onPress={later} accessibilityRole="button" hitSlop={8}>
              <AppText style={styles.later}>Not now</AppText>
            </Pressable>
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg },
  card: { flexDirection: 'row', gap: SPACING.md, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, ...SHADOW },
  icon: { width: 44, height: 44, borderRadius: 22, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  title: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.gray900 },
  text: { fontFamily: FONTS.regular, fontSize: 13, lineHeight: 19, color: COLORS.gray700, marginTop: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md, marginTop: SPACING.sm },
  button: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 9 },
  buttonText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.white },
  later: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.gray700 },
});
