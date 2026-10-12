// "Continue with Google" button: opens Google sign-in, shows a spinner while the browser is open,
// and an inline message if something goes wrong. Used on the login and sign-up screens.
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import { errorMessage } from '../lib/api';
import { useAuth } from '../lib/auth';
import { GoogleSignInError } from '../lib/google';
import AppText from './AppText';

export default function GoogleButton({ title = 'Continue with Google' }) {
  const { signInWithGoogle } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  async function press() {
    setLoading(true);
    setError(null);
    try {
      const signedIn = await signInWithGoogle();
      if (signedIn) router.replace('/(tabs)');
    } catch (e) {
      setError(e instanceof GoogleSignInError ? e.message : errorMessage(e));
    } finally {
      setLoading(false);
    }
  }

  return (
    <View>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={title}
        accessibilityState={{ busy: loading, disabled: loading }}
        disabled={loading}
        onPress={press}
        style={({ pressed }) => [styles.button, pressed && { opacity: 0.85 }, loading && { opacity: 0.7 }]}
      >
        {loading ? <ActivityIndicator color={COLORS.black} /> : <Ionicons name="logo-google" size={20} color="#4285F4" />}
        <AppText style={styles.text}>{loading ? 'Opening Google…' : title}</AppText>
      </Pressable>
      {error ? (
        <View style={styles.error} accessibilityRole="alert">
          <AppText variant="label" style={{ color: COLORS.danger }}>
            {error}
          </AppText>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  button: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: SPACING.sm + 2,
    borderRadius: RADIUS.full,
    borderWidth: 1.5,
    borderColor: COLORS.gray300,
    backgroundColor: COLORS.white,
  },
  text: { fontFamily: FONTS.semibold, fontSize: 16, color: COLORS.black },
  error: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: SPACING.md, marginTop: SPACING.sm },
});
