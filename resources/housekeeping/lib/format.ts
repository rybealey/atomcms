const SMALL = new Set(['a', 'an', 'the', 'to', 'on', 'of', 'per', 'for', 'and', 'in', 'at', 'by', 'with', 'from', 'or', 'as', 'vs']);

/** Title Case for labels, leaving small words lower-case after the first. */
export function titleCase(text: string): string {
  return String(text).replace(/(^|[\s(&/-])([a-z])([\w.']*)/g, (match, pre: string, first: string, rest: string, offset: number) =>
    offset > 0 && SMALL.has(first + rest) ? match : pre + first.toUpperCase() + rest,
  );
}

export function fmtNumber(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return value.toLocaleString('en-US');
}

function toDate(ts: number | string | null | undefined): Date | null {
  if (ts === null || ts === undefined) return null;
  const n = typeof ts === 'string' ? Number(ts) : ts;
  if (!Number.isFinite(n) || n <= 0) return null;
  return new Date(n * 1000);
}

export function fmtDate(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  return d ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

export function fmtDateTime(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  return d ? d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
}

/** "18 min ago", "yesterday", "3 d ago" — the compact relative style of the design. */
export function relTime(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  if (!d) return '—';
  const diff = Math.round((Date.now() - d.getTime()) / 1000);
  if (diff < 45) return 'just now';
  if (diff < 3600) return `${Math.round(diff / 60)} min ago`;
  if (diff < 86400) return `${Math.round(diff / 3600)} h ago`;
  if (diff < 172800) return 'yesterday';
  if (diff < 86400 * 14) return `${Math.round(diff / 86400)} d ago`;
  return fmtDate(ts);
}

/** Seconds → "3h 20m" for shift bookkeeping. */
export function fmtDuration(seconds: number): string {
  if (!seconds) return '0m';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (h === 0) return `${m}m`;
  return `${h}h ${m}m`;
}

export function fmtSecondsLong(seconds: number): { n: number; unit: 'days' | 'months' | 'years' } {
  const y = 31556926, mo = 2629743, d = 86400;
  if (seconds % y === 0 || Math.abs(seconds / y - Math.round(seconds / y)) < 0.01) return { n: Math.round(seconds / y), unit: 'years' };
  if (seconds >= mo && seconds % mo < d) return { n: Math.round(seconds / mo), unit: 'months' };
  return { n: Math.max(1, Math.round(seconds / d)), unit: 'days' };
}

export const UNIT_SECONDS = { days: 86400, months: 2629743, years: 31556926 } as const;

export function greeting(): string {
  const h = new Date().getHours();
  if (h < 5) return 'Good night';
  if (h < 12) return 'Good morning';
  if (h < 18) return 'Good afternoon';
  return 'Good evening';
}

export function plural(n: number, one: string, many = one + 's'): string {
  return n === 1 ? one : many;
}
