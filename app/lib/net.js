// What a failed request means: no connection at all, or the server answering "no".

/** True when a request failed for lack of a connection (no answer at all), not because the server said no. */
export const isOffline = (e) => !!e && !e.response && e.code !== 'ERR_CANCELED';

/** The server answered, and the answer will not change if we ask again (a rejected note, a missing record). */
export const isRejected = (e) => !!e?.response && e.response.status >= 400 && e.response.status < 500 && ![401, 408, 429].includes(e.response.status);
