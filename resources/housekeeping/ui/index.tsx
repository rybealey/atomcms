import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { ICONS, IconName } from '@hk/lib/icons';
import { headUrl } from '@hk/lib/avatar';

/* ---------- Icon ---------- */
export function Icon({ name, size = 20, className = '', style }: { name: IconName; size?: number; className?: string; style?: React.CSSProperties }) {
  return <img className={`ico ${className}`} src={ICONS[name]} alt="" width={size} height={size} style={{ width: size, height: size, flex: 'none', ...style }} />;
}

/* ---------- Button ---------- */
type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: 'sm' | 'md';
  to?: string;
  href?: string;
  external?: boolean;
}
export function Button({ variant = 'primary', size = 'md', to, href, external, className = '', children, ...rest }: ButtonProps) {
  const cls = `hk-btn hk-btn--${variant} ${size === 'sm' ? 'hk-btn--sm' : ''} ${className}`;
  if (to) {
    return (
      <Link to={to} className={cls}>
        {children}
      </Link>
    );
  }
  if (href) {
    return (
      <a href={href} className={cls} target={external ? '_blank' : undefined} rel={external ? 'noreferrer' : undefined}>
        {children}
      </a>
    );
  }
  return (
    <button type="button" className={cls} {...rest}>
      {children}
    </button>
  );
}

export function IconButton({ name, label, size = 'md', plain, ...rest }: { name: IconName; label: string; size?: 'sm' | 'md'; plain?: boolean } & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button type="button" className={`hk-iconbtn ${size === 'sm' ? 'hk-iconbtn--sm' : ''} ${plain ? 'hk-iconbtn--plain' : ''}`} aria-label={label} title={label} {...rest}>
      <Icon name={name} size={size === 'sm' ? 18 : 20} />
    </button>
  );
}

/* ---------- Switch ---------- */
export function Switch({ checked, onChange, disabled, label }: { checked: boolean; onChange: (next: boolean) => void; disabled?: boolean; label?: string }) {
  return <button type="button" role="switch" aria-checked={checked} aria-label={label} className="hk-switch" disabled={disabled} onClick={() => onChange(!checked)} />;
}

export function StateText({ on, onText = 'On', offText = 'Off', danger }: { on: boolean; onText?: string; offText?: string; danger?: boolean }) {
  return <span className={`hk-state ${on ? (danger ? 'hk-state--danger' : 'hk-state--on') : ''}`}>{on ? onText : offText}</span>;
}

/* ---------- Pills & chips ---------- */
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'ink' | 'orange';
export function Pill({ tone = 'info', children }: { tone?: Tone; children: React.ReactNode }) {
  return <span className={`hk-pill hk-pill--${tone}`}>{children}</span>;
}

export function rankTone(rank: number, minStaff: number): string {
  if (rank >= 8) return 'hk-rank--top';
  if (rank >= 6) return 'hk-rank--mgmt';
  if (rank >= Math.min(2, minStaff)) return 'hk-rank--staff';
  return '';
}

export function RankChip({ rank, name, minStaff = 4 }: { rank: number; name: string; minStaff?: number }) {
  return (
    <span className={`hk-rank ${rankTone(rank, minStaff)}`}>
      {name} <small>{rank}</small>
    </span>
  );
}

export function Avatar({ look, imager, size = 'md', title }: { look: string; imager: string; size?: 'xs' | 'sm' | 'md' | 'lg'; title?: string }) {
  const cls = size === 'sm' ? 'hk-avatar--sm' : size === 'xs' ? 'hk-avatar--xs' : size === 'lg' ? 'hk-avatar--md' : '';
  return (
    <span className={`hk-avatar ${cls}`} title={title}>
      {look ? <img src={headUrl(imager, look)} alt="" className="px-art" loading="lazy" /> : null}
    </span>
  );
}

export function OnlineDot({ online, banned }: { online: boolean; banned?: boolean }) {
  return <span className={`hk-dot ${banned ? 'hk-dot--danger' : online ? 'hk-dot--on' : 'hk-dot--off'}`} />;
}

/* ---------- Tabs ---------- */
export interface TabDef { id: string; label: string }
export function Tabs({ tabs, active, onChange }: { tabs: TabDef[]; active: string; onChange: (id: string) => void }) {
  return (
    <div role="tablist" className="hk-tabs">
      {tabs.map((t) => (
        <button key={t.id} type="button" role="tab" aria-selected={t.id === active} className="hk-tab" onClick={() => onChange(t.id)}>
          {t.label}
        </button>
      ))}
    </div>
  );
}

/* ---------- Search ---------- */
export function SearchBox({ value, onChange, placeholder, width, kbd, autoFocus }: { value: string; onChange: (v: string) => void; placeholder: string; width?: number | string; kbd?: string; autoFocus?: boolean }) {
  return (
    <label className="hk-search" style={{ width: width ?? 260 }}>
      <Icon name="search" size={16} />
      <span className="sr-only">{placeholder}</span>
      <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} autoFocus={autoFocus} />
      {kbd ? <kbd>{kbd}</kbd> : null}
    </label>
  );
}

