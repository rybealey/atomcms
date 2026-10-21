// The housekeeping component kit. Each component renders the class names of
// the Housekeeping Redesign canvas (styles.css), so pages compose them the way
// the boards are laid out.
import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from 'react-router-dom';
import { Icon, IconName } from './icons';
import { headUrl, badgeUrl } from '@hk/lib/avatar';
import { useSession } from '@hk/lib/session';
import { get } from '@hk/lib/api';
import { fmtNumber, relTime } from '@hk/lib/format';

export { Icon } from './icons';
export type { IconName } from './icons';

export type Tone = '' | 'ok' | 'warn' | 'bad' | 'info' | 'brand' | 'acc';
export type Domain = 'police' | 'medical' | 'corp' | 'gang' | 'staff';
export type WritePath = 'live' | 'saved' | 'game';

/* ---------- Write-path badge ---------- */
const WP_LABEL: Record<WritePath, string> = { live: 'Live', saved: 'Saved', game: 'In-game' };
const WP_ICON: Record<WritePath, IconName> = { live: 'zap', saved: 'save', game: 'game' };
const WP_TITLE: Record<WritePath, string> = {
  live: 'Sent through RCON. Takes effect in the hotel at once.',
  saved: 'Written to the database. Read on next use, or reloaded for you automatically.',
  game: 'Held in emulator memory. Change it in the City Panel until an RCON command exists.',
};
export function Wp({ path, label, icon }: { path: WritePath; label?: string; icon?: boolean }) {
  return (
    <span className={`wp ${path}`} title={WP_TITLE[path]}>
      {icon ? <Icon name={WP_ICON[path]} /> : null}
      {label ?? WP_LABEL[path]}
    </span>
  );
}

/* ---------- Buttons ---------- */
type BtnVariant = 'default' | 'primary' | 'quiet' | 'danger' | 'danger-primary';
interface BtnProps extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'type'> {
  variant?: BtnVariant;
  sm?: boolean;
  icon?: IconName;
  iconOnly?: boolean;
  to?: string;
  href?: string;
  wp?: WritePath;
  wpLabel?: string;
  type?: 'button' | 'submit';
}
export function Btn({ variant = 'default', sm, icon, iconOnly, to, href, wp, wpLabel, className = '', children, type = 'button', ...rest }: BtnProps) {
  const v = variant === 'danger-primary' ? 'danger primary' : variant === 'default' ? '' : variant;
  const cls = `btn ${v} ${sm ? 'sm' : ''} ${iconOnly ? 'icon' : ''} ${className}`.replace(/\s+/g, ' ').trim();
  const inner = (
    <>
      {icon ? <Icon name={icon} /> : null}
      {iconOnly ? null : children}
      {wp ? <Wp path={wp} label={wpLabel} /> : null}
    </>
  );
  if (to) return <Link to={to} className={cls} aria-label={iconOnly ? String(rest['aria-label'] ?? '') : undefined}>{inner}</Link>;
  if (href) return <a href={href} className={cls}>{inner}</a>;
  return (
    <button type={type} className={cls} {...rest}>
      {inner}
    </button>
  );
}

