// The app's look (light, dark or girls) is chosen once, when the app opens: constants/theme.js reads the choice kept on the phone and every screen's
// styles are built with those colours. So changing the look saves the choice and starts the app's JavaScript again, which takes a second or two;
// the opening photos are skipped that one time.
import { DevSettings, Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import * as Updates from 'expo-updates';
import { THEME, THEMES, THEME_KEY } from '../constants/theme';

const SKIP_FLASH_KEY = 'ff_skip_flash';

export const LOOKS = [
  { key: 'light', title: 'Light', text: 'The classic FaithFellows look.', icon: 'sunny' },
  { key: 'dark', title: 'Dark', text: 'Easy on the eyes at night, and saves battery.', icon: 'moon' },
  { key: 'girls', title: 'Rose', text: 'A soft, stylish look in pinks, with rounder corners.', icon: 'flower' },
];

/** The look this run of the app is using. */
export const currentLook = () => THEME;

function write(key, value) {
  try {
    if (Platform.OS === 'web') globalThis.localStorage?.setItem(key, value);
    else SecureStore.setItem(key, value);
  } catch {
    // Not remembered; the look simply stays as it was.
  }
}

function read(key) {
  try {
    return Platform.OS === 'web' ? globalThis.localStorage?.getItem(key) : SecureStore.getItem(key);
  } catch {
    return null;
  }
}

/** True once when the app was started again only to change its look (so the opening photos do not play a second time). */
export function consumeSkipFlash() {
  const skip = read(SKIP_FLASH_KEY) === '1';
  if (skip) write(SKIP_FLASH_KEY, '0');

  return skip;
}

/** Saves the look and starts the app again with it. Returns false when it is already the look in use. */
export async function chooseLook(name) {
  if (!THEMES.includes(name) || name === THEME) return false;
  write(THEME_KEY, name);
  write(SKIP_FLASH_KEY, '1');
  if (Platform.OS === 'web') {
    globalThis.location?.reload();
    return true;
  }
  try {
    await Updates.reloadAsync();
  } catch {
    try {
      DevSettings.reload();
    } catch {
      // The next time the app is opened it will have the new look.
    }
  }

  return true;
}
