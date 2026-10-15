// Delete your account from inside the app (DELETE /api/v1/me). Google Play asks for this. The member types their email address to
// confirm (Google sign-in accounts have no password to ask for). Personal data is removed and gifts stay without a name; the public page
// faithfellows.com/delete-account says the same.
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Linking, Platform, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import AppText from '../components/AppText';
import Button from '../components/Button';
import KeyboardScroll from '../components/KeyboardScroll';
import ScreenHeader from '../components/ScreenHeader';
import TextField from '../components/TextField';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import api, { errorMessage, fieldErrors } from '../lib/api';
import { useAuth } from '../lib/auth';

const GONE = [
  'Your name, email, phone, photo, birthday and place',
  'Your notes, highlights, follows and saved payment details',
  'Your phones, so notifications stop',
  'Gifts that repeat are cancelled',
];
const KEPT = [
  'Records of gifts and receipts, without your name (churches must keep their books)',
  'What you shared with others, shown as “Deleted user”',
];

export default function DeleteAccountScreen() {
  const { user, signOut } = useAuth();
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const matches = email.trim().length > 0 && email.trim().toLowerCase() === String(user?.email || '').toLowerCase();

  async function run() {
    setBusy(true);
    setErrors({});
    try {
      await api.delete('/me', { data: { email: email.trim() } });
      await signOut();
      router.replace('/(auth)/welcome');
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) Alert.alert('Could not delete the account', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function confirm() {
    if (Platform.OS === 'web') {
      run();
      return;
    }
    Alert.alert('Delete your account?', 'This cannot be undone.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Delete my account', style: 'destructive', onPress: run },
    ]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Delete my account" />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
        <KeyboardScroll contentContainerStyle={{ padding: SPACING.md }} keyboardShouldPersistTaps="handled">
          <View style={styles.warn}>
            <Ionicons name="warning" size={22} color={COLORS.danger} />
            <AppText style={styles.warnText}>This permanently deletes your FaithFellows account. It cannot be undone.</AppText>
          </View>

          <AppText style={styles.heading}>What is removed</AppText>
          {GONE.map((t) => (
            <Row key={t} icon="close-circle" color={COLORS.danger} text={t} />
          ))}

          <AppText style={styles.heading}>What stays, without your name</AppText>
          {KEPT.map((t) => (
            <Row key={t} icon="checkmark-circle" color={COLORS.gray700} text={t} />
          ))}

          <AppText style={styles.heading}>Type your email to confirm</AppText>
          <TextField
            value={email}
            onChangeText={setEmail}
            error={errors.email}
            placeholder={user?.email || 'you@example.com'}
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="email"
          />
          <Button title="Delete my account" onPress={confirm} loading={busy} disabled={!matches} style={styles.danger} />

          <Pressable onPress={() => Linking.openURL('https://faithfellows.com/delete-account')} accessibilityRole="link" style={{ marginTop: SPACING.md, alignSelf: 'center' }}>
            <AppText variant="caption" style={{ color: COLORS.primary, fontFamily: FONTS.medium }}>
              More about what happens to your data
            </AppText>
          </Pressable>
        </KeyboardScroll>
      </KeyboardAvoidingView>
    </View>
  );
}

function Row({ icon, color, text }) {
  return (
    <View style={styles.row}>
      <Ionicons name={icon} size={18} color={color} style={{ marginTop: 1 }} />
      <AppText style={{ flex: 1 }}>{text}</AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  warn: { flexDirection: 'row', gap: 10, alignItems: 'center', backgroundColor: COLORS.dangerMist, borderRadius: RADIUS.lg, padding: SPACING.md },
  warnText: { flex: 1, color: COLORS.danger, fontFamily: FONTS.semibold },
  heading: { fontFamily: FONTS.bold, color: COLORS.black, marginTop: SPACING.lg, marginBottom: 8, fontSize: 16 },
  row: { flexDirection: 'row', gap: 10, alignItems: 'flex-start', marginBottom: 8 },
  danger: { backgroundColor: COLORS.danger, marginTop: 4 },
});