/* ---------- Small marks ---------- */
export function Pill({ tone = '', children, title }: { tone?: Tone; children: React.ReactNode; title?: string }) {
  return <span className={`pill ${tone}`} title={title}>{children}</span>;
}
export function Dot({ tone }: { tone?: Tone | Domain | '' }) {
  return <span className={`dot ${tone ?? ''}`} />;
}
export function Tag({ domain, children, to }: { domain?: Domain; children: React.ReactNode; to?: string }) {
  const body = (
    <>
      {domain ? <span className={`dot ${domain}`} /> : null}
      {children}
    </>
  );
  return to ? <Link className="tag" to={to}>{body}</Link> : <span className="tag">{body}</span>;
}
export function Count({ n, tone }: { n: number; tone?: 'bad' }) {
  if (!n) return null;
  return <span className={`count ${tone ?? ''}`}>{n > 99 ? '99+' : n}</span>;
}
export function Kind({ icon, tone = '' }: { icon: IconName; tone?: Tone }) {
  return (
    <span className={`kind ${tone}`}>
      <Icon name={icon} />
    </span>
  );
}
export function Bar({ pct, tone, width = 96 }: { pct: number; tone?: 'ok' | 'warn' | 'bad'; width?: number | string }) {
  return (
    <div className={`bar ${tone ?? ''}`} style={{ width }}>
      <i style={{ width: `${Math.max(0, Math.min(100, pct))}%` }} />
    </div>
  );
}
export function Coin({ type = 'cash', alt = '' }: { type?: 'cash' | 'credits' | 'diamonds' | 'duckets'; alt?: string }) {
  return <img className="coin" src={`/assets/images/icons/currency/${type}.png`} alt={alt} width={15} height={15} />;
}
export function Money({ value, signed, coin = true }: { value: number | null | undefined; signed?: boolean; coin?: boolean }) {
  const n = value ?? 0;
  const cls = signed ? (n > 0 ? 'pos' : n < 0 ? 'neg' : '') : '';
  return (
    <span className={`num ${cls}`} style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
      {signed && n > 0 ? '+' : ''}
      {value === null || value === undefined ? '—' : fmtNumber(n)}
      {coin ? <Coin /> : null}
    </span>
  );
}

/* ---------- Game art ---------- */
const AV_SIZE = { 20: '', 30: 's30', 40: 's40', 80: 's80' } as const;
export function Avatar({ look, size = 20, title }: { look?: string | null; size?: 20 | 30 | 40 | 80; title?: string }) {
  const { me } = useSession();
  // The imager draws heads at whole-number scales only (size m is 1x, l is
  // 2x); the head is centred in the tile and the frame crops the rest.
  const big = size === 80;
  return (
    <span className={`av ${AV_SIZE[size]}`} title={title}>
      {look ? (
        <img
          src={headUrl(me.config.imager, look, big ? 'l' : size === 20 ? 's' : 'm')}
          alt=""
          loading="lazy"
          style={{ transform: `translate(-50%, ${size === 20 ? '-52%' : '-50%'})` }}
        />
      ) : null}
    </span>
  );
}
export function BadgeImg({ code, size = 40, title }: { code: string; size?: number; title?: string }) {
  const { me } = useSession();
  if (!code) return null;
  // Missing art (a code with no image yet) hides rather than showing broken alt text.
  return <img className="badge-img" src={badgeUrl(me.config.badges_path, code)} alt={title ?? code} title={title ?? code} width={size} height={size} style={{ objectFit: 'none' }} loading="lazy" onError={(e) => { e.currentTarget.style.visibility = 'hidden'; }} />;
}

/* ---------- Entity chips ---------- */
export interface PlayerRef { id: number; username: string; look?: string | null }
interface PlayerCard { id: number; username: string; look: string; rank_name: string; online: boolean; room: string | null; job: string | null; flags: string[] }
const cardCache = new Map<number, Promise<PlayerCard>>();

