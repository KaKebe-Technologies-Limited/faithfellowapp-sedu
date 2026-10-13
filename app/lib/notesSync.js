// Keeps the notes on the phone in step with the server, and gives the notebook screen its list.
// The list always comes from the phone (so it opens at once and works offline); a background refresh fetches every
// page of GET /api/v1/notes and merges it in without touching changes that are still waiting to be sent.
import { useCallback, useEffect, useRef, useState } from 'react';
import api, { errorMessage } from './api';
import { isOffline } from './net';
import { lastSynced, listNotes, mergeFromServer, useNotesVersion } from './notesStore';

const MAX_PAGES = 40;
let syncing = null;

/** Fetches all of the member's notes into the phone. Rejects when the server cannot be reached. */
export function syncNotes() {
  syncing ??= (async () => {
    const all = [];
    let page = 1;
    let last = 1;
    do {
      const { data } = await api.get('/notes', { params: { page } });
      all.push(...data.data);
      last = data.meta?.last_page ?? 1;
      page += 1;
    } while (page <= last && page <= MAX_PAGES);
    await mergeFromServer(all, page > last);
  })().finally(() => {
    syncing = null;
  });
  return syncing;
}

/** The notebook: `{ items, loading, refreshing, error, offline, reload }` for the given search and kind filter. */
export function useNotes({ q = '', kind = '' } = {}) {
  const version = useNotesVersion();
  const [items, setItems] = useState([]);
  const [state, setState] = useState({ loading: true, refreshing: false, error: null, offline: false });
  const alive = useRef(true);

  useEffect(() => {
    alive.current = true;
    return () => {
      alive.current = false;
    };
  }, []);

  useEffect(() => {
    let current = true;
    listNotes({ q, kind }).then((list) => current && setItems(list));
    return () => {
      current = false;
    };
  }, [version, q, kind]);

  const refresh = useCallback(async (mode) => {
    const never = !(await lastSynced());
    setState((s) => ({ ...s, loading: never && mode === 'load', refreshing: mode === 'refresh', error: null }));
    try {
      await syncNotes();
      alive.current && setState({ loading: false, refreshing: false, error: null, offline: false });
    } catch (e) {
      if (alive.current) setState({ loading: false, refreshing: false, error: never && !isOffline(e) ? errorMessage(e) : null, offline: isOffline(e) });
    }
  }, []);

  useEffect(() => {
    refresh('load');
  }, [refresh]);

  return { items, ...state, reload: () => refresh('refresh'), retry: () => refresh('load') };
}
