// Push notifications to the phone when the app is closed. The app asks the member's permission, gets the phone's Expo
// push token and registers it with the server (POST /api/v1/devices); the server then pushes church news, connection
// requests and chat messages. Tapping a push opens the right screen inside the app (see lib/deeplink.js).
//
// Works on a real phone with a development or store build. It is switched off in the web preview and in Expo Go on
// Android (Expo removed push from Expo Go there), where every function here quietly does nothing.
import { useEffect, useRef } from 'react';
import { AppState, Linking, Platform } from 'react-native';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import api from './api';
import { openNotification } from './deeplink';
import { getStored, setStored } from './storage';

const TOKEN_KEY = 'push_token';
const ASKED_KEY = 'push_asked';
const REGISTERED_AT_KEY = 'push_registered_at';
const REFRESH_EVERY_MS = 6 * 60 * 60 * 1000;

/** Push needs a phone, and not Expo Go on Android. */
export const pushSupported = Platform.OS === 'ios' || (Platform.OS === 'android' && Constants.executionEnvironment !== ExecutionEnvironment.StoreClient);

let Notifications = null;
function notifications() {
  if (!pushSupported) return null;
  if (!Notifications) Notifications = require('expo-notifications');
  return Notifications;
}

// The chat the member is looking at right now: a push for it would only repeat what is on screen.
let activeChat = null;
export function setActiveChat(id) {
  activeChat = id == null ? null : String(id);
}

let configured = false;
/** Foreground behaviour and Android channels. Safe to call more than once. */
export async function configurePush() {
  const N = notifications();
  if (!N || configured) return;
  configured = true;

  N.setNotificationHandler({
    handleNotification: async (n) => {
      const data = n.request.content.data ?? {};
      const hide = data.kind === 'message' && activeChat && String(data.ref) === activeChat;
      return { shouldShowBanner: !hide, shouldShowList: !hide, shouldPlaySound: !hide, shouldSetBadge: true };
    },
  });

  if (Platform.OS === 'android') {
    const channels = [
      ['default', 'News from your churches', N.AndroidImportance.HIGH],
      ['messages', 'Messages and connections', N.AndroidImportance.HIGH],
      ['live', 'Live services', N.AndroidImportance.MAX],
      ['urgent', 'Urgent notices', N.AndroidImportance.MAX],
    ];
    for (const [id, name, importance] of channels) {
      await N.setNotificationChannelAsync(id, { name, importance, lightColor: '#BC2649', vibrationPattern: [0, 200, 100, 200] }).catch(() => {});
    }
  }
}

/** 'granted' | 'denied' | 'undetermined' | 'unsupported' */
export async function pushPermission() {
  const N = notifications();
  if (!N) return 'unsupported';
  try {
    const { status } = await N.getPermissionsAsync();
    if (status === 'granted') return 'granted';
    return status === 'denied' ? 'denied' : 'undetermined';
  } catch {
    return 'unsupported';
  }
}

function projectId() {
  const id = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  return id && !String(id).startsWith('REPLACE_') ? id : undefined;
}

/**
 * Asks for permission when `ask` is true, gets this phone's token and registers it with the server.
 * Resolves `{ status, token?, error? }` where status is 'on', 'denied', 'undetermined' or 'unsupported'.
 */
export async function enablePush({ ask = false } = {}) {
  const N = notifications();
  if (!N) return { status: 'unsupported' };
  try {
    await configurePush();
    let permission = await pushPermission();
    if (permission === 'undetermined' && ask) {
      await setStored(ASKED_KEY, true);
      const { status } = await N.requestPermissionsAsync();
      permission = status === 'granted' ? 'granted' : 'denied';
    }
    if (permission !== 'granted') return { status: permission };

    const { data: token } = await N.getExpoPushTokenAsync(projectId() ? { projectId: projectId() } : undefined);
    await api.post('/devices', { token, platform: Platform.OS, name: Constants.deviceName ?? undefined, app_version: Constants.expoConfig?.version });
    await setStored(TOKEN_KEY, token);
    await setStored(REGISTERED_AT_KEY, Date.now());
    return { status: 'on', token };
  } catch (e) {
    return { status: 'unsupported', error: e?.message ?? String(e) };
  }
}

/** Stops pushes to this phone for the signed-in member (turning it off, or just before signing out). */
export async function disablePush() {
  const token = await getStored(TOKEN_KEY, null);
  if (token) await api.delete('/devices', { data: { token } }).catch(() => {});
  await setStored(TOKEN_KEY, null);
}

/** Whether this phone has registered itself. */
export const isRegistered = async () => Boolean(await getStored(TOKEN_KEY, null));

/** Opens the phone's own settings, for a member who said no earlier. */
export const openSystemSettings = () => Linking.openSettings().catch(() => {});

/**
 * A phone that is allowed notifications keeps itself registered: the server forgets a phone it could not reach, and the token can change. Looks again
 * whenever the app comes forward, at most every six hours, so members who allowed notifications are never left out of a message to everyone.
 */
async function refreshRegistration() {
  if (!notifications()) return;
  if (Date.now() - (await getStored(REGISTERED_AT_KEY, 0)) < REFRESH_EVERY_MS) return;
  if ((await pushPermission()) === 'granted') await enablePush();
}

/** Keeps the app icon's badge equal to what is unread. */
export async function syncBadge() {
  const N = notifications();
  if (!N) return;
  try {
    const { data } = await api.get('/me');
    const me = data.data ?? data;
    await N.setBadgeCountAsync((me.unread_notifications ?? 0) + (me.unread_messages ?? 0));
  } catch {
    // Not important enough to bother the member.
  }
}

/**
 * Mount once, inside the signed-in app: registers the phone (asking permission the first time), opens the right
 * screen when a push is tapped — also when it started the app — and refreshes the badge when the app comes forward.
 */
export function usePushSetup(user) {
  const handled = useRef(null);
  const signedIn = Boolean(user?.id);

  useEffect(() => {
    const N = notifications();
    if (!N || !signedIn) return undefined;
    let cancelled = false;

    (async () => {
      await configurePush();
      const asked = await getStored(ASKED_KEY, false);
      if (!cancelled) await enablePush({ ask: !asked });
      syncBadge();

      // The push that opened the app from closed.
      const last = N.getLastNotificationResponse ? N.getLastNotificationResponse() : await N.getLastNotificationResponseAsync?.();
      const id = last?.notification?.request?.identifier;
      if (last && id && handled.current !== id) {
        handled.current = id;
        setTimeout(() => openNotification(last.notification.request.content.data ?? {}), 600);
      }
    })();

    const tap = N.addNotificationResponseReceivedListener((response) => {
      handled.current = response.notification.request.identifier;
      openNotification(response.notification.request.content.data ?? {});
    });
    const state = AppState.addEventListener('change', (s) => {
      if (s !== 'active') return;
      syncBadge();
      refreshRegistration();
    });

    return () => {
      cancelled = true;
      tap.remove();
      state.remove();
    };
  }, [signedIn]);
}
