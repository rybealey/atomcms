// Display formats. The API sends every time as epoch seconds (emulator ints
// and CMS timestamps alike); these turn them into the canvas's US formats.

export function fmtNumber(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return value.toLocaleString('en-US');
}

/** 4,210,000 → "4.21M"; small numbers stay whole. */
export function fmtCompact(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  const abs = Math.abs(value);
  if (abs >= 1e9) return `${+(value / 1e9).toFixed(2)}B`;
  if (abs >= 1e6) return `${+(value / 1e6).toFixed(2)}M`;
  if (abs >= 1e4) return `${+(value / 1e3).toFixed(1)}k`;
  return fmtNumber(value);
}

function toDate(ts: number | string | null | undefined): Date | null {
  if (ts === null || ts === undefined) return null;
  const n = typeof ts === 'string' ? Number(ts) : ts;
  if (!Number.isFinite(n) || n <= 0) return null;
  return new Date(n * 1000);
}

function sameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

/** "Oct 20, 2026" */
export function fmtDate(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  return d ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

/** "Oct 20", with the year only when it isn't this one. */
export function fmtDay(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  if (!d) return '—';
  return d.toLocaleDateString('en-US', d.getFullYear() === new Date().getFullYear() ? { month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric', year: 'numeric' });
}

/** "10:42 PM" */
export function fmtClock(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  return d ? d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '—';
}

/** "Oct 20 · 8:15 PM" */
export function fmtDateTime(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  return d ? `${fmtDay(ts)} · ${fmtClock(ts)}` : '—';
}

/** Today's rows show the clock, older ones the day: the timeline's left column. */
export function fmtWhen(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  if (!d) return '—';
  return sameDay(d, new Date()) ? fmtClock(ts) : fmtDay(ts);
}

/** "Today", "Yesterday" or "Monday, October 19": the timeline's day heads. */
export function fmtDayHead(ts: number): string {
  const d = toDate(ts);
  if (!d) return '';
  const now = new Date();
  if (sameDay(d, now)) return 'Today';
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return 'Yesterday';
  return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: d.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
}

/** "18m ago", "3h ago", "yesterday", "3d ago" */
export function relTime(ts: number | string | null | undefined): string {
  const d = toDate(ts);
  if (!d) return '—';
  const diff = Math.round((Date.now() - d.getTime()) / 1000);
  if (diff < 0) return fmtDateTime(ts);
  if (diff < 45) return 'just now';
  if (diff < 3600) return `${Math.max(1, Math.round(diff / 60))}m ago`;
  if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
  if (diff < 172800) return 'yesterday';
  if (diff < 86400 * 14) return `${Math.round(diff / 86400)}d ago`;
  return fmtDay(ts);
}

/** Seconds → "3h 20m" (shift time, online time, sentences). */
export function fmtDuration(seconds: number | null | undefined): string {
  const s = Math.max(0, Math.round(seconds ?? 0));
  if (s < 60) return `${s}s`;
  const d = Math.floor(s / 86400);
  const h = Math.floor((s % 86400) / 3600);
  const m = Math.floor((s % 3600) / 60);
  if (d > 0) return h ? `${d}d ${h}h` : `${d}d`;
  if (h === 0) return `${m}m`;
  return m ? `${h}h ${m}m` : `${h}h`;
}

/** Seconds → "1m 05s" for response times. */
export function fmtResponse(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return '—';
  const s = Math.max(0, Math.round(seconds));
  return `${Math.floor(s / 60)}m ${String(s % 60).padStart(2, '0')}s`;
}

export function plural(n: number, one: string, many = one + 's'): string {
  return `${fmtNumber(n)} ${n === 1 ? one : many}`;
}

export function fmtSecondsLong(seconds: number): { n: number; unit: 'days' | 'months' | 'years' } {
  const y = 31556926, mo = 2629743, d = 86400;
  if (seconds % y === 0 || Math.abs(seconds / y - Math.round(seconds / y)) < 0.01) return { n: Math.round(seconds / y), unit: 'years' };
  if (seconds >= mo && seconds % mo < d) return { n: Math.round(seconds / mo), unit: 'months' };
  return { n: Math.max(1, Math.round(seconds / d)), unit: 'days' };
}

export const UNIT_SECONDS = { days: 86400, months: 2629743, years: 31556926 } as const;
