// "Forgot your password?", inside the app: enter your email, we send a 6-digit code, enter the code with a new
// password. No link to the website, and the code stops working after 15 minutes or five wrong tries.
import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import TextField from '../../components/TextField';
import KeyboardScroll from '../../components/KeyboardScroll';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import api, { errorMessage, fieldErrors } from '../../lib/api';

export default function Forgot() {
  const [step, setStep] = useState('email'); // 'email' | 'code' | 'done'
  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});
  const [wait, setWait] = useState(0); // seconds before another code can be asked for

  useEffect(() => {
    if (wait <= 0) return undefined;
    const t = setTimeout(() => setWait((w) => w - 1), 1000);
    return () => clearTimeout(t);
  }, [wait]);

  async function sendCode() {
    if (!email.trim()) {
      setErrors({ email: 'Enter your email.' });
      return;
    }
    setBusy(true);
    setErrors({});
    setError(null);
    try {
      await api.post('/auth/password/code', { email: email.trim() });
      setCode('');
      setStep('code');
      setWait(45);
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function reset() {
    setBusy(true);
    setErrors({});
    setError(null);
    try {
      await api.post('/auth/password/reset', { email: email.trim(), code: code.trim(), password, password_confirmation: confirm });
      setStep('done');
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior="padding">
      <KeyboardScroll contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <AppText variant="title" style={styles.heroTitle}>
            {step === 'done' ? 'All set' : 'Reset your password'}
          </AppText>
          <AppText style={styles.heroText}>{step === 'email' ? 'We will email you a 6-digit code.' : step === 'code' ? `If ${email.trim()} has an account, a code is on its way.` : 'Your password was changed.'}</AppText>
        </View>

        <View style={styles.card}>
          {error ? (
            <View style={styles.banner} accessibilityRole="alert">
              <AppText variant="label" style={{ color: COLORS.danger }}>
                {error}
              </AppText>
            </View>
          ) : null}

          {step === 'email' ? (
            <>
              <TextField label="Email" value={email} onChangeText={setEmail} error={errors.email} autoCapitalize="none" autoComplete="email" keyboardType="email-address" textContentType="emailAddress" placeholder="you@example.com" returnKeyType="go" onSubmitEditing={sendCode} />
              <Button title="Send code" onPress={sendCode} loading={busy} />
            </>
          ) : null}

          {step === 'code' ? (
            <>
              <View style={styles.sent}>
                <AppText variant="label" style={{ color: COLORS.primaryInk }}>
                  Check your inbox, and the spam folder too. The code works for 15 minutes.
                </AppText>
              </View>
              <TextField label="6-digit code" value={code} onChangeText={(t) => setCode(t.replace(/\D/g, '').slice(0, 6))} error={errors.code} keyboardType="number-pad" placeholder="123456" maxLength={6} textContentType="oneTimeCode" />
              <TextField label="New password" value={password} onChangeText={setPassword} error={errors.password} secureTextEntry autoComplete="new-password" placeholder="At least 8 characters" />
              <TextField label="Confirm new password" value={confirm} onChangeText={setConfirm} secureTextEntry autoComplete="new-password" placeholder="Type it again" returnKeyType="go" onSubmitEditing={reset} />
              <Button title="Change password" onPress={reset} loading={busy} />
              <AppText variant="label" style={[styles.again, wait > 0 && { color: COLORS.gray500 }]} onPress={() => (wait > 0 ? null : sendCode())} accessibilityRole="link" accessibilityState={{ disabled: wait > 0 }}>
                {wait > 0 ? `Send a new code in ${wait}s` : 'Did not get a code? Send a new one'}
              </AppText>
              <AppText variant="label" style={styles.again} onPress={() => { setStep('email'); setCode(''); setErrors({}); setError(null); }} accessibilityRole="link">
                Use a different email
              </AppText>
            </>
          ) : null}

          {step === 'done' ? <Button title="Sign in" onPress={() => router.replace({ pathname: '/(auth)/login', params: { email: email.trim(), reset: '1' } })} /> : null}

          {step !== 'done' ? (
            <AppText variant="label" style={styles.back} onPress={() => router.replace('/(auth)/login')} accessibilityRole="link">
              Back to sign in
            </AppText>
          ) : null}
        </View>
      </KeyboardScroll>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.primary },
  scroll: { flexGrow: 1 },
  hero: { paddingTop: SPACING.md, paddingBottom: SPACING.xl, paddingHorizontal: SPACING.lg },
  heroTitle: { color: COLORS.onColor },
  heroText: { color: COLORS.primaryPale, marginTop: 4 },
  card: { flexGrow: 1, backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, padding: SPACING.lg, paddingTop: SPACING.xl },
  banner: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  sent: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  again: { color: COLORS.primary, textAlign: 'center', marginTop: SPACING.md },
  back: { color: COLORS.gray700, textAlign: 'center', marginTop: SPACING.lg },
});