function HoverCard({ anchor, id }: { anchor: DOMRect; id: number }) {
  const [card, setCard] = useState<PlayerCard | null>(null);
  useEffect(() => {
    let live = true;
    if (!cardCache.has(id)) cardCache.set(id, get<PlayerCard>(`/players/${id}/card`));
    cardCache.get(id)!.then((c) => live && setCard(c)).catch(() => cardCache.delete(id));
    return () => {
      live = false;
    };
  }, [id]);
  const top = anchor.bottom + 8 + 90 > window.innerHeight ? anchor.top - 8 - 76 : anchor.bottom + 8;
  const left = Math.min(anchor.left, window.innerWidth - 316);
  return createPortal(
    <div className="hk-portal">
      <div className="peek hovercard" style={{ top, left }} role="tooltip">
        <Avatar look={card?.look} size={40} />
        <div className="grow">
          <p style={{ fontWeight: 500 }}>{card?.username ?? '…'}</p>
          <p className="sub ell">
            {card ? [card.job ?? card.rank_name, card.online ? (card.room ? `online in ${card.room}` : 'online') : 'offline'].join(' · ') : 'Loading'}
          </p>
        </div>
        {card?.flags.slice(0, 1).map((f) => (
          <Pill key={f} tone={f === 'Banned' ? 'bad' : f === 'Jailed' ? 'warn' : f === 'VIP' ? 'acc' : 'info'}>
            {f}
          </Pill>
        ))}
      </div>
    </div>,
    document.querySelector('.hk') ?? document.body,
  );
}

export function PlayerChip({ player, noCard }: { player: PlayerRef | null | undefined; noCard?: boolean }) {
  const [anchor, setAnchor] = useState<DOMRect | null>(null);
  const timer = useRef<number>();
  if (!player || !player.id) return <span className="chip ghost">{player?.username || 'Unknown'}</span>;
  const enter = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (noCard) return;
    const rect = e.currentTarget.getBoundingClientRect();
    window.clearTimeout(timer.current);
    timer.current = window.setTimeout(() => setAnchor(rect), 350);
  };
  const leave = () => {
    window.clearTimeout(timer.current);
    setAnchor(null);
  };
  return (
    <>
      <Link className="chip" to={`/players/${player.id}`} onMouseEnter={enter} onMouseLeave={leave} onFocus={leave} onClick={(e) => e.stopPropagation()}>
        <Avatar look={player.look} />
        <span>{player.username}</span>
      </Link>
      {anchor ? <HoverCard anchor={anchor} id={player.id} /> : null}
    </>
  );
}
export function RoomChip({ id, name, domain }: { id?: number | null; name: string | null | undefined; domain?: Domain }) {
  if (!name && !id) return <span className="sub">—</span>;
  const body = (
    <>
      {domain ? <span className={`dot ${domain}`} /> : <Icon name="pin" />}
      <span>{name || `Room ${id}`}</span>
    </>
  );
  return id ? (
    <Link className="chip room" to={`/city/rooms?room=${id}`} onClick={(e) => e.stopPropagation()}>
      {body}
    </Link>
  ) : (
    <span className="chip room">{body}</span>
  );
}
export function CorpChip({ id, name, police }: { id: number; name: string; police?: boolean }) {
  return (
    <Link className="chip room" to={`/city/corporations/${id}`} onClick={(e) => e.stopPropagation()}>
      <span className={`dot ${police ? 'police' : 'corp'}`} />
      <span>{name}</span>
    </Link>
  );
}
export function GangChip({ id, name }: { id: number; name: string }) {
  return (
    <Link className="chip room" to={`/city/gangs/${id}`} onClick={(e) => e.stopPropagation()}>
      <span className="dot gang" />
      <span>{name}</span>
    </Link>
  );
}

