// Root layout: loads the Clarity City fonts (the website's typeface), keeps the splash screen up until the app is ready,
// provides authentication to every screen and defines the top-level navigation stack.
import { useEffect } from 'react';
import { View } from 'react-native';
import { Stack, useSegments } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import {
  ClarityCity_400Regular,
  ClarityCity_500Medium,
  ClarityCity_600SemiBold,
  ClarityCity_700Bold,
  useFonts,
} from '@expo-google-fonts/clarity-city';
import NowPlayingBar from '../components/NowPlayingBar';
import { COLORS } from '../constants/theme';
import { AuthProvider, useAuth } from '../lib/auth';
import { useOfflineSync } from '../lib/outbox';
import { syncNotes } from '../lib/notesSync';
import { usePushSetup } from '../lib/push';
import { useAppUpdates } from '../lib/updates';
import { useRadioLifecycle } from '../lib/radio';

SplashScreen.preventAutoHideAsync().catch(() => {});

function Navigator({ fontsLoaded }) {
  const { ready, user } = useAuth();
  usePushSetup(user);
  useOfflineSync(user, syncNotes);
  useRadioLifecycle();
  useAppUpdates();
  const segments = useSegments();
  const insets = useSafeAreaInsets();
  // The tab screens carry the player bar inside their own tab bar; every other screen gets it here, at the bottom.
  const showBar = !!user && !['(tabs)', '(auth)', 'onboarding', 'radio', 'listen'].includes(segments[0]);

  useEffect(() => {
    if (fontsLoaded && ready) SplashScreen.hideAsync().catch(() => {});
  }, [fontsLoaded, ready]);

  if (!fontsLoaded) return null;

  return (
    <View style={{ flex: 1 }}>
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
      {showBar ? <NowPlayingBar bottomInset={insets.bottom} /> : null}
    </View>
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
