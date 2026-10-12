// Authentication state for the whole app: signed-in user, token, sign-in and sign-out.
// Wrap the app in <AuthProvider> and read it anywhere with useAuth().
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';
import api from './api';
import { clearToken, getToken, onSessionExpired, setToken } from './session';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [ready, setReady] = useState(false);
  const [token, setTokenState] = useState(null);
  const [user, setUser] = useState(null);

  // Restore the session on launch.
  useEffect(() => {
    (async () => {
      const saved = await getToken();
      setTokenState(saved);
      if (saved) {
        try {
          const { data } = await api.get('/me');
          setUser(data.data ?? data);
        } catch {
          // Offline or server down: keep the token; a 401 is handled by the session listener.
        }
      }
      setReady(true);
    })();
  }, []);

  // The API client reports a revoked or expired token.
  useEffect(
    () =>
      onSessionExpired(() => {
        setTokenState(null);
        setUser(null);
      }),
    [],
  );

  const signIn = useCallback(async (email, password) => {
    const { data } = await api.post('/auth/token', {
      email: email.trim(),
      password,
      device_name: `FaithFellows app (${Platform.OS})`,
    });
    await setToken(data.token);
    setTokenState(data.token);
    setUser(data.user);
    return data.user;
  }, []);

  const signOut = useCallback(async () => {
    try {
      await api.delete('/auth/token');
    } catch {
      // Even if the server cannot be reached, sign out on this device.
    }
    await clearToken();
    setTokenState(null);
    setUser(null);
  }, []);

  const value = useMemo(() => ({ ready, token, user, signIn, signOut, setUser }), [ready, token, user, signIn, signOut]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}
