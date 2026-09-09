import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { get } from './api';
import { Button, Icon, Tone } from '@hk/ui';

export interface Rank { id: number; name: string; badge: string; members: number }
export interface Me {
  user: { id: number; username: string; look: string; motto: string; rank: number; rank_name: string; last_online: number };
  permissions: Record<string, boolean>;
  ranks: Rank[];
  config: { hotel_name: string; imager: string; badges_path: string; emulator: string; features: string[]; legacy_url: string; min_staff_rank: number };
  counts: { staff_applications: number; draw_badges: number };
}

export interface ConfirmSpec { title: string; body: string; impacts?: string[]; cta: string; tone?: 'danger' | 'brand' }
interface ToastSpec { tone: Tone; title: string; body?: string }

interface SessionValue {
  me: Me;
  can: (permission: string) => boolean;
  refreshMe: () => void;
  theme: 'light' | 'dark';
  toggleTheme: () => void;
  toast: (tone: Tone, title: string, body?: string) => void;
  confirm: (spec: ConfirmSpec) => Promise<boolean>;
  rankName: (rank: number) => string;
}

const SessionContext = createContext<SessionValue | null>(null);

export function useSession(): SessionValue {
  const value = useContext(SessionContext);
  if (!value) throw new Error('useSession outside SessionProvider');
  return value;
}

function initialTheme(): 'light' | 'dark' {
  try {
    const stored = localStorage.getItem('hk-theme');
    if (stored === 'light' || stored === 'dark') return stored;
  } catch {
    /* private mode */
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function SessionProvider({ children }: { children: React.ReactNode }) {
  const [me, setMe] = useState<Me | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [theme, setTheme] = useState<'light' | 'dark'>(initialTheme);
  const [toastState, setToast] = useState<ToastSpec | null>(null);
  const [confirmState, setConfirm] = useState<(ConfirmSpec & { resolve: (ok: boolean) => void }) | null>(null);
  const toastTimer = useRef<number | undefined>(undefined);

  const load = useCallback(() => {
    get<Me>('/me')
      .then(setMe)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load housekeeping'));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const toast = useCallback((tone: Tone, title: string, body?: string) => {
    window.clearTimeout(toastTimer.current);
    setToast({ tone, title, body });
    toastTimer.current = window.setTimeout(() => setToast(null), 3600);
  }, []);

  const confirm = useCallback((spec: ConfirmSpec) => new Promise<boolean>((resolve) => setConfirm({ ...spec, resolve })), []);

  const toggleTheme = useCallback(() => {
    setTheme((t) => {
      const next = t === 'dark' ? 'light' : 'dark';
      try {
        localStorage.setItem('hk-theme', next);
      } catch {
        /* ignore */
      }
      return next;
    });
  }, []);

  const value = useMemo<SessionValue | null>(() => {
    if (!me) return null;
    return {
      me,
      can: (permission: string) => me.permissions[permission] === true,
      refreshMe: load,
      theme,
      toggleTheme,
      toast,
      confirm,
      rankName: (rank: number) => me.ranks.find((r) => r.id === rank)?.name ?? `Rank ${rank}`,
    };
  }, [me, theme, toggleTheme, toast, confirm, load]);

  if (error) {
    return (
      <div className="hk" data-theme={theme} style={{ display: 'grid', placeItems: 'center', padding: 24 }}>
        <div className="hk-card hk-card--lg" style={{ padding: 24, maxWidth: 420 }}>
          <h1 className="hk-h1" style={{ marginBottom: 8 }}>
            Housekeeping is unavailable
          </h1>
          <p className="hk-lede">{error}</p>
          <div className="hk-actions" style={{ marginTop: 16 }}>
            <Button variant="secondary" size="sm" onClick={() => window.location.reload()}>
              Retry
            </Button>
            <Button variant="ghost" size="sm" href="/">
              Back to the site
            </Button>
          </div>
        </div>
      </div>
    );
  }

  if (!value) {
    return (
      <div className="hk" data-theme={theme} style={{ display: 'grid', placeItems: 'center' }}>
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12 }}>
          <img src="/favicon-32x32.png" alt="" className="px-art" style={{ width: 48, height: 48, animation: 'hk-blink 1.2s steps(2) infinite' }} />
          <span className="hk-brand__sub">Loading housekeeping</span>
        </div>
      </div>
    );
  }

  return (
    <SessionContext.Provider value={value}>
      <div className="hk" data-theme={theme}>
        {children}
        {toastState ? (
          <div className="hk-toastwrap">
            <div role="status" className="hk-toast">
              <span className="hk-toast__bar" style={{ background: `var(--${toastState.tone === 'ink' || toastState.tone === 'orange' ? 'info' : toastState.tone})` }} />
              <div style={{ flex: 1 }}>
                <div className="hk-toast__title">{toastState.title}</div>
                {toastState.body ? <div className="hk-toast__body">{toastState.body}</div> : null}
              </div>
              <button type="button" aria-label="Dismiss" onClick={() => setToast(null)}>
                ✕
              </button>
            </div>
          </div>
        ) : null}
        {confirmState ? (
          <div
            role="presentation"
            className="hk-dialog-scrim"
            onClick={() => {
              confirmState.resolve(false);
              setConfirm(null);
            }}
          >
            <div role="alertdialog" aria-modal="true" aria-labelledby="hk-confirm-title" className="hk-dialog" onClick={(e) => e.stopPropagation()}>
              <div className={`hk-dialog__head ${confirmState.tone === 'brand' ? 'hk-dialog__head--brand' : ''}`}>
                <Icon name="alert" size={18} />
                <span id="hk-confirm-title">{confirmState.title}</span>
              </div>
              <div className="hk-dialog__body">
                <p>{confirmState.body}</p>
                {confirmState.impacts && confirmState.impacts.length > 0 ? (
                  <ul>
                    {confirmState.impacts.map((i) => (
                      <li key={i}>{i}</li>
                    ))}
                  </ul>
                ) : null}
              </div>
              <div className="hk-dialog__foot">
                <Button
                  variant="ghost"
                  onClick={() => {
                    confirmState.resolve(false);
                    setConfirm(null);
                  }}
                >
                  Keep It As Is
                </Button>
                <Button
                  variant={confirmState.tone === 'brand' ? 'primary' : 'danger'}
                  onClick={() => {
                    confirmState.resolve(true);
                    setConfirm(null);
                  }}
                >
                  {confirmState.cta}
                </Button>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </SessionContext.Provider>
  );
}
