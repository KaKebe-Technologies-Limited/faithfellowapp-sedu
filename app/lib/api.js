// Axios client for the FaithFellows Laravel API (/api/v1) plus small helpers:
// friendly error messages, the /api/ping health check, and media URL fixing for local testing.
import axios from 'axios';
import Constants from 'expo-constants';
import { clearToken, expireSession, getToken } from './session';

const ENV = Constants.expoConfig?.extra?.apiUrl || {};
const appEnv = process.env.EXPO_PUBLIC_APP_ENV || 'development';

/**
 * While developing, the computer that serves the app to the phone is also the one running the API, so its address is
 * known from the dev server itself (for example 192.168.0.103:8081 -> http://192.168.0.103:8000/api). That way the phone
 * keeps working when the computer's address changes (home Wi-Fi, a phone hotspot, an office network).
 */
function devServerApi() {
  if (!__DEV__) return '';
  const fromBrowser = typeof document !== 'undefined' ? globalThis.location?.hostname : '';
  const host = String(Constants.expoConfig?.hostUri || Constants.expoGoConfig?.debuggerHost || fromBrowser || '').split(':')[0];

  return host && !host.includes('exp.direct') ? `http://${host}:8000/api` : '';
}

// EXPO_PUBLIC_API_URL wins (set it to a real address, or leave it as "auto" / empty while developing to use the address of the
// computer running the dev server); otherwise the per-environment URL from app.json.
const configured = (process.env.EXPO_PUBLIC_API_URL || '').trim();
export const API_ROOT = ((configured && configured !== 'auto' ? configured : devServerApi()) || ENV[appEnv] || ENV.development || '').replace(/\/+$/, '');
export const SITE_URL = API_ROOT.replace(/\/api$/, '');

const api = axios.create({
  baseURL: `${API_ROOT}/v1`,
  timeout: 15000,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

api.interceptors.request.use(async (config) => {
  const token = await getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  async (error) => {
    // A 401 on a signed-in request means the token expired or was revoked.
    if (error.response?.status === 401 && error.config?.headers?.Authorization) {
      await clearToken();
      expireSession();
    }
    return Promise.reject(error);
  },
);

/** Health check: GET /api/ping (not under /v1). Resolves with { status, version, ms }. */
export async function ping() {
  const started = Date.now();
  const { data } = await axios.get(`${API_ROOT}/ping`, { timeout: 8000, headers: { Accept: 'application/json' } });
  return { ...data, ms: Date.now() - started };
}

// The words members see when something fails (never the server's address): see errors.js.
export { errorMessage, fieldErrors, isConnectionMessage, OFFLINE_MESSAGE, SLOW_MESSAGE } from './errors';

/**
 * Media URLs come from the server's APP_URL. While testing on a phone that is often
 * "localhost", which the phone cannot reach, so point those at the API host instead.
 */
export function mediaUrl(url) {
  if (!url || !SITE_URL) return url || null;
  return url.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, SITE_URL);
}

export default api;