/* ---------- Layout ---------- */
export function PageHead({ title, sub, actions }: { title: React.ReactNode; sub?: React.ReactNode; actions?: React.ReactNode }) {
  return (
    <div className="ph">
      <div style={{ minWidth: 0 }}>
        <h1>{title}</h1>
        {sub ? <p className="sub">{sub}</p> : null}
      </div>
      {actions ? <div className="actions">{actions}</div> : null}
    </div>
  );
}
export function Card({ title, sub, action, children, className = '', style, bodyless, id }: { title?: React.ReactNode; sub?: React.ReactNode; action?: React.ReactNode; children?: React.ReactNode; className?: string; style?: React.CSSProperties; bodyless?: boolean; id?: string }) {
  return (
    <section className={`card ${className}`} style={style} id={id}>
      {title || action ? (
        <div className="card-h">
          <div style={{ minWidth: 0 }}>
            {title ? <h3>{title}</h3> : null}
            {sub ? <p className="sub">{sub}</p> : null}
          </div>
          {action ? <div className="actions">{action}</div> : null}
        </div>
      ) : null}
      {bodyless ? children : children}
    </section>
  );
}
export function Stat({ label, value, word, sub, dot, spark, tone, className = '' }: { label: React.ReactNode; value?: React.ReactNode; word?: React.ReactNode; sub?: React.ReactNode; dot?: Tone | Domain; spark?: number[]; tone?: 'pos' | 'neg'; className?: string }) {
  return (
    <div className={`stat ${className}`}>
      <span className="l">
        {dot ? <span className={`dot ${dot}`} /> : null}
        {label}
      </span>
      {value !== undefined ? <span className={`v ${tone ?? ''}`}>{value}</span> : null}
      {word !== undefined ? <span className="w">{word}</span> : null}
      {spark && spark.length > 1 ? <Spark values={spark} width={120} height={24} /> : null}
      {sub ? <span className="sub">{sub}</span> : null}
    </div>
  );
}
export function Spark({ values, width = 120, height = 24, stroke = 1.75, color }: { values: number[]; width?: number | string; height?: number; stroke?: number; color?: string }) {
  if (values.length < 2) return null;
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  const span = max - min || 1;
  const w = 120;
  const pts = values.map((v, i) => `${((i / (values.length - 1)) * w).toFixed(1)},${(height - 3 - ((v - min) / span) * (height - 6)).toFixed(1)}`).join(' ');
  return (
    <svg className="spark" viewBox={`0 0 ${w} ${height}`} width={width} height={height} preserveAspectRatio="none" aria-hidden="true" style={color ? { color } : undefined}>
      <polyline points={pts} fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}
export function Kv({ rows }: { rows: [React.ReactNode, React.ReactNode][] }) {
  return (
    <dl className="kv">
      {rows.map(([k, v], i) => (
        <React.Fragment key={i}>
          <dt>{k}</dt>
          <dd>{v}</dd>
        </React.Fragment>
      ))}
    </dl>
  );
}

/* ---------- Tabs and segments ---------- */
export interface TabItem { id: string; label: string; n?: number | null; to?: string }
export function Tabs({ tabs, active, onChange, label }: { tabs: TabItem[]; active: string; onChange?: (id: string) => void; label: string }) {
  return (
    <nav className="tabs" aria-label={label}>
      {tabs.map((t) =>
        t.to ? (
          <Link key={t.id} className={`tab ${t.id === active ? 'on' : ''}`} to={t.to} aria-current={t.id === active ? 'page' : undefined}>
            {t.label}
            {t.n ? <span className="n">{t.n}</span> : null}
          </Link>
        ) : (
          <button key={t.id} type="button" className={`tab ${t.id === active ? 'on' : ''}`} aria-pressed={t.id === active} onClick={() => onChange?.(t.id)}>
            {t.label}
            {t.n ? <span className="n">{t.n}</span> : null}
          </button>
        ),
      )}
    </nav>
  );
}
export function Seg<T extends string>({ options, value, onChange, label }: { options: { value: T; label: React.ReactNode }[]; value: T; onChange: (v: T) => void; label: string }) {
  return (
    <div className="seg" role="group" aria-label={label}>
      {options.map((o) => (
        <button key={o.value} type="button" className={o.value === value ? 'on' : ''} aria-pressed={o.value === value} onClick={() => onChange(o.value)}>
          {o.label}
        </button>
      ))}
    </div>
  );
}

/* ---------- Filters ---------- */
export function FilterSelect<T extends string>({ label, value, options, onChange, anyLabel = 'Any' }: { label: string; value: T | ''; options: { value: T; label: string }[]; onChange: (v: T | '') => void; anyLabel?: string }) {
  const current = options.find((o) => o.value === value);
  return (
    <label className={`select ${value ? 'on' : ''}`}>
      <span className="k">{label}</span> {current ? current.label : anyLabel}
      <Icon name="chevron-down" size={14} />
      <select value={value} onChange={(e) => onChange(e.target.value as T | '')} aria-label={label}>
        <option value="">{anyLabel}</option>
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );
}
export function SearchInput({ value, onChange, placeholder, width = 260, autoFocus }: { value: string; onChange: (v: string) => void; placeholder: string; width?: number | string; autoFocus?: boolean }) {
  return (
    <label className="input" style={{ width, maxWidth: '100%' }}>
      <Icon name="search" />
      <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} aria-label={placeholder} autoFocus={autoFocus} />
      {value ? (
        <button type="button" className="btn quiet sm icon" aria-label="Clear" onClick={() => onChange('')} style={{ marginRight: -6 }}>
          <Icon name="close" />
        </button>
      ) : null}
    </label>
  );
}
/** Debounce a fast-changing value (search boxes) before it reaches a fetch. */
export function useDebounced<T>(value: T, ms = 250): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = window.setTimeout(() => setV(value), ms);
    return () => window.clearTimeout(t);
  }, [value, ms]);
  return v;
}

