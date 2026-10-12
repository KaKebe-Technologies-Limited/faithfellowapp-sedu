// Stores the API token securely (Keychain / Keystore via expo-secure-store; localStorage on web)
// and lets the rest of the app react when the server says the session is no longer valid.
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const KEY = 'auth_token';
const isWeb = Platform.OS === 'web';
const expiredListeners = new Set();

export async function getToken() {
  try {
    return isWeb ? globalThis.localStorage?.getItem(KEY) ?? null : await SecureStore.getItemAsync(KEY);
  } catch {
    return null;
  }
}

export async function setToken(token) {
  try {
    if (isWeb) globalThis.localStorage?.setItem(KEY, token);
    else await SecureStore.setItemAsync(KEY, token);
  } catch {
    // Storage can fail on locked-down devices; the session then lasts until the app closes.
  }
}

export async function clearToken() {
  try {
    if (isWeb) globalThis.localStorage?.removeItem(KEY);
    else await SecureStore.deleteItemAsync(KEY);
  } catch {
    // Nothing to clear.
  }
}

/** Called by the API client on a 401; the auth provider listens and signs the user out. */
export function expireSession() {
  expiredListeners.forEach((fn) => fn());
}

export function onSessionExpired(fn) {
  expiredListeners.add(fn);
  return () => expiredListeners.delete(fn);
}
