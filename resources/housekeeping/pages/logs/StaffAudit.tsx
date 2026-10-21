import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useStream } from '@hk/lib/useFetch';
import { qs } from '@hk/lib/api';
import { fmtDateTime, fmtNumber } from '@hk/lib/format';
import { Btn, Empty, FilterSelect, Icon, Loadable, More, PlayerChip, PlayerRef, Seg, Wp, WritePath } from '@hk/ui';

export interface AuditRow {
  id: string;
  at: number;
  actor: PlayerRef | null;
  event: string;
  summary: string;
  reason: string | null;
  path: WritePath | null;
  player: PlayerRef | null;
  changes: { field: string; before: string | null; after: string | null }[];
  result: string;
}
interface AuditData {
  rows: AuditRow[];
  next: number | null;
  by_staff: { player: PlayerRef | null; actions: number; coins: number }[] | null;
  events: string[];
  staff: { value: string; label: string }[];
}

const RANGES = [
  { value: '24h', label: 'Last 24 hours' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: '90d', label: 'Last 90 days' },
] as const;

export function capital(s: string): string {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
}

export function AuditLine({ row, open, onToggle, wide }: { row: AuditRow; open: boolean; onToggle: () => void; wide?: boolean }) {
  return (
    <div className={`tl ${open ? 'open' : ''}`} style={wide ? { gridTemplateColumns: '96px 110px minmax(0, 1fr) auto' } : undefined}>
      <span className="mono">{fmtDateTime(row.at)}</span>
      {wide ? <span style={{ fontWeight: 500 }} className="ell">{row.actor?.username ?? 'System'}</span> : null}
      <div className="sum">
        <span>{capital(row.summary)}</span>
        {row.reason ? <span className="sub">“{row.reason}”</span> : null}
      </div>
      <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {row.path ? <Wp path={row.path} /> : null}
        <button className="btn quiet icon sm" type="button" onClick={onToggle} aria-expanded={open} aria-label="Show before and after">
          <Icon name={open ? 'chevron-up' : 'chevron-down'} />
        </button>
      </span>
      {open ? (
        <div className="raw" style={{ gridColumn: wide ? '2 / 5' : '3 / 5' }}>
          {row.player ? (
            <>
              <span>player</span>
              <span style={{ color: 'var(--ink)' }}>{row.player.username}</span>
            </>
          ) : null}
          {row.changes.map((c) => (
            <ChangeRows key={c.field} {...c} />
          ))}
          <span>result</span>
          <span style={{ color: 'var(--ink)' }}>{row.result}</span>
        </div>
      ) : null}
    </div>
  );
}

function ChangeRows({ field, before, after }: { field: string; before: string | null; after: string | null }) {
  return (
    <>
      <span>{field}</span>
      <span>
        {before !== null ? <span className="neg">{before}</span> : null}
        {before !== null && after !== null ? ' → ' : null}
        {after !== null ? <span className="pos">{after}</span> : null}
      </span>
    </>
  );
}

export default function StaffAudit() {
  useCrumbs([{ label: 'Logs', to: '/logs' }, { label: 'Staff audit' }]);
  const [search] = useSearchParams();
  const [staff, setStaff] = useState(search.get('staff') ?? '');
  const [event, setEvent] = useState('');
  const [path, setPath] = useState<'all' | WritePath>('all');
  const [range, setRange] = useState<string>('7d');
  const [open, setOpen] = useState<string | null>(null);
  const params = { staff, event, path: path === 'all' ? '' : path, range };
  const stream = useStream<AuditData, AuditRow>(`/audit${qs(params)}`, (d) => d.rows, 'before');
  const state = stream.first;
  const rows = stream.rows;
  const maxActions = Math.max(1, ...(state.data?.by_staff ?? []).map((s) => s.actions));
  const reset = () => setOpen(null);

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Staff audit</h1>
          <p className="sub">Every change staff made, from the panel and in game · activity_log and logs_client_staff</p>
        </div>
        <Btn href={`/housekeeping/api/audit${qs({ ...params, csv: 1 })}`} icon="external">
          Export CSV
        </Btn>
      </div>
      <div className="filters">
        <FilterSelect label="Staff" value={staff} anyLabel="Anyone" options={state.data?.staff ?? []} onChange={(v) => { reset(); setStaff(v); }} />
        <FilterSelect label="Action" value={event} anyLabel="All" options={(state.data?.events ?? []).concat('staff_tool').map((e) => ({ value: e, label: capital(e.replace(/_/g, ' ')) }))} onChange={(v) => { reset(); setEvent(v); }} />
        <Seg
          label="How it reached the game"
          value={path}
          onChange={(v) => { reset(); setPath(v); }}
          options={[
            { value: 'all', label: 'All' },
            { value: 'live', label: 'Live' },
            { value: 'saved', label: 'Saved' },
            { value: 'game', label: 'In-game' },
          ]}
        />
        <FilterSelect label="When" value={range} options={[...RANGES]} anyLabel="Last 7 days" onChange={(v) => { reset(); setRange(v || '7d'); }} />
      </div>
      <div className="split">
        <section className="card main-col">
          <Loadable state={state} rows={8} empty={() => (rows.length === 0 ? <Empty title="No staff changes in this range" /> : false)}>
            {() => (
              <>
                {rows.map((r) => (
                  <AuditLine key={r.id} row={r} wide open={open === r.id} onToggle={() => setOpen(open === r.id ? null : r.id)} />
                ))}
                <More onClick={stream.more} loading={stream.loadingMore} done={stream.next === null} />
              </>
            )}
          </Loadable>
        </section>
        <section className="card side-col">
          <div className="card-h">
            <div>
              <h3>By staff</h3>
              <p className="sub">Actions · coins given, {RANGES.find((r) => r.value === range)?.label.toLowerCase()}</p>
            </div>
          </div>
          <Loadable state={state} empty={(x) => (!x.by_staff?.length ? <Empty title="Nobody yet" /> : false)}>
            {(x) =>
              (x.by_staff ?? []).map((s, i) => (
                <div key={s.player?.id ?? i} className="row">
                  <PlayerChip player={s.player} />
                  <span className="grow" />
                  <span className="bar" style={{ width: 64 }}>
                    <i style={{ width: `${Math.round((s.actions / maxActions) * 100)}%` }} />
                  </span>
                  <span className="num" style={{ width: 32, textAlign: 'right' }}>
                    {s.actions}
                  </span>
                  <span className="num" style={{ width: 64, textAlign: 'right', color: 'var(--muted)' }}>
                    {fmtNumber(s.coins)}
                  </span>
                </div>
              ))
            }
          </Loadable>
        </section>
      </div>
    </main>
  );
}
