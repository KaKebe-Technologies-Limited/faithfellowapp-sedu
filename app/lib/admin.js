// Small helpers for the Admin screens: build the API path for a church, and check what the signed-in staff member
// is allowed to do (the server enforces the same permissions on every request).
import api from './api';

/** `adminApi('grace-lira').get('/events')` → GET /admin/grace-lira/events */
export function adminApi(slug) {
  const base = `/admin/${slug}`;
  return {
    get: (path, config) => api.get(base + path, config),
    post: (path, body, config) => api.post(base + path, body, config),
    put: (path, body, config) => api.put(base + path, body, config),
    delete: (path, config) => api.delete(base + path, config),
    path: (path) => base + path,
  };
}

export const can = (permissions, permission) => !!permissions?.includes(permission);

/** "2026-10-21" + "18:00" → "2026-10-21 18:00" (read by the server as a local date and time). */
export const joinDateTime = (date, time) => `${String(date).trim()} ${String(time).trim() || '00:00'}`;

export const today = () => new Date().toISOString().slice(0, 10);
