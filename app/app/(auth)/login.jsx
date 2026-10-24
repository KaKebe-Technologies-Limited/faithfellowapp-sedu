// Sign in with email: the "Continue with email" step after the welcome screen (POST /api/v1/auth/token), with a link to reset
// a forgotten password and one to create an account. "Continue with Google" is on the welcome screen, above this.
// After a password reset the email arrives filled in. States: loading (button spinner), error (inline message).
import { useState } from 'react';
import { KeyboardAvoidingView, StyleSheet, View } from 'react-native';
import { Link, router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import Logo from '../../components/Logo';
import TextField from '../../components/TextField';
import KeyboardScroll from '../../components/KeyboardScroll';
import PendingJoinBanner from '../../components/join/PendingJoinBanner';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import { API_ROOT, errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';

export default function Login() {
  const { signIn } = useAuth();
  const params = useLocalSearchParams();
  const [email, setEmail] = useState(typeof params.email === 'string' ? params.email : '');
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
    <KeyboardAvoidingView style={styles.flex} behavior="padding">
      <KeyboardScroll contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <Logo size={76} />
          <AppText variant="title" style={styles.heroTitle}>
            Welcome back
          </AppText>
          <AppText style={styles.heroText}>Sign in with your email to join your church family.</AppText>
        </View>

        <View style={styles.card}>
          <PendingJoinBanner style={{ marginBottom: SPACING.md }} />
          <AppText variant="heading" style={styles.cardTitle}>
            Continue with email
          </AppText>

          {params.reset ? (
            <View style={styles.good} accessibilityRole="alert">
              <AppText variant="label" style={{ color: COLORS.success }}>
                Your password was changed. Sign in with the new one.
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
      </KeyboardScroll>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.primary },
  scroll: { flexGrow: 1 },
  hero: { alignItems: 'center', paddingTop: SPACING.md, paddingBottom: SPACING.xl, paddingHorizontal: SPACING.lg },
  heroTitle: { color: COLORS.onColor, marginTop: SPACING.md },
  heroText: { color: COLORS.primaryPale, textAlign: 'center', marginTop: 4 },
  card: { flexGrow: 1, backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, padding: SPACING.lg, paddingTop: SPACING.xl },
  cardTitle: { marginBottom: SPACING.md },
  google: { marginBottom: SPACING.xs },
  divider: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, marginVertical: SPACING.md },
  line: { flex: 1, height: 1, backgroundColor: COLORS.gray100 },
  good: { backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  banner: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  forgot: { color: COLORS.primary, alignSelf: 'flex-end', marginTop: -SPACING.xs, marginBottom: SPACING.xs },
  submit: { marginTop: SPACING.sm },
  switch: { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: SPACING.lg },
  link: { color: COLORS.primary, fontWeight: '600' },
  dev: { textAlign: 'center', color: COLORS.primaryPale, padding: SPACING.md, backgroundColor: COLORS.white, marginTop: -1 },
});
