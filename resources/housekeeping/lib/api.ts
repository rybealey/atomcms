// Thin fetch wrapper for the session-authenticated JSON API under
// /housekeeping/api. Same-origin cookies carry the Laravel session; the CSRF
// token comes from the Blade shell's meta tag and rides on every write.

export class ApiError extends Error {
  status: number;
  body: unknown;

  constructor(status: number, message: string, body: unknown) {
    super(message);
    this.status = status;
    this.body = body;
  }
}

const BASE = '/housekeeping/api';

function csrfToken(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.content ?? '';
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase();
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('X-Requested-With', 'XMLHttpRequest');
  if (method !== 'GET') {
    headers.set('Content-Type', 'application/json');
    headers.set('X-CSRF-TOKEN', csrfToken());
  }

  const response = await fetch(BASE + path, { ...init, method, headers, credentials: 'same-origin' });

  if (response.status === 401) {
    window.location.href = '/login';
    throw new ApiError(401, 'Signed out', null);
  }
  if (response.status === 419) {
    window.location.reload();
    throw new ApiError(419, 'Session expired', null);
  }

  const text = await response.text();
  let body: unknown = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = text;
  }

  if (!response.ok) {
    const message =
      typeof body === 'object' && body !== null && 'message' in body && typeof (body as { message: unknown }).message === 'string'
        ? (body as { message: string }).message
        : `Request failed (${response.status})`;
    throw new ApiError(response.status, message, body);
  }

  return body as T;
}

export const get = <T>(path: string) => api<T>(path);
export const put = <T>(path: string, data: unknown) => api<T>(path, { method: 'PUT', body: JSON.stringify(data) });
export const post = <T>(path: string, data: unknown) => api<T>(path, { method: 'POST', body: JSON.stringify(data) });
