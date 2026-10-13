// Sharing an approximate place so believers nearby can find each other. The phone asks permission once ("while using
// the app"), the server keeps the place rounded to about 1 km, and other members only ever see how far away someone is
// ("About 10 km away"), never where. Switch it off any time (PUT / DELETE /api/v1/me/location).
import * as Location from 'expo-location';
import api, { errorMessage } from './api';

/** Resolves `{ ok: true, user }` or `{ ok: false, message }`. */
export async function shareMyLocation() {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== 'granted') return { ok: false, message: 'Allow location for FaithFellows in your phone settings to find believers near you.' };
    const here = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
    const { data } = await api.put('/me/location', { latitude: here.coords.latitude, longitude: here.coords.longitude });
    return { ok: true, user: data.data };
  } catch (e) {
    return { ok: false, message: e?.response ? errorMessage(e) : 'We could not find your location. Check that location is switched on and try again.' };
  }
}

export async function stopSharingLocation() {
  try {
    const { data } = await api.delete('/me/location');
    return { ok: true, user: data.data };
  } catch (e) {
    return { ok: false, message: errorMessage(e) };
  }
}
