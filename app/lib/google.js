// "Continue with Google" for the app, through the system browser (no native Google library, so it
// works in Expo Go and in store builds). Steps:
//   1. make a secret PKCE verifier and send only its hash (the challenge) to the server;
//   2. open <server>/auth/google/app/start in the in-app browser → Google → the server verifies the person;
//   3. the browser returns to the app link (…/auth?code=ONE_TIME_CODE);
//   4. trade that code + the verifier for an API token at POST /api/v1/auth/google/exchange.
// The token never travels in a link, and the code is useless without the verifier kept in this function.
import * as Crypto from 'expo-crypto';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';
import api, { SITE_URL } from './api';

// While testing on a phone the sign-in page can live on a different (public https) address than the API.
const AUTH_BASE = (process.env.EXPO_PUBLIC_GOOGLE_AUTH_URL || SITE_URL).replace(/\/+$/, '');

// Google sign-in is built but switched off for now; set EXPO_PUBLIC_GOOGLE_SIGNIN=1 in app/.env to show the button.
export const GOOGLE_ENABLED = process.env.EXPO_PUBLIC_GOOGLE_SIGNIN === '1';

export class GoogleSignInError extends Error {
  constructor(message, code = 'failed') {
    super(message);
    this.code = code;
  }
}

const toHex = (bytes) => Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
const toBase64Url = (base64) => base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

/**
 * Resolves with the server's sign-in response ({ token, user, is_new_user }), or null if the person
 * closed the browser. Throws GoogleSignInError with a message that is safe to show.
 */
export async function signInWithGoogleBrowser() {
  const verifier = toHex(await Crypto.getRandomBytesAsync(48)); // 96 hex characters
  const challenge = toBase64Url(await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, verifier, { encoding: Crypto.CryptoEncoding.BASE64 }));

  const returnUrl = Linking.createURL('auth'); // faithfellows://auth (exp://…/--/auth in Expo Go)
  const startUrl = `${AUTH_BASE}/auth/google/app/start?redirect=${encodeURIComponent(returnUrl)}&challenge=${challenge}`;

  let result;
  try {
    result = await WebBrowser.openAuthSessionAsync(startUrl, returnUrl);
  } catch (e) {
    throw new GoogleSignInError('Google sign-in cannot open in this environment. Use a phone or a development build.', 'unsupported');
  }
  if (result.type !== 'success') return null; // closed or cancelled

  const { queryParams = {} } = Linking.parse(result.url);
  if (queryParams.error === 'cancelled') return null;
  if (queryParams.error) {
    throw new GoogleSignInError(
      queryParams.error === 'denied' && queryParams.message ? String(queryParams.message) : 'We could not verify your Google sign-in. Please try again.',
      String(queryParams.error),
    );
  }
  if (!queryParams.code) throw new GoogleSignInError('Google sign-in did not finish. Please try again.');

  const { data } = await api.post('/auth/google/exchange', {
    code: String(queryParams.code),
    verifier,
    device_name: `FaithFellows app (${Platform.OS})`,
  });
  return data;
}
