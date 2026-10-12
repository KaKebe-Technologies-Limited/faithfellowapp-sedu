// Sign-in / sign-up screen. "Continue with Google" does both: it signs returning members in and
// creates the account for new ones. Email + password (POST /api/v1/auth/token) stays available,
// tucked away, for accounts that already have a password. States: loading (spinners), error (inline).
import { useState } from 'react';
import { KeyboardAvoidingView, Linking, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import GoogleButton from '../../components/GoogleButton';
import Logo from '../../components/Logo';
import TextField from '../../components/TextField';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import { API_ROOT, SITE_URL, errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export default function Login() {
  const { signIn } = useAuth();
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});

  async function submit() {
    if (!email.trim() || !password) {
      setErrors({ email: email.trim() ? undefined : 'Enter your email.', password: password ? undefined : 'Enter your password.' });
      return;
    }
    setLoading(true);
    setError(null);
    setErrors({});
    try {
      await signIn(email, password);
      router.replace('/(tabs)');
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <Logo size={84} />
          <AppText variant="title" style={styles.heroTitle}>
            FaithFellows
          </AppText>
          <AppText style={styles.heroText}>Empowering your ministry in the digital age</AppText>
        </View>

        <View style={styles.card}>
          <AppText variant="heading" style={styles.cardTitle}>
            Welcome
          </AppText>
          <AppText style={styles.lead}>Sign in or create your account in one tap.</AppText>

          <GoogleButton />

          <AppText variant="caption" style={styles.terms}>
            By continuing you agree to our{' '}
            <AppText variant="caption" style={styles.link} onPress={() => Linking.openURL(`${SITE_URL}/terms`)}>
              Terms of Service
            </AppText>{' '}
            and{' '}
            <AppText variant="caption" style={styles.link} onPress={() => Linking.openURL(`${SITE_URL}/privacy`)}>
              Privacy Policy
            </AppText>
            .
          </AppText>

          {showPassword ? (
            <View style={styles.passwordBlock}>
              <View style={styles.divider}>
                <View style={styles.line} />
                <AppText variant="caption">or use your password</AppText>
                <View style={styles.line} />
              </View>

              {error ? (
                <View style={styles.banner} accessibilityRole="alert">
                  <AppText variant="label" style={{ color: COLORS.danger }}>
                    {error}
                  </AppText>
                </View>
              ) : null}

              <TextField
                label="Email"
                value={email}
                onChangeText={setEmail}
                error={errors.email}
                autoCapitalize="none"
                autoComplete="email"
                keyboardType="email-address"
                textContentType="emailAddress"
                placeholder="you@example.com"
                returnKeyType="next"
              />
              <TextField
                label="Password"
                value={password}
                onChangeText={setPassword}
                error={errors.password}
                secureTextEntry
                autoComplete="password"
                textContentType="password"
                placeholder="Your password"
                returnKeyType="go"
                onSubmitEditing={submit}
              />
              <Button title="Sign in" variant="outline" onPress={submit} loading={loading} />
            </View>
          ) : (
            <Pressable onPress={() => setShowPassword(true)} accessibilityRole="button" style={styles.switch}>
              <AppText variant="label" style={styles.link}>
                Use email and password instead
              </AppText>
            </Pressable>
          )}
        </View>

        {__DEV__ ? (
          <AppText variant="caption" style={styles.dev}>
            Server: {API_ROOT || 'not set'}
          </AppText>
        ) : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.primary },
  scroll: { flexGrow: 1 },
  hero: { alignItems: 'center', paddingTop: SPACING.md, paddingBottom: SPACING.xl, paddingHorizontal: SPACING.lg },
  heroTitle: { color: COLORS.white, marginTop: SPACING.md },
  heroText: { color: COLORS.primaryPale, textAlign: 'center', marginTop: 4 },
  card: { flexGrow: 1, backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, padding: SPACING.lg, paddingTop: SPACING.xl },
  cardTitle: { marginBottom: 2 },
  lead: { color: COLORS.gray700, marginBottom: SPACING.lg },
  terms: { textAlign: 'center', marginTop: SPACING.md, lineHeight: 18 },
  link: { color: COLORS.primary },
  switch: { alignItems: 'center', marginTop: SPACING.xl, paddingVertical: SPACING.sm },
  passwordBlock: { marginTop: SPACING.lg },
  divider: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, marginBottom: SPACING.md },
  line: { flex: 1, height: 1, backgroundColor: COLORS.gray100 },
  banner: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  dev: { textAlign: 'center', color: COLORS.primaryPale, padding: SPACING.md, backgroundColor: COLORS.white, marginTop: -1 },
});
