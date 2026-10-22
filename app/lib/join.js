// A church's join link in the app. The link (https://faithfellows.com/join/GRACE7KQ, or faithfellows://join/GRACE7KQ) opens the app on the church;
// the code is kept on the phone until the person has an account, then the church is followed for them. When the app was installed after the link
// was opened, the code can still be found: from the Play Store install referrer (Android), or by the server noticing which church's link this phone
// opened a moment ago, which the person is asked to confirm (many phones share one address, so it is never applied without a yes).
import { useEffect, useState, useSyncExternalStore } from 'react';
import { Alert, Platform } from 'react-native';
import { router } from 'expo-router';
import api, { errorMessage } from './api';
import { getStored, setStored } from './storage';

const PENDING = 'pending_join_v1';
const CHECKED = 'join_checked_v1';

// { loaded, pending: { code, church, requires_approval, confirmed } | null }
let snapshot = { loaded: false, pending: null };
const listeners = new Set();
const set = (patch) => {
  snapshot = { ...snapshot, ...patch };
  listeners.forEach((l) => l());
};

export const usePendingJoin = () => useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => snapshot);

/** "https://faithfellows.com/join/grace7kq", "faithfellows://join/GRACE7KQ" or "grace 7kq" all become "GRACE7KQ". */
export function parseJoinCode(text) {
  const raw = String(text ?? '').trim();
  const fromLink = raw.match(/\/join\/([A-Za-z0-9-]+)/);

  return (fromLink ? fromLink[1] : raw).replace(/[^A-Za-z0-9]/g, '').toUpperCase();
}

/** The church behind a code: { code, church: { id, slug, name, logo_url, tagline, city, denomination, followers }, requires_approval }. Throws when there is none. */
export async function lookupChurch(code) {
  const { data } = await api.get(`/join/${encodeURIComponent(parseJoinCode(code))}`);

  return data.data;
}

export async function loadPending() {
  if (snapshot.loaded) return;
  const saved = await getStored(PENDING);
  set({ loaded: true, pending: saved?.code ? saved : null });
}

async function save(pending) {
  set({ loaded: true, pending });
  await setStored(PENDING, pending);
}

/** Keeps the church of a link until the person has an account. `confirmed` is false when the app only guessed it (the person is asked). */
export const rememberJoin = (info, confirmed = true) => save({ code: info.code, church: info.church, requires_approval: info.requires_approval, confirmed });
export const forgetJoin = () => save(null);
export const confirmJoin = () => (snapshot.pending ? save({ ...snapshot.pending, confirmed: true }) : Promise.resolve());

let following = false;

/**
 * Follows the church that was kept, for the signed-in member. Returns { church, status, message } (status 'active' or 'pending'), { church, error } when the
 * church cannot be followed (for example the limit of churches was reached), or null when there is nothing to do or the phone could not reach the server
 * (the church is then kept for the next time).
 */
export async function followPending() {
  const pending = snapshot.pending;
  if (!pending?.confirmed || following) return null;
  following = true;
  try {
    const { data } = await api.post(`/join/${pending.code}/follow`);
    await forgetJoin();

    return { church: pending.church, status: data.status, message: data.message };
  } catch (e) {
    if (!e?.response) return null;
    await forgetJoin();

    return e.response.status === 404 ? null : { church: pending.church, error: errorMessage(e) };
  } finally {
    following = false;
  }
}

/** The very first start after an install: where did this person come from? Runs once, and only when no church was kept already. */
export async function checkDeferredJoin() {
  if (await getStored(CHECKED)) return;
  if (snapshot.pending) return setStored(CHECKED, true);

  // 1. Android: the Play Store carries the code of the link the app was installed from.
  if (Platform.OS === 'android') {
    try {
      // eslint-disable-next-line global-require
      const Application = require('expo-application');
      const referrer = decodeURIComponent((await Application.getInstallReferrerAsync()) ?? '');
      const found = /faithfellows_join=([A-Za-z0-9]+)/.exec(referrer);
      if (found) {
        await rememberJoin(await lookupChurch(found[1]), true);

        return setStored(CHECKED, true);
      }
    } catch {
      // Not installed from the Play Store, or no referrer: try the next way.
    }
  }

  // 2. The church whose link this phone opened a moment ago: offered to the person, who says yes or no.
  if (Platform.OS === 'android' || Platform.OS === 'ios') {
    try {
      const { data } = await api.post('/join/match', { platform: Platform.OS });
      if (data.data) await rememberJoin(data.data, false);
      await setStored(CHECKED, true);
    } catch {
      // No connection yet: ask again at the next start.
    }
  }
}

/**
 * Mount once in the root layout. Loads the church that was kept, looks for one after a fresh install while signed out, and, the moment the person
 * is signed in, follows it and opens the church (an Alert says what happened). `place` is the first part of the current route.
 */
export function useJoinLink({ ready, token, user, place }) {
  const { loaded, pending } = usePendingJoin();
  const [done, setDone] = useState(null);

  useEffect(() => {
    loadPending();
  }, []);

  useEffect(() => {
    if (ready && loaded && !token) checkDeferredJoin();
  }, [ready, loaded, token]);

  useEffect(() => {
    if (!user || !loaded || !pending) return;
    (async () => {
      // A church the app only guessed, and the person did not say yes to, is not joined behind their back.
      if (!pending.confirmed) return forgetJoin();
      const result = await followPending();
      if (result) setDone(result);
    })();
  }, [user?.id, loaded, pending?.code, pending?.confirmed]); // eslint-disable-line react-hooks/exhaustive-deps

  // Wait until the person is on a main screen (after sign-up the app is still moving there), then open the church.
  useEffect(() => {
    if (!done || place !== '(tabs)') return;
    const result = done;
    setDone(null);
    if (result.error) {
      Alert.alert('We could not add you to the church', result.error);

      return;
    }
    router.push({ pathname: '/church/[slug]', params: { slug: result.church.slug } });
    Alert.alert(result.status === 'pending' ? 'Request sent' : `Welcome to ${result.church.name}`, result.message);
  }, [done, place]);
}
