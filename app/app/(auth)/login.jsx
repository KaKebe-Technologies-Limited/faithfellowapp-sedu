// Sign-in screen: email + password (POST /api/v1/auth/token) with a link to create an account.
// "Continue with Google" appears above the form only when Google sign-in is switched on
// (EXPO_PUBLIC_GOOGLE_SIGNIN=1). States: loading (button spinner), error (inline message).
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { Link, router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import GoogleButton from '../../components/GoogleButton';
import Logo from '../../components/Logo';
import TextField from '../../components/TextField';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import { API_ROOT, errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { GOOGLE_ENABLED } from '../../lib/google';

export default function Login() {
  const { signIn } = useAuth();
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
            Welcome back
          </AppText>

          {GOOGLE_ENABLED ? (
            <View style={styles.google}>
              <GoogleButton />
              <View style={styles.divider}>
                <View style={styles.line} />
                <AppText variant="caption">or use your email</AppText>
                <View style={styles.line} />
              </View>
            </View>
          ) : null}

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

          <AppText
            variant="label"
            style={styles.forgot}
            onPress={() => router.push('/(auth)/forgot')}
            accessibilityRole="link"
          >
            Forgot your password?
          </AppText>

          <Button title="Sign in" onPress={submit} loading={loading} style={styles.submit} />

          <View style={styles.switch}>
            <AppText>New to FaithFellows? </AppText>
            <Link href="/(auth)/register" style={styles.link}>
              Create an account
            </Link>
          </View>
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
  cardTitle: { marginBottom: SPACING.md },
  google: { marginBottom: SPACING.xs },
  divider: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, marginVertical: SPACING.md },
  line: { flex: 1, height: 1, backgroundColor: COLORS.gray100 },
  banner: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  forgot: { color: COLORS.primary, alignSelf: 'flex-end', marginTop: -SPACING.xs, marginBottom: SPACING.xs },
  submit: { marginTop: SPACING.sm },
  switch: { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: SPACING.lg },
  link: { color: COLORS.primary, fontWeight: '600' },
  dev: { textAlign: 'center', color: COLORS.primaryPale, padding: SPACING.md, backgroundColor: COLORS.white, marginTop: -1 },
});
