// Entry route ("/"): waits for the saved session to load, then sends the user to the tabs (signed in), the
// first-run introduction (signed out, never seen it) or the welcome screen (signed out, seen it).
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Redirect } from 'expo-router';
import { COLORS } from '../constants/theme';
import { useAuth } from '../lib/auth';
import { hasOnboarded } from '../lib/onboarding';

export default function Index() {
  const { ready, token } = useAuth();
  const [onboarded, setOnboarded] = useState(null);

  useEffect(() => {
    hasOnboarded().then(setOnboarded);
  }, []);

  if (!ready || onboarded === null) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  if (token) return <Redirect href="/(tabs)" />;
  return <Redirect href={onboarded ? '/(auth)/welcome' : '/onboarding'} />;
}
