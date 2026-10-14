// Over-the-air updates (EAS Update). When an update has been published for this build's channel, the app downloads it when it
// opens or comes back to the front, and restarts into it. Screens, text and logic change with no new APK; a new permission or a
// native package still needs a new build (see "EAS Update" in README.md).
//
// Nothing here runs in development (Expo Go, the web preview) or in a build that has no updates in it: every function is safe to call.
import { useEffect, useRef } from 'react';
import { AppState } from 'react-native';
import Constants from 'expo-constants';
import * as Updates from 'expo-updates';

/** "1.0.0" or "1.0.0 · update 1a2b3c4d" (which published update this phone runs; none means the update inside the APK). */
export function versionLabel() {
  const version = Constants.expoConfig?.version ?? '';
  const id = Updates.isEnabled && !Updates.isEmbeddedLaunch && Updates.updateId ? Updates.updateId.slice(0, 8) : null;

  return id ? `${version} · update ${id}` : version;
}

/** Mount once in the root layout. */
export function useAppUpdates() {
  const busy = useRef(false);

  useEffect(() => {
    if (__DEV__ || !Updates.isEnabled) return undefined;

    async function apply() {
      if (busy.current) return;
      busy.current = true;
      try {
        const check = await Updates.checkForUpdateAsync();
        if (check.isAvailable) {
          await Updates.fetchUpdateAsync();
          await Updates.reloadAsync();
        }
      } catch {
        // No connection, or nothing new: the app keeps running what it has.
      } finally {
        busy.current = false;
      }
    }

    apply();
    const watch = AppState.addEventListener('change', (state) => {
      if (state === 'active') apply();
    });

    return () => watch.remove();
  }, []);
}