/* ---------- Forms ---------- */
export function Field({ label, optional, hint, error, children, style }: { label: React.ReactNode; optional?: boolean | string; hint?: React.ReactNode; error?: string | null; children: React.ReactNode; style?: React.CSSProperties }) {
  return (
    <label className="field" style={style}>
      <span>
        {label} {optional ? <span className="opt">· {typeof optional === 'string' ? optional : 'optional'}</span> : null}
      </span>
      {children}
      {error ? <span className="err">{error}</span> : hint ? <span className="hint">{hint}</span> : null}
    </label>
  );
}
export function Switch({ checked, onChange, disabled, label }: { checked: boolean; onChange: (next: boolean) => void; disabled?: boolean; label: string }) {
  return <button type="button" role="switch" aria-checked={checked} aria-label={label} className="switch" disabled={disabled} onClick={() => onChange(!checked)} />;
}
export function CellCheck({ checked, onChange, disabled, label }: { checked: boolean; onChange?: (next: boolean) => void; disabled?: boolean; label: string }) {
  return (
    <button type="button" role="checkbox" aria-checked={checked} aria-label={label} className="cell-check" disabled={disabled || !onChange} onClick={() => onChange?.(!checked)}>
      {checked ? <Icon name="check" /> : null}
    </button>
  );
}

/* ---------- States ---------- */
export function Skeleton({ rows = 3 }: { rows?: number }) {
  const widths = ['70%', '90%', '50%', '80%', '60%'];
  return (
    <div className="skel" aria-label="Loading" role="status">
      {Array.from({ length: rows }, (_, i) => (
        <i key={i} style={{ width: widths[i % widths.length] }} />
      ))}
    </div>
  );
}
export function Empty({ title, body, bare, action }: { title: string; body?: React.ReactNode; bare?: boolean; action?: React.ReactNode }) {
  return (
    <div className={`empty ${bare ? 'bare' : ''}`}>
      <p>{title}</p>
      {body ? <p className="sub">{body}</p> : null}
      {action ? <div style={{ marginTop: 10 }}>{action}</div> : null}
    </div>
  );
}
export function Note({ tone = '', icon = 'info', title, children, action }: { tone?: '' | 'bad' | 'warn' | 'info' | 'ok'; icon?: IconName; title: React.ReactNode; children?: React.ReactNode; action?: React.ReactNode }) {
  return (
    <div className={`note ${tone}`}>
      <Icon name={icon} />
      <div className="grow">
        <p>{title}</p>
        {children ? <div style={{ fontSize: 13 }}>{children}</div> : null}
      </div>
      {action}
    </div>
  );
}
export function ErrorNote({ message, retry }: { message: string; retry?: () => void }) {
  return (
    <div style={{ padding: 16 }}>
      <Note tone="bad" icon="warning" title="Could not load this" action={retry ? <Btn sm onClick={retry}>Try again</Btn> : undefined}>
        {message}
      </Note>
    </div>
  );
}
/** Loading, error and empty in one place for a card body. */
export function Loadable<T>({ state, empty, children, rows }: { state: { data: T | null; error: string | null; loading: boolean; reload: () => void }; empty?: (data: T) => React.ReactNode | false; children: (data: T) => React.ReactNode; rows?: number }) {
  if (state.error && !state.data) return <ErrorNote message={state.error} retry={state.reload} />;
  if (!state.data) return <Skeleton rows={rows} />;
  const e = empty ? empty(state.data) : false;
  if (e) return <>{e}</>;
  return <>{children(state.data)}</>;
}
export function AsOf({ online }: { online: boolean }) {
  if (!online) return null;
  return (
    <Pill tone="warn" title="The emulator writes this when the player logs out, so the stored value may be behind.">
      As of last logout
    </Pill>
  );
}

