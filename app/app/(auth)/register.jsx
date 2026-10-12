// Register screen: validates the form and calls POST /api/v1/auth/register. That endpoint is not
// built yet (it is next on the roadmap), so until then a 404 offers to continue on the website.
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Linking, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { Link, router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import TextField from '../../components/TextField';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import api, { SITE_URL, errorMessage, fieldErrors } from '../../lib/api';
import { setToken } from '../../lib/session';
import { useAuth } from '../../lib/auth';

export default function Register() {
  const { setUser } = useAuth();
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
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
    setErrors(found);
    return Object.keys(found).length === 0;
  }

  async function submit() {
    if (!validate()) return;
    setLoading(true);
    setError(null);
    try {
      const { data } = await api.post('/auth/register', { ...form, device_name: `FaithFellows app (${Platform.OS})` });
      await setToken(data.token);
      setUser(data.user);
      router.replace('/(tabs)');
    } catch (e) {
      if ([404, 405].includes(e.response?.status)) {
        Alert.alert('Sign-up on the website for now', 'Creating an account inside the app is coming soon. You can register on the FaithFellows website and then sign in here.', [
          { text: 'Not now', style: 'cancel' },
          { text: 'Open website', onPress: () => Linking.openURL(`${SITE_URL}/register`) },
        ]);
      } else {
        const fields = fieldErrors(e);
        setErrors(fields);
        if (!Object.keys(fields).length) setError(errorMessage(e));
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.hero}>
          <AppText variant="title" style={{ color: COLORS.white }}>
            Create your account
          </AppText>
          <AppText style={{ color: COLORS.primaryPale, marginTop: 4 }}>Follow your church and grow together.</AppText>
        </View>

        <View style={styles.card}>
          {error ? (
            <View style={styles.banner} accessibilityRole="alert">
              <AppText variant="label" style={{ color: COLORS.danger }}>
                {error}
              </AppText>
            </View>
          ) : null}

          <TextField label="Full name" value={form.name} onChangeText={set('name')} error={errors.name} autoComplete="name" textContentType="name" placeholder="Grace Akello" />
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
            returnKeyType="go"
            onSubmitEditing={submit}
          />

          <Button title="Create account" onPress={submit} loading={loading} style={styles.submit} />

          <View style={styles.switch}>
            <AppText>Already have an account? </AppText>
            <Link href="/(auth)/login" style={styles.link}>
              Sign in
            </Link>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.primary },
  scroll: { flexGrow: 1 },
  hero: { paddingBottom: SPACING.lg, paddingHorizontal: SPACING.lg },
  card: { flexGrow: 1, backgroundColor: COLORS.white, borderTopLeftRadius: RADIUS.xl, borderTopRightRadius: RADIUS.xl, padding: SPACING.lg, paddingTop: SPACING.xl },
  banner: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginBottom: SPACING.md },
  submit: { marginTop: SPACING.sm },
  switch: { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap', marginTop: SPACING.lg },
  link: { color: COLORS.primary, fontWeight: '600' },
});
