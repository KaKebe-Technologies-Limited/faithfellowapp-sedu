// Root layout: loads the Clarity City fonts (the website's typeface), keeps the splash screen up until the app is ready,
// shows the opening flash (a few seconds of rolling photos) once each time the app is opened, provides authentication to every screen
// and defines the top-level navigation stack.
import { useEffect, useMemo, useState } from 'react';
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
import OpeningFlash from '../components/OpeningFlash';
import { COLORS } from '../constants/theme';
import { useActivityTracking } from '../lib/activity';
import { loadAppMedia, refreshAppMedia, sourceOf, useAppMedia } from '../lib/appMedia';
import { markFlashDone } from '../lib/appStart';
import { refreshFlash, storedFlash } from '../lib/openingFlash';
import { AuthProvider, useAuth } from '../lib/auth';
import { useJoinLink } from '../lib/join';
import { useOfflineSync } from '../lib/outbox';
import { syncNotes } from '../lib/notesSync';
import { usePushSetup } from '../lib/push';
import { useAppUpdates } from '../lib/updates';
import { useRadioLifecycle } from '../lib/radio';

SplashScreen.preventAutoHideAsync().catch(() => {});

function Navigator({ fontsLoaded }) {
  const { ready, token, user } = useAuth();
  usePushSetup(user);
  useOfflineSync(user, syncNotes);
  useRadioLifecycle();
  useActivityTracking(user); // the figures on your profile: minutes reading and listening, what you shared
  useAppUpdates();
  const [flash, setFlash] = useState(true); // the opening flash, once each time the app is opened
  const [flashSettings, setFlashSettings] = useState(null); // what the platform admin set for it (the last answer, kept on the phone)

  const [mediaLoaded, setMediaLoaded] = useState(false);
  const media = useAppMedia();
  const openingPhotos = useMemo(() => media.opening.map(sourceOf), [media.opening]); // only the admin's: none are built into the app

  useEffect(() => {
    storedFlash().then(setFlashSettings);
    loadAppMedia().finally(() => setMediaLoaded(true));
    refreshFlash(); // the answers are kept for the next time the app is opened
    refreshAppMedia();
  }, []);

  // No flash when the admin switched it off or has not added any photos (nothing else waits on it then).
  useEffect(() => {
    if (flashSettings && mediaLoaded && (!flashSettings.enabled || openingPhotos.length === 0)) {
      setFlash(false);
      markFlashDone();
    }
  }, [flashSettings, mediaLoaded, openingPhotos.length]);
  const segments = useSegments();
  useJoinLink({ ready, token, user, place: segments[0] }); // a church's join link: keep it through sign-up, then follow the church
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
      {flash && ready && mediaLoaded && flashSettings?.enabled && openingPhotos.length > 0 ? (
        <OpeningFlash photos={openingPhotos} seconds={flashSettings.seconds} speed={flashSettings.speed} title={flashSettings.title} text={flashSettings.text} onDone={() => { setFlash(false); markFlashDone(); }} />
      ) : null}
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
