// What a failed request says to the member. Nothing here ever shows the server's address, a file path or a developer's error text: when there
// is no connection the message is "check your internet connection and try again", and anything technical the server sends back is replaced by a
// plain sentence. (Kept free of React Native imports so it can be tested on its own.)

export const OFFLINE_MESSAGE = 'Please check your internet connection and try again.';
export const SLOW_MESSAGE = 'The connection is slow or has dropped. Please check your internet connection and try again.';

/** True for the two messages above, so a screen can say "No internet connection" instead of "Something went wrong". */
export const isConnectionMessage = (message) => message === OFFLINE_MESSAGE || message === SLOW_MESSAGE;

// Words that only a developer should ever see: addresses, class names, SQL, file names, framework errors.
const TECHNICAL = /(https?:\/\/|\bwww\.|\bApp\\|SQLSTATE|\.php\b|\/vendor\/|\bexception\b|stack trace|\bartisan\b|\broute\b.*\bnot (be )?found|No query results|Call to |Undefined |Class "|Target class|Symfony|localhost|\b\d{1,3}(\.\d{1,3}){3}\b)/i;

/** The server's own words when they are fit for a member to read, otherwise null. */
function safeServerMessage(message) {
  const ok = typeof message === 'string' && message.trim() !== '' && message.length <= 300 && !TECHNICAL.test(message);

  // The framework's one-word answers ("Not Found", "Server Error") say nothing to a member: use our own sentence instead.
  return ok && !/^(not found|server error|forbidden|unauthori[sz]ed|unauthenticated\.?)$/i.test(message.trim()) ? message : null;
}

/** A human-readable message for any request error (validation, throttling, no connection…). */
export function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
  const res = error?.response;
  if (!res) {
    // No answer at all: no connection, a dropped connection, or a server that did not reply in time. Never print the error's own text.
    if (error?.code === 'ECONNABORTED' || error?.code === 'ETIMEDOUT' || /timeout/i.test(String(error?.message ?? ''))) return SLOW_MESSAGE;
    if (error?.code === 'ERR_NETWORK' || error?.message === 'Network Error' || error?.request) return OFFLINE_MESSAGE;
    return fallback;
  }
  if (res.status === 422) {
    const first = Object.values(res.data?.errors || {})[0];
    return safeServerMessage(Array.isArray(first) ? first[0] : first) || safeServerMessage(res.data?.message) || fallback;
  }
  if (res.status === 429) return 'Too many attempts. Please wait a moment and try again.';
  if (res.status >= 500) return 'The server had a problem. Please try again shortly.';
  if (res.status === 401) return safeServerMessage(res.data?.message) || 'Please sign in again.';
  if (res.status === 404) return safeServerMessage(res.data?.message) || 'We could not find that. It may have been removed.';
  if (res.status === 403) return safeServerMessage(res.data?.message) || 'You do not have access to this.';

  return safeServerMessage(res.data?.message) || fallback;
}

/** Field-level validation errors from a 422: { email: 'The email is invalid.' }. */
export function fieldErrors(error) {
  if (error?.response?.status !== 422) return {};
  return Object.fromEntries(
    Object.entries(error.response.data?.errors || {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]),
  );
}
