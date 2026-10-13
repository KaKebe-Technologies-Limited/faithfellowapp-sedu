// Authentication state for the whole app: signed-in user, token, sign-in and sign-out.
// Wrap the app in <AuthProvider> and read it anywhere with useAuth().
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';
import api from './api';
import { signInWithGoogleBrowser } from './google';
import { readJson, removeFile, writeJson } from './localFiles';
import { flush, forgetEverything } from './outbox';
import { disablePush } from './push';
import { stopRadio } from './radio';
import { clearToken, getToken, onSessionExpired, setToken } from './session';

const AuthContext = createContext(null);
const USER_FILE = 'auth/user.json';

export function AuthProvider({ children }) {
  const [ready, setReady] = useState(false);
  const [token, setTokenState] = useState(null);
  const [user, setUser] = useState(null);

  // Restore the session on launch. The last known profile is kept on the phone, so the app opens at once and works
  // with no internet; the profile is refreshed in the background (and retried until it works).
  useEffect(() => {
    let retry = null;
    (async () => {
      const saved = await getToken();
      setTokenState(saved);
      if (!saved) return setReady(true);

      const cached = await readJson(USER_FILE);
      if (cached) {
        setUser(cached);
        setReady(true);
      }
      const refresh = async () => {
        try {
          const { data } = await api.get('/me', { timeout: 8000 });
          const me = data.data ?? data;
          setUser(me);
          writeJson(USER_FILE, me);
          clearInterval(retry);
          return true;
        } catch {
          // Offline or server down: keep the token; a 401 is handled by the session listener.
          return false;
        }
      };
      const ok = await refresh();
      setReady(true);
      if (!ok) retry = setInterval(refresh, 20000);
    })();
    return () => clearInterval(retry);
  }, []);

  // The API client reports a revoked or expired token.
  useEffect(
    () =>
      onSessionExpired(() => {
        removeFile(USER_FILE);
        setTokenState(null);
        setUser(null);
      }),
    [],
  );

  // Sets the signed-in profile and keeps a copy on the phone for starting up offline.
  const remember = useCallback((me) => {
    setUser(me);
    if (me) writeJson(USER_FILE, me);
  }, []);

  const signIn = useCallback(async (email, password) => {
    const { data } = await api.post('/auth/token', {
      email: email.trim(),
      password,
      device_name: `FaithFellows app (${Platform.OS})`,
    });
    await setToken(data.token);
    setTokenState(data.token);
    remember(data.user);
    return data.user;
  }, []);

  // Create an account with email + password (POST /auth/register) and sign in. Throws the API error for the form to show.
  const signUp = useCallback(async (form) => {
    const { data } = await api.post('/auth/register', {
      name: form.name.trim(),
      email: form.email.trim().toLowerCase(),
      phone: form.phone?.trim() || null,
      password: form.password,
      password_confirmation: form.password_confirmation,
      terms: true,
      device_name: `FaithFellows app (${Platform.OS})`,
    });
    await setToken(data.token);
    setTokenState(data.token);
    remember(data.user);
    return data.user;
  }, []);

  // Google sign-in / sign-up in one: returns the user, or null if the person closed the browser.
  const signInWithGoogle = useCallback(async () => {
    const data = await signInWithGoogleBrowser();
    if (!data) return null;
    await setToken(data.token);
    setTokenState(data.token);
    remember(data.user);
    return data.user;
  }, []);

  const signOut = useCallback(async () => {
    stopRadio();
    await disablePush();
    // Send what was written offline, then forget this member's notes and highlight colours on the phone.
    await Promise.race([flush(), new Promise((resolve) => setTimeout(resolve, 4000))]).catch(() => {});
    try {
      await api.delete('/auth/token');
    } catch {
      // Even if the server cannot be reached, sign out on this device.
    }
    await forgetEverything().catch(() => {});
    removeFile(USER_FILE);
    await clearToken();
    setTokenState(null);
    setUser(null);
  }, []);

  const value = useMemo(() => ({ ready, token, user, signIn, signUp, signInWithGoogle, signOut, setUser: remember }), [ready, token, user, signIn, signUp, signInWithGoogle, signOut, remember]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}