/* ---------- Time ---------- */
/** Seconds → "12:40" under an hour, "2h 05m" under a day, "2d 3h" beyond. */
export function fmtLeft(seconds: number): string {
  const s = Math.max(0, Math.round(seconds));
  if (s < 3600) return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
  if (s < 86400) return `${Math.floor(s / 3600)}h ${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}m`;
  return `${Math.floor(s / 86400)}d ${Math.floor((s % 86400) / 3600)}h`;
}
export function useNow(intervalMs = 1000): number {
  const [now, setNow] = useState(() => Date.now() / 1000);
  useEffect(() => {
    const t = window.setInterval(() => setNow(Date.now() / 1000), intervalMs);
    return () => window.clearInterval(t);
  }, [intervalMs]);
  return now;
}
/** A ticking time-left readout with its progress bar (jail, bans, VIP). */
export function Countdown({ until, total, tone, width = 96, label }: { until: number; total?: number; tone?: 'ok' | 'warn' | 'bad'; width?: number; label?: string }) {
  const now = useNow(until - Date.now() / 1000 < 3600 ? 1000 : 30000);
  const left = Math.max(0, until - now);
  const pct = total && total > 0 ? (left / total) * 100 : 0;
  return (
    <div style={{ width, display: 'flex', flexDirection: 'column', gap: 4, alignItems: 'flex-end' }} aria-label={label}>
      <span className="num">{left > 0 ? fmtLeft(left) : 'Due'}</span>
      {total ? <Bar pct={pct} tone={tone} width={width} /> : null}
    </div>
  );
}
export function Ago({ ts }: { ts: number | null | undefined }) {
  return <span className="mono" title={ts ? new Date(ts * 1000).toLocaleString('en-US') : undefined}>{relTime(ts)}</span>;
}

/* ---------- Pager ---------- */
export function Pager({ page, lastPage, total, onPage, label = 'rows' }: { page: number; lastPage: number; total?: number; onPage: (p: number) => void; label?: string }) {
  if (lastPage <= 1 && !total) return null;
  return (
    <div className="pager">
      <span>
        {total !== undefined ? `${fmtNumber(total)} ${label} · ` : ''}page {page} of {Math.max(1, lastPage)}
      </span>
      <div className="actions">
        <Btn sm icon="chevron-left" disabled={page <= 1} onClick={() => onPage(page - 1)}>
          Previous
        </Btn>
        <Btn sm disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
          Next <Icon name="chevron-right" />
        </Btn>
      </div>
    </div>
  );
}
/** "Load older" for cursor-paged streams (timelines, the log explorer). */
export function More({ onClick, loading, done }: { onClick: () => void; loading?: boolean; done?: boolean }) {
  if (done) return null;
  return (
    <div className="pager" style={{ justifyContent: 'center' }}>
      <Btn sm onClick={onClick} disabled={loading}>
        {loading ? 'Loading' : 'Load older'}
      </Btn>
    </div>
  );
}

