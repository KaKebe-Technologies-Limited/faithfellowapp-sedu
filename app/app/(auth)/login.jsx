// Sign in with email: the "Continue with email" step after the welcome screen (POST /api/v1/auth/token), with a link to reset
// a forgotten password and one to create an account. "Continue with Google" is on the welcome screen, above this.
// After a password reset the email arrives filled in. States: loading (button spinner), error (inline message).
// An account that uses an email code is asked for the six digits next (POST /api/v1/auth/two-factor).
// "Admin sign in" (?admin=1) is the same form for church and platform admins: it ends in their tools (the platform for a super admin, the
// church's admin for staff) and turns away an account that manages nothing.
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
  const { signIn, verifyTwoFactor } = useAuth();
  const params = useLocalSearchParams();
  const [email, setEmail] = useState(typeof params.email === 'string' ? params.email : '');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});
  const admin = params.admin === '1';
  const [code, setCode] = useState('');
  const [asked, setAsked] = useState(null); // the message about the emailed code, once the password was right

  // Where a signed-in person goes: a member to Home; in admin sign in, a super admin to the platform tools and staff to their church's admin.
  function arrive(user) {
    if (!admin) return router.replace('/(tabs)');
    if (user?.is_super_admin) return router.replace('/platform');

    return router.replace('/admin');
  }

  // An account that manages nothing is turned away before it is signed in, with a plain sentence.
  function problem(e) {
    if (e?.notAdmin) {
      setAsked(null);
      setError('This account does not manage a church or the platform. Use the normal sign in instead.');

      return true;
    }
    const fields = fieldErrors(e);
    setErrors(fields);
    if (!Object.keys(fields).length) setError(errorMessage(e));

    return false;
  }

  async function confirm() {
    if (code.trim().length !== 6) {
      setErrors({ code: 'Enter the 6 digits from your email.' });
      return;
    }
    setLoading(true);
    setError(null);
    setErrors({});
    try {
      arrive(await verifyTwoFactor(email, code, { admin }));
    } catch (e) {
      problem(e);
    } finally {
      setLoading(false);
    }
  }

  async function submit() {
    if (!email.trim() || !password) {
      setErrors({ email: email.trim() ? undefined : 'Enter your email.', password: password ? undefined : 'Enter your password.' });
      return;
    }
    setLoading(true);
    setError(null);
    setErrors({});
    try {
      const result = await signIn(email, password, { admin });
      if (result?.twoFactor) {
        setAsked(result.message);
        setCode('');
      } else {
        arrive(result);
      }
    } catch (e) {
      problem(e);
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
            {admin ? 'Admin sign in' : 'Welcome back'}
          </AppText>
          <AppText style={styles.heroText}>{admin ? 'Manage your church, or the whole platform, from the app.' : 'Sign in with your email to join your church family.'}</AppText>
        </View>

        <View style={styles.card}>
          <PendingJoinBanner style={{ marginBottom: SPACING.md }} />
          <AppText variant="heading" style={styles.cardTitle}>
            {asked ? 'Enter your code' : admin ? 'Sign in as an admin' : 'Continue with email'}
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

          {asked ? (
            <>
              <AppText style={styles.codeText}>{asked}</AppText>
              <TextField
                label="Code"
                value={code}
                onChangeText={(v) => setCode(v.replace(/\D/g, '').slice(0, 6))}
                error={errors.code}
                keyboardType="number-pad"
                autoComplete="one-time-code"
                textContentType="oneTimeCode"
                placeholder="6 digits"
                returnKeyType="go"
                onSubmitEditing={confirm}
              />
              <Button title="Confirm" onPress={confirm} loading={loading} style={styles.submit} />
              <AppText variant="label" style={styles.forgot} onPress={submit} accessibilityRole="link">
                Send a new code
              </AppText>
              <AppText variant="label" style={styles.forgot} onPress={() => { setAsked(null); setCode(''); setErrors({}); setError(null); }} accessibilityRole="link">
                Use a different account
              </AppText>
            </>
          ) : (
            <>
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
            </>
          )}

          <View style={styles.switch}>
            {admin ? (
              <Link href="/(auth)/login" replace style={styles.link}>
                Sign in as a member instead
              </Link>
            ) : (
              <>
                <AppText>New to FaithFellows? </AppText>
                <Link href="/(auth)/register" style={styles.link}>
                  Create an account
                </Link>
              </>
            )}
          </View>
          {!admin ? (
            <View style={styles.switch}>
              <Link href="/(auth)/login?admin=1" replace style={styles.link}>
                Church or platform admin? Sign in here
              </Link>
            </View>
          ) : null}
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
  codeText: { color: COLORS.gray900, marginBottom: SPACING.md },
  switch: { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: SPACING.lg },
  link: { color: COLORS.primary, fontWeight: '600' },
  dev: { textAlign: 'center', color: COLORS.primaryPale, padding: SPACING.md, backgroundColor: COLORS.white, marginTop: -1 },
});
