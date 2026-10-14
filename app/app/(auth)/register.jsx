// Create-account screen: name, email, optional phone, password and agreement to the Terms →
// POST /api/v1/auth/register → signed in and taken to the feed. Server validation errors show under
// each field. States: loading (button spinner), error (inline message), empty (n/a for a form).
import { useState } from 'react';
import { KeyboardAvoidingView, StyleSheet, Switch, View } from 'react-native';
import { Link, router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import GoogleButton from '../../components/GoogleButton';
import TextField from '../../components/TextField';
import KeyboardScroll from '../../components/KeyboardScroll';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import { errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { GOOGLE_ENABLED } from '../../lib/google';

export default function Register() {
  const { signUp } = useAuth();
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
  const [agreed, setAgreed] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});
  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  function validate() {
    const found = {};
    if (!form.name.trim()) found.name = 'Enter your full name.';
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) found.email = 'Enter a valid email address.';
    if (form.password.length < 8) found.password = 'Use at least 8 characters.';
    if (form.password !== form.password_confirmation) found.password_confirmation = 'The passwords do not match.';
    if (!agreed) found.terms = 'Please agree to the Terms of Service and Privacy Policy.';
    setErrors(found);
    return Object.keys(found).length === 0;
  }

  async function submit() {
    if (!validate()) return;
    setLoading(true);
    setError(null);
    try {
      await signUp(form);
      router.replace('/(tabs)');
    } catch (e) {
      const fields = fieldErrors(e);
      // The server reports password confirmation mismatches on "password".
      setErrors(fields);
      if (!Object.keys(fields).length) setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior="padding">
      <KeyboardScroll contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <AppText variant="title" style={{ color: COLORS.white }}>
            Create your account
          </AppText>
          <AppText style={{ color: COLORS.primaryPale, marginTop: 4 }}>Follow your church and grow together.</AppText>
        </View>

        <View style={styles.card}>
          {GOOGLE_ENABLED ? (
            <View style={{ marginBottom: SPACING.md }}>
              <GoogleButton title="Sign up with Google" />
              <AppText variant="caption" style={styles.or}>
                or create an account with your email
              </AppText>
            </View>
          ) : null}

          {error ? (
            <View style={styles.banner} accessibilityRole="alert">
              <AppText variant="label" style={{ color: COLORS.danger }}>
                {error}
              </AppText>
            </View>
          ) : null}

          <TextField label="Full name" value={form.name} onChangeText={set('name')} error={errors.name} autoComplete="name" textContentType="name" placeholder="Grace Akello" returnKeyType="next" />
          <TextField
            label="Email"
            value={form.email}
            onChangeText={set('email')}
            error={errors.email}
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            textContentType="emailAddress"
            placeholder="you@example.com"
            returnKeyType="next"
          />
          <TextField label="Phone (optional)" value={form.phone} onChangeText={set('phone')} error={errors.phone} keyboardType="phone-pad" autoComplete="tel" placeholder="0772 123 456" />
          <TextField label="Password" value={form.password} onChangeText={set('password')} error={errors.password} secureTextEntry textContentType="newPassword" placeholder="At least 8 characters" />
          <TextField
            label="Confirm password"
            value={form.password_confirmation}
            onChangeText={set('password_confirmation')}
            error={errors.password_confirmation}
            secureTextEntry
            textContentType="newPassword"
            placeholder="Repeat your password"
          />

          <View style={styles.terms}>
            <Switch
              value={agreed}
              onValueChange={setAgreed}
              trackColor={{ true: COLORS.primary, false: COLORS.gray300 }}
              thumbColor={COLORS.white}
              accessibilityLabel="I agree to the Terms of Service and Privacy Policy"
            />
            <AppText variant="caption" style={styles.termsText}>
              I agree to the{' '}
              <AppText variant="caption" style={styles.link} onPress={() => router.push('/legal/terms')}>
                Terms of Service
              </AppText>{' '}
              and{' '}
              <AppText variant="caption" style={styles.link} onPress={() => router.push('/legal/privacy')}>
                Privacy Policy
              </AppText>
              .
            </AppText>
          </View>
          {errors.terms ? (
            <AppText variant="caption" style={styles.fieldError}>
              {errors.terms}
            </AppText>
          ) : null}

          <Button title="Create account" onPress={submit} loading={loading} style={styles.submit} />

          <View style={styles.switch}>
            <AppText>Already have an account? </AppText>
            <Link href="/(auth)/login" style={styles.link}>
              Sign in
            </Link>
          </View>
        </View>
      </KeyboardScroll>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.primary },
  scroll: { flexGrow: 1 },
  hero: { paddingBottom: SPACING.lg, paddingHorizontal: SPACING.lg },
  card: { flexGrow: 1, backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, padding: SPACING.lg, paddingTop: SPACING.xl },
  or: { textAlign: 'center', marginTop: SPACING.md },
  banner: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  terms: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md, marginTop: SPACING.xs },
  termsText: { flex: 1, lineHeight: 18 },
  fieldError: { color: COLORS.danger, marginTop: 4 },
  submit: { marginTop: SPACING.md },
  switch: { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: SPACING.lg },
  link: { color: COLORS.primary, fontWeight: '600' },
});
