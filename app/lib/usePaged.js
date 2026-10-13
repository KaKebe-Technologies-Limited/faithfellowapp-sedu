// Paged list hook for Laravel resource collections ({ data: [...], meta: { current_page, last_page } }):
// loads page 1, appends the next page on loadMore(), and supports pull-to-refresh. Same states as useFetch
// (loading / refreshing / error) plus `loadingMore`.
import { useCallback, useEffect, useRef, useState } from 'react';
import api, { errorMessage } from './api';

export function usePaged(path, params = {}, { enabled = true } = {}) {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState({ current_page: 0, last_page: 1 });
  const [state, setState] = useState({ loading: enabled, refreshing: false, loadingMore: false, error: null });
  const latest = useRef(0);
  const paramsKey = JSON.stringify(params);

  const fetchPage = useCallback(
    async (page, mode) => {
      if (!enabled) return;
      const id = ++latest.current;
      setState((s) => ({ ...s, loading: mode === 'load', refreshing: mode === 'refresh', loadingMore: mode === 'more', error: null }));
      try {
        const { data } = await api.get(path, { params: { ...JSON.parse(paramsKey), page } });
        if (id !== latest.current) return;
        setItems((current) => (page === 1 ? data.data : [...current, ...data.data]));
        setMeta(data.meta ?? { current_page: page, last_page: page });
        setState({ loading: false, refreshing: false, loadingMore: false, error: null });
      } catch (e) {
        if (id === latest.current) setState((s) => ({ ...s, loading: false, refreshing: false, loadingMore: false, error: errorMessage(e) }));
      }
    },
    [path, paramsKey, enabled],
  );

  useEffect(() => {
    fetchPage(1, 'load');
    return () => {
      latest.current++;
    };
  }, [fetchPage]);

  return {
    items,
    ...state,
    hasMore: meta.current_page < meta.last_page,
    reload: () => fetchPage(1, 'refresh'),
    retry: () => fetchPage(1, 'load'),
    loadMore: () => {
      if (!state.loading && !state.loadingMore && !state.refreshing && meta.current_page < meta.last_page) fetchPage(meta.current_page + 1, 'more');
    },
    setItems,
  };
}
