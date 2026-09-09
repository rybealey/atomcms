import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError, get } from './api';

export interface Fetched<T> {
  data: T | null;
  error: string | null;
  loading: boolean;
  reload: () => void;
  setData: (updater: (current: T | null) => T | null) => void;
}

/** Load a JSON endpoint; pass null to skip. Re-fetches when the path changes. */
export function useFetch<T>(path: string | null): Fetched<T> {
  const [data, setDataState] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState<boolean>(path !== null);
  const [tick, setTick] = useState(0);
  const latest = useRef(0);

  useEffect(() => {
    if (path === null) {
      setLoading(false);
      return;
    }
    const id = ++latest.current;
    setLoading(true);
    setError(null);
    get<T>(path)
      .then((result) => {
        if (latest.current === id) {
          setDataState(result);
          setLoading(false);
        }
      })
      .catch((err: unknown) => {
        if (latest.current !== id) return;
        setError(err instanceof ApiError ? err.message : 'Something went wrong');
        setLoading(false);
      });
  }, [path, tick]);

  const reload = useCallback(() => setTick((t) => t + 1), []);
  const setData = useCallback((updater: (current: T | null) => T | null) => setDataState((current) => updater(current)), []);

  return { data, error, loading, reload, setData };
}
