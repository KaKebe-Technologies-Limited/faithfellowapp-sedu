// Root layout: loads the Clarity City fonts (the website's typeface), keeps the splash screen up until the app is ready,
// provides authentication to every screen and defines the top-level navigation stack.
import { useEffect } from 'react';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import {
  ClarityCity_400Regular,
  ClarityCity_500Medium,
  ClarityCity_600SemiBold,
  ClarityCity_700Bold,
  useFonts,
} from '@expo-google-fonts/clarity-city';
import { COLORS } from '../constants/theme';
import { AuthProvider, useAuth } from '../lib/auth';
import { usePushSetup } from '../lib/push';

SplashScreen.preventAutoHideAsync().catch(() => {});

function Navigator({ fontsLoaded }) {
  const { ready, user } = useAuth();
  usePushSetup(user);

  useEffect(() => {
    if (fontsLoaded && ready) SplashScreen.hideAsync().catch(() => {});
  }, [fontsLoaded, ready]);

  if (!fontsLoaded) return null;

  return (
    <>
      <StatusBar style="light" />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: COLORS.primary },
          headerTintColor: COLORS.white,
          headerTitleStyle: { fontWeight: '600' },
          contentStyle: { backgroundColor: COLORS.white },
          headerShown: false,
        }}
      />
    </>
  );
}

export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    ClarityCity_400Regular,
    ClarityCity_500Medium,
    ClarityCity_600SemiBold,
    ClarityCity_700Bold,
  });

  return (
    <AuthProvider>
      <Navigator fontsLoaded={fontsLoaded} />
    </AuthProvider>
  );
}
