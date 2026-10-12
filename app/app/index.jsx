// Entry route ("/"): waits for the saved session to load, then sends the user to the
// tabs (signed in) or the login screen (signed out).
import { ActivityIndicator, View } from 'react-native';
import { Redirect } from 'expo-router';
import { COLORS } from '../constants/theme';
import { useAuth } from '../lib/auth';

export default function Index() {
  const { ready, token } = useAuth();

  if (!ready) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  return <Redirect href={token ? '/(tabs)' : '/(auth)/login'} />;
}
