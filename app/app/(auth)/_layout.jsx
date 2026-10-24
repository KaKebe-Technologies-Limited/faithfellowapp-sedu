// Layout for the signed-out screens (welcome, sign in, create account, forgot password): a plain magenta header, and a
// redirect into the app if the user is already signed in.
import { Redirect, Stack } from 'expo-router';
import { COLORS } from '../../constants/theme';
import { useAuth } from '../../lib/auth';

export default function AuthLayout() {
  const { ready, token } = useAuth();

  if (ready && token) return <Redirect href="/(tabs)" />;

  return (
    <Stack
      screenOptions={{
        headerShown: true,
        headerStyle: { backgroundColor: COLORS.primary },
        headerTintColor: COLORS.onColor,
        headerTitle: '',
        headerShadowVisible: false,
        contentStyle: { backgroundColor: COLORS.white },
      }}
    >
      <Stack.Screen name="welcome" options={{ headerShown: false }} />
    </Stack>
  );
}
