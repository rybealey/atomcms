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

export interface Stream<T, R> {
  first: Fetched<T>;
  rows: R[];
  next: string | number | null;
  more: () => void;
  loadingMore: boolean;
}

/**
 * A newest-first list with "Load older": the first page from `path`, later
 * pages from the same path plus `cursorParam=<next>`, appended in order.
 */
export function useStream<T extends { next?: string | number | null }, R>(path: string | null, rowsOf: (data: T) => R[], cursorParam = 'cursor'): Stream<T, R> {
  const first = useFetch<T>(path);
  const [extra, setExtra] = useState<R[]>([]);
  const [next, setNext] = useState<string | number | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  const rowsRef = useRef(rowsOf);
  rowsRef.current = rowsOf;

  useEffect(() => {
    setExtra([]);
    setNext(first.data?.next ?? null);
  }, [first.data]);

  const more = useCallback(() => {
    if (!path || next === null || loadingMore) return;
    setLoadingMore(true);
    const sep = path.includes('?') ? '&' : '?';
    get<T>(`${path}${sep}${cursorParam}=${encodeURIComponent(String(next))}`)
      .then((d) => {
        setExtra((e) => [...e, ...rowsRef.current(d)]);
        setNext(d.next ?? null);
      })
      .finally(() => setLoadingMore(false));
  }, [path, next, loadingMore, cursorParam]);

  return { first, rows: first.data ? [...rowsOf(first.data), ...extra] : [], next, more, loadingMore };
}
