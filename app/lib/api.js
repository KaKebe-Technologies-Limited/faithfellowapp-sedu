// Axios client for the FaithFellows Laravel API (/api/v1) plus small helpers:
// friendly error messages, the /api/ping health check, and media URL fixing for local testing.
import axios from 'axios';
import Constants from 'expo-constants';
import { clearToken, expireSession, getToken } from './session';

const ENV = Constants.expoConfig?.extra?.apiUrl || {};
const appEnv = process.env.EXPO_PUBLIC_APP_ENV || 'development';

// .env wins (EXPO_PUBLIC_API_URL); otherwise the per-environment URL from app.json.
export const API_ROOT = (process.env.EXPO_PUBLIC_API_URL || ENV[appEnv] || ENV.development || '').replace(/\/+$/, '');
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

/** A human-readable message for any request error (validation, throttling, network…). */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
  const res = error?.response;
  if (!res) {
    if (error?.code === 'ECONNABORTED') return 'The server took too long to answer. Please try again.';
    if (error?.message === 'Network Error') return `Cannot reach the server at ${API_ROOT || 'the API'}. Check your Wi-Fi and the address in app/.env.`;
    return error?.message || fallback;
  }
  if (res.status === 422) {
    const first = Object.values(res.data?.errors || {})[0];
    return (Array.isArray(first) ? first[0] : first) || res.data?.message || fallback;
  }
  if (res.status === 429) return 'Too many attempts. Please wait a moment and try again.';
  if (res.status >= 500) return 'The server had a problem. Please try again shortly.';
  return res.data?.message || fallback;
}

/** Field-level validation errors from a 422: { email: 'The email is invalid.' }. */
export function fieldErrors(error) {
  if (error?.response?.status !== 422) return {};
  return Object.fromEntries(
    Object.entries(error.response.data?.errors || {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
  );
}

/**
 * Media URLs come from the server's APP_URL. While testing on a phone that is often
 * "localhost", which the phone cannot reach, so point those at the API host instead.
 */
export function mediaUrl(url) {
  if (!url || !SITE_URL) return url || null;
  return url.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i, SITE_URL);
}

export default api;
