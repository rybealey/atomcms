import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { get } from './api';
import { Btn, Icon, Note } from '@hk/ui';

export interface Rank { id: number; name: string; badge: string; members: number }
export interface Counts {
  draw_badges: number;
  support_waiting: number;
  tickets_open: number;
  calls_open: number;
  guard_flags: number;
  jailed: number;
}
export interface Pulse {
  counts: Counts;
  emulator: { online: boolean };
}
export interface Me {
  user: { id: number; username: string; look: string; motto: string; rank: number; rank_name: string; last_online: number };
  /** website_housekeeping_permissions: which screens and edits this rank reaches */
  permissions: Record<string, boolean>;
  /** permissions_commands: the in-game abilities the same rank holds, mirrored by web actions */
  abilities: Record<string, boolean>;
  ranks: Rank[];
  config: { hotel_name: string; imager: string; badges_path: string; emulator: string; features: string[]; min_staff_rank: number; environment: 'production' | 'beta' | 'local' };
  pulse: Pulse;
}

export interface ConfirmSpec { title: string; body: string; cta: string; danger?: boolean }
type ToastTone = '' | 'ok' | 'bad' | 'warn' | 'info';
interface ToastSpec { id: number; tone: ToastTone; title: string; body?: string }

type Theme = 'light' | 'dark';
type Density = 'comfortable' | 'compact';

interface SessionValue {
  me: Me;
  pulse: Pulse;
  can: (permission: string) => boolean;
  /** an in-game ability (permissions_commands row) the signed-in rank holds */
  able: (ability: string) => boolean;
  refreshMe: () => void;
  refreshPulse: () => void;
  theme: Theme;
  toggleTheme: () => void;
  density: Density;
  toggleDensity: () => void;
  collapsed: boolean;
  toggleCollapsed: () => void;
  toast: (tone: ToastTone, title: string, body?: string) => void;
  confirm: (spec: ConfirmSpec) => Promise<boolean>;
  rankName: (rank: number) => string;
}

const SessionContext = createContext<SessionValue | null>(null);

export function useSession(): SessionValue {
  const value = useContext(SessionContext);
  if (!value) throw new Error('useSession outside SessionProvider');
  return value;
}

function stored<T extends string>(key: string, allowed: readonly T[], fallback: T): T {
  try {
    const v = localStorage.getItem(key);
    if (v && (allowed as readonly string[]).includes(v)) return v as T;
  } catch {
    /* private mode: the default is fine */
  }
  return fallback;
}
function store(key: string, value: string): void {
  try {
    localStorage.setItem(key, value);
  } catch {
    /* ignore */
  }
}

function initialTheme(): Theme {
  const prefers: Theme = window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  return stored<Theme>('hk-theme', ['light', 'dark'], prefers);
}

