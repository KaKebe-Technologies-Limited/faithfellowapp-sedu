// Root layout: loads the Space Grotesk fonts, keeps the splash screen up until the app is ready,
// provides authentication to every screen and defines the top-level navigation stack.
import { useEffect } from 'react';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import {
  SpaceGrotesk_400Regular,
  SpaceGrotesk_500Medium,
  SpaceGrotesk_600SemiBold,
  SpaceGrotesk_700Bold,
  useFonts,
} from '@expo-google-fonts/space-grotesk';
import { COLORS } from '../constants/theme';
import { AuthProvider, useAuth } from '../lib/auth';

SplashScreen.preventAutoHideAsync().catch(() => {});

function Navigator({ fontsLoaded }) {
  const { ready } = useAuth();

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
    SpaceGrotesk_400Regular,
    SpaceGrotesk_500Medium,
    SpaceGrotesk_600SemiBold,
    SpaceGrotesk_700Bold,
  });

  return (
    <AuthProvider>
      <Navigator fontsLoaded={fontsLoaded} />
    </AuthProvider>
  );
}
