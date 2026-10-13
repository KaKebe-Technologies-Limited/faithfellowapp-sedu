// Tiny key-value store for small preferences (last chapter read, Bible text size…): SecureStore on phones,
// localStorage in the web preview. Every call is safe — a failure just means the preference is not remembered.
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const isWeb = Platform.OS === 'web';

export async function getStored(key, fallback = null) {
  try {
    const raw = isWeb ? globalThis.localStorage?.getItem(key) : await SecureStore.getItemAsync(key);
    return raw == null ? fallback : JSON.parse(raw);
  } catch {
    return fallback;
  }
}

export async function setStored(key, value) {
  try {
    const raw = JSON.stringify(value);
    if (isWeb) globalThis.localStorage?.setItem(key, raw);
    else await SecureStore.setItemAsync(key, raw);
  } catch {
    // Not remembered; nothing else to do.
  }
}