export function SessionProvider({ children }: { children: React.ReactNode }) {
  const [me, setMe] = useState<Me | null>(null);
  const [pulse, setPulse] = useState<Pulse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [theme, setTheme] = useState<Theme>(initialTheme);
  const [density, setDensity] = useState<Density>(() => stored<Density>('hk-density', ['comfortable', 'compact'], 'comfortable'));
  const [collapsed, setCollapsed] = useState<boolean>(() => stored('hk-rail', ['open', 'collapsed'], 'open') === 'collapsed');
  const [toasts, setToasts] = useState<ToastSpec[]>([]);
  const [confirmState, setConfirm] = useState<(ConfirmSpec & { resolve: (ok: boolean) => void }) | null>(null);
  const toastId = useRef(0);

  const load = useCallback(() => {
    get<Me>('/me')
      .then((m) => {
        setMe(m);
        setPulse(m.pulse);
      })
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load housekeeping'));
  }, []);

  const refreshPulse = useCallback(() => {
    get<Pulse>('/me/pulse')
      .then(setPulse)
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  // The nav counts and the emulator dot stay current while the tab is open.
  useEffect(() => {
    if (!me) return;
    const t = window.setInterval(() => {
      if (document.visibilityState === 'visible') refreshPulse();
    }, 30000);
    return () => window.clearInterval(t);
  }, [me, refreshPulse]);

  useEffect(() => {
    document.body.classList.toggle('dark', theme === 'dark');
  }, [theme]);

  const toast = useCallback((tone: ToastTone, title: string, body?: string) => {
    const id = ++toastId.current;
    setToasts((list) => [...list.slice(-2), { id, tone, title, body }]);
    window.setTimeout(() => setToasts((list) => list.filter((t) => t.id !== id)), 4200);
  }, []);

  const confirm = useCallback((spec: ConfirmSpec) => new Promise<boolean>((resolve) => setConfirm({ ...spec, resolve })), []);

  const toggleTheme = useCallback(() => {
    setTheme((t) => {
      const next = t === 'dark' ? 'light' : 'dark';
      store('hk-theme', next);
      return next;
    });
  }, []);
  const toggleDensity = useCallback(() => {
    setDensity((d) => {
      const next = d === 'compact' ? 'comfortable' : 'compact';
      store('hk-density', next);
      return next;
    });
  }, []);
  const toggleCollapsed = useCallback(() => {
    setCollapsed((c) => {
      store('hk-rail', c ? 'open' : 'collapsed');
      return !c;
    });
  }, []);

  const value = useMemo<SessionValue | null>(() => {
    if (!me || !pulse) return null;
    return {
      me,
      pulse,
      can: (permission: string) => me.permissions[permission] === true,
      able: (ability: string) => me.abilities[ability] === true,
      refreshMe: load,
      refreshPulse,
      theme,
      toggleTheme,
      density,
      toggleDensity,
      collapsed,
      toggleCollapsed,
      toast,
      confirm,
      rankName: (rank: number) => me.ranks.find((r) => r.id === rank)?.name ?? `Rank ${rank}`,
    };
  }, [me, pulse, theme, toggleTheme, density, toggleDensity, collapsed, toggleCollapsed, toast, confirm, load, refreshPulse]);

  const rootClass = `hk ${theme === 'dark' ? 'dark' : ''} ${density === 'compact' ? 'compact' : ''} ${me?.config.environment === 'beta' ? 'beta' : ''}`;

  if (error) {
    return (
      <div className={rootClass} style={{ display: 'grid', placeItems: 'center', padding: 24 }}>
        <div className="card" style={{ maxWidth: 420, width: '100%' }}>
          <div className="card-b stack16">
            <Note tone="bad" icon="warning" title="Housekeeping is unavailable">
              {error}
            </Note>
            <div className="actions">
              <Btn variant="primary" onClick={() => window.location.reload()}>
                Try again
              </Btn>
              <Btn href="/">Back to the site</Btn>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!value) {
    return (
      <div className={rootClass} style={{ display: 'grid', placeItems: 'center' }} role="status">
        <img src="/assets/images/pixelrp/emblem.png" alt="Loading housekeeping" width={31} height={55} className="px" />
      </div>
    );
  }

  return (
    <SessionContext.Provider value={value}>
      <div className={rootClass}>
        {children}
        {toasts.length > 0 ? (
          <div className="toasts" role="status" aria-live="polite">
            {toasts.map((t) => (
              <div key={t.id} className="toast">
                <span className={`dot ${t.tone}`} style={{ marginTop: 6 }} />
                <div className="grow">
                  <p>{t.title}</p>
                  {t.body ? <p className="sub">{t.body}</p> : null}
                </div>
                <button type="button" className="btn quiet sm icon" aria-label="Dismiss" onClick={() => setToasts((list) => list.filter((x) => x.id !== t.id))}>
                  <Icon name="close" />
                </button>
              </div>
            ))}
          </div>
        ) : null}
        {confirmState ? (
          <ConfirmDialog
            spec={confirmState}
            onDone={(ok) => {
              confirmState.resolve(ok);
              setConfirm(null);
            }}
          />
        ) : null}
      </div>
    </SessionContext.Provider>
  );
}

function ConfirmDialog({ spec, onDone }: { spec: ConfirmSpec; onDone: (ok: boolean) => void }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onDone(false);
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onDone]);
  return (
    <>
      <div className="scrim" onClick={() => onDone(false)} />
      <div className="dialog-wrap" onClick={() => onDone(false)}>
        <div className="dialog" role="alertdialog" aria-modal="true" aria-labelledby="hk-confirm-title" onClick={(e) => e.stopPropagation()}>
          <div className="card-h">
            <h3 id="hk-confirm-title">{spec.title}</h3>
          </div>
          <div className="card-b">
            <p className="sub" style={{ fontSize: 14, color: 'var(--ink)' }}>
              {spec.body}
            </p>
          </div>
          <div className="card-f">
            <Btn onClick={() => onDone(false)}>Cancel</Btn>
            <Btn variant={spec.danger ? 'danger-primary' : 'primary'} onClick={() => onDone(true)} autoFocus>
              {spec.cta}
            </Btn>
          </div>
        </div>
      </div>
    </>
  );
}
