// Remembers that this phone has already seen the intro slides, so they show once.
// Stored beside the session token (SecureStore on phones, localStorage on the web preview).
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const KEY = 'onboarded_v1';
const isWeb = Platform.OS === 'web';

export async function hasOnboarded() {
  try {
    return (isWeb ? globalThis.localStorage?.getItem(KEY) : await SecureStore.getItemAsync(KEY)) === '1';
  } catch {
    return false;
  }
}

export async function markOnboarded() {
  try {
    if (isWeb) globalThis.localStorage?.setItem(KEY, '1');
    else await SecureStore.setItemAsync(KEY, '1');
  } catch {
    // Not being able to remember just means the slides show again next time.
  }
}