/* ---------- Dialogs ---------- */
export function Dialog({ title, wp, onClose, children, footer, wide, labelledBy = 'hk-dialog-title' }: { title: React.ReactNode; wp?: WritePath; onClose: () => void; children: React.ReactNode; footer?: React.ReactNode; wide?: boolean; labelledBy?: string }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);
  return createPortal(
    <div className="hk-portal">
      <div className="scrim" onClick={onClose} />
      <div className="dialog-wrap" onClick={onClose}>
        <div className={`dialog ${wide ? 'wide' : ''}`} role="dialog" aria-modal="true" aria-labelledby={labelledBy} onClick={(e) => e.stopPropagation()}>
          <div className="card-h">
            <h3 id={labelledBy}>{title}</h3>
            <div className="actions">
              {wp ? <Wp path={wp} /> : null}
              <Btn variant="quiet" sm iconOnly icon="close" aria-label="Close" onClick={onClose} />
            </div>
          </div>
          <div className="card-b">{children}</div>
          {footer ? <div className="card-f">{footer}</div> : null}
        </div>
      </div>
    </div>,
    document.querySelector('.hk') ?? document.body,
  );
}

/**
 * The action dialog: anything that touches money, discipline or access asks
 * for a reason, and the reason goes to the audit log with the change.
 */
export function ActionDialog({ title, wp, note, cta, danger, reason = true, onClose, onSubmit, children, valid = true }: { title: string; wp?: WritePath; note?: React.ReactNode; cta: string; danger?: boolean; reason?: boolean; onClose: () => void; onSubmit: (reason: string) => Promise<void>; children?: React.ReactNode; valid?: boolean }) {
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const ok = valid && (!reason || text.trim().length >= 3);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!ok || busy) return;
    setBusy(true);
    setError(null);
    try {
      await onSubmit(text.trim());
      onClose();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Something went wrong');
      setBusy(false);
    }
  };
  return (
    <Dialog
      title={title}
      wp={wp}
      onClose={onClose}
      footer={
        <>
          <Btn onClick={onClose}>Cancel</Btn>
          <Btn type="submit" variant={danger ? 'danger-primary' : 'primary'} disabled={!ok || busy} form="hk-action-form">
            {busy ? 'Working' : cta}
          </Btn>
        </>
      }
    >
      <form id="hk-action-form" onSubmit={submit} className="stack16">
        {children}
        {reason ? (
          <Field label="Reason" optional="required">
            <textarea className="input" rows={2} value={text} onChange={(e) => setText(e.target.value)} maxLength={500} required />
          </Field>
        ) : null}
        {note ? <p className="sub" style={{ fontSize: 12 }}>{note}</p> : null}
        {error ? <Note tone="bad" icon="warning" title={error} /> : null}
      </form>
    </Dialog>
  );
}

/* ---------- Menus ---------- */
export function MenuButton({ label, icon = 'more', children, align = 'right', sm = true }: { label: string; icon?: IconName; children: (close: () => void) => React.ReactNode; align?: 'left' | 'right'; sm?: boolean }) {
  const [open, setOpen] = useState(false);
  const close = () => setOpen(false);
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);
  return (
    <span className="rel" style={{ display: 'inline-flex' }}>
      <Btn variant="quiet" sm={sm} iconOnly icon={icon} aria-label={label} aria-haspopup="menu" aria-expanded={open} onClick={(e) => { e.stopPropagation(); setOpen((o) => !o); }} />
      {open ? (
        <>
          <div className="scrim clear" onClick={(e) => { e.stopPropagation(); close(); }} />
          <div className="menu" role="menu" style={{ top: '100%', marginTop: 4, [align]: 0 }} onClick={(e) => e.stopPropagation()}>
            {children(close)}
          </div>
        </>
      ) : null}
    </span>
  );
}
