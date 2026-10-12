// Landing route for the "…/auth?code=…" link Google sign-in returns to. The in-app browser normally
// consumes that link and hands it to lib/google.js; if the system delivers it to the app instead
// (Android), this screen just shows a spinner and moves on while the sign-in finishes.
import { ActivityIndicator, View } from 'react-native';
import { Redirect } from 'expo-router';
import { COLORS } from '../constants/theme';
import { useAuth } from '../lib/auth';

export default function AuthReturn() {
  const { token } = useAuth();

  if (token) return <Redirect href="/(tabs)" />;

  return (
    <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
      <ActivityIndicator size="large" color={COLORS.primary} />
    </View>
  );
}