/* ---------- Card ---------- */
export function Card({ title, action, children, flat, lg, className = '', style }: { title?: React.ReactNode; action?: React.ReactNode; children: React.ReactNode; flat?: boolean; lg?: boolean; className?: string; style?: React.CSSProperties }) {
  return (
    <div className={`hk-card ${flat ? 'hk-card--flat' : ''} ${lg ? 'hk-card--lg' : ''} ${className}`} style={style}>
      {title !== undefined ? (
        <div className="hk-card__head">
          <h2>{title}</h2>
          {action}
        </div>
      ) : null}
      {children}
    </div>
  );
}

/* ---------- Segmented / stepper ---------- */
export function Segmented<T extends string | number>({ options, value, onChange }: { options: { value: T; label: string }[]; value: T; onChange: (v: T) => void }) {
  return (
    <div role="radiogroup" className="hk-seg">
      {options.map((o) => (
        <button key={String(o.value)} type="button" aria-pressed={o.value === value} onClick={() => onChange(o.value)}>
          {o.label}
        </button>
      ))}
    </div>
  );
}

export function Stepper({ value, onChange, min = 0, step = 1, unit, id }: { value: number; onChange: (v: number) => void; min?: number; step?: number; unit?: string; id?: string }) {
  return (
    <div className="hk-stepper">
      <button type="button" aria-label="Decrease" onClick={() => onChange(Math.max(min, value - step))}>
        −
      </button>
      <input id={id} value={value} inputMode="numeric" onChange={(e) => onChange(Number(e.target.value.replace(/[^\d-]/g, '')) || 0)} />
      <button type="button" className="inc" aria-label="Increase" onClick={() => onChange(value + step)}>
        +
      </button>
      {unit ? <span className="unit">{unit}</span> : null}
    </div>
  );
}

/* ---------- Pager ---------- */
export function Pager({ page, lastPage, total, perPage, onPage, onPerPage, shown }: { page: number; lastPage: number; total: number; perPage: number; onPage: (p: number) => void; onPerPage?: (n: number) => void; shown: number }) {
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(total, from + shown - 1);
  const pages: number[] = [];
  for (let p = Math.max(1, page - 2); p <= Math.min(lastPage, page + 2); p++) pages.push(p);
  return (
    <div className="hk-pager">
      <span>
        Showing {from} to {to} of {total.toLocaleString('en-US')}
      </span>
      <span className="hk-pager__pages">
        <button type="button" aria-label="Previous page" disabled={page <= 1} onClick={() => onPage(page - 1)}>
          ◀
        </button>
        {pages.map((p) => (
          <button key={p} type="button" aria-current={p === page ? 'page' : undefined} onClick={() => onPage(p)}>
            {p}
          </button>
        ))}
        <button type="button" aria-label="Next page" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
          ▶
        </button>
      </span>
      {onPerPage ? (
        <label style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          Per page
          <span className="hk-selectwrap" style={{ width: 'auto' }}>
            <select className="hk-select hk-select--sm" value={perPage} onChange={(e) => onPerPage(Number(e.target.value))}>
              <option value={25}>25</option>
              <option value={50}>50</option>
              <option value={100}>100</option>
            </select>
          </span>
        </label>
      ) : (
        <span />
      )}
    </div>
  );
}

/* ---------- Empty / skeleton / error ---------- */
export function EmptyState({ title, body, action, figure }: { title: string; body?: string; action?: React.ReactNode; figure?: React.ReactNode }) {
  return (
    <div className="hk-empty">
      {figure}
      <h3>{title}</h3>
      {body ? <p>{body}</p> : null}
      {action}
    </div>
  );
}

export function Skeleton({ rows = 3 }: { rows?: number }) {
  const widths = ['60%', '85%', '40%', '70%', '55%'];
  return (
    <div aria-hidden="true" style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: 16 }}>
      {Array.from({ length: rows }, (_, i) => (
        <span key={i} className="hk-skel" style={{ width: widths[i % widths.length] }} />
      ))}
    </div>
  );
}

export function ErrorBox({ message, retry }: { message: string; retry?: () => void }) {
  return (
    <div className="hk-empty hk-empty--dashed" role="alert">
      <span>{message}</span>
      {retry ? (
        <Button variant="ghost" size="sm" onClick={retry}>
          Try again
        </Button>
      ) : null}
    </div>
  );
}

/* ---------- Row menu ---------- */
export function RowMenu({ label, children }: { label: string; children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);
  return (
    <div ref={ref} style={{ position: 'relative', display: 'inline-block' }}>
      <IconButton name="more-horizontal" label={label} plain aria-expanded={open} onClick={() => setOpen((o) => !o)} />
      {open ? (
        <div role="menu" className="hk-menu" onClick={() => setOpen(false)}>
          {children}
        </div>
      ) : null}
    </div>
  );
}

/* ---------- Legacy link ---------- */
export function LegacyLink({ legacyUrl, slug, children, size = 'sm', variant = 'ghost' }: { legacyUrl: string; slug: string; children: React.ReactNode; size?: 'sm' | 'md'; variant?: Variant }) {
  return (
    <Button variant={variant} size={size} href={`${legacyUrl}/${slug}`} external>
      {children} <Icon name="external-link" size={14} style={{ filter: variant === 'primary' || variant === 'danger' ? 'invert(1)' : undefined }} />
    </Button>
  );
}
