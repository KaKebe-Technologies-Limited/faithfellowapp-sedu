// Change your password from inside the app (PUT /api/v1/me/password). Your other phones are signed out.
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import Button from '../components/Button';
import ScreenHeader from '../components/ScreenHeader';
import TextField from '../components/TextField';
import KeyboardScroll from '../components/KeyboardScroll';
import { COLORS, SPACING } from '../constants/theme';
import api, { errorMessage, fieldErrors } from '../lib/api';

export default function PasswordScreen() {
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});
  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      await api.put('/me/password', form);
      if (Platform.OS === 'web') router.back();
      else Alert.alert('Password changed', 'Your other devices will ask you to sign in again.', [{ text: 'OK', onPress: () => router.back() }]);
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) Alert.alert('Could not change password', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Change password" />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
        <KeyboardScroll contentContainerStyle={{ padding: SPACING.md }} keyboardShouldPersistTaps="handled">
          <TextField label="Current password" value={form.current_password} onChangeText={set('current_password')} error={errors.current_password} secureTextEntry autoComplete="current-password" />
          <TextField label="New password" value={form.password} onChangeText={set('password')} error={errors.password} secureTextEntry autoComplete="new-password" placeholder="At least 8 characters" />
          <TextField label="Confirm new password" value={form.password_confirmation} onChangeText={set('password_confirmation')} secureTextEntry autoComplete="new-password" />
          <Button title="Change password" onPress={save} loading={busy} />
        </KeyboardScroll>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
});
