import React, { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch, useStream } from '@hk/lib/useFetch';
import { get, post, qs } from '@hk/lib/api';
import { fmtDayHead, fmtNumber, fmtWhen } from '@hk/lib/format';
import { Btn, Card, Dialog, Empty, Field, FilterSelect, Icon, IconName, Kind, Loadable, Money, More, PlayerChip, PlayerRef, RoomChip, SearchInput, Switch, Tone, useDebounced, Wp, WritePath } from '@hk/ui';

export type LogPart = { text: string } | { player: PlayerRef } | { room: number; name: string } | { amount: number } | { quote: string };
export interface LogRow {
  key: string;
  source: string;
  kind: string;
  at: number;
  parts: LogPart[];
  amount: { value: number; owner?: number; from?: number; to?: number; after?: number; account?: string } | null;
  ledger?: { book: string; what: string; other: PlayerRef | null; amount: number | null; after: number | null } | null;
  path: WritePath | null;
  raw: Record<string, string>;
}
export interface LogPage { rows: LogRow[]; next: string | null }
interface SourceInfo { key: string; group: string; kind: string; label: string }
interface Meta { sources: SourceInfo[]; groups: Record<string, string>; kinds: Record<string, string> }
interface SavedView { id: number; name: string; filters: Record<string, string>; today: number | null }

export const KIND_ICON: Record<string, IconName> = { chat: 'message', money: 'coins', justice: 'scale', trade: 'trade', login: 'key', furni: 'sofa', staff: 'shield', guard: 'shield-alert' };
export const KIND_TONE: Record<string, Tone> = { chat: 'info', money: 'ok', justice: 'bad', trade: 'acc', login: '', furni: 'brand', staff: 'brand', guard: 'warn' };

/**
 * A log row's sentence. On a player's own timeline (`me`) a leading chip of
 * that player is dropped and the verb capitalised; `plain` draws names as
 * text for tight spots.
 */
export function LogParts({ parts, me, plain }: { parts: LogPart[]; me?: number; plain?: boolean }) {
  let list = parts;
  let cap = false;
  const first = parts[0];
  if (me && first && 'player' in first && first.player.id === me) {
    list = parts.slice(1);
    cap = true;
  }
  return (
    <>
      {list.map((p, i) => {
        if ('text' in p) {
          const t = cap && i === 0 ? p.text.charAt(0).toUpperCase() + p.text.slice(1) : p.text;
          return <span key={i}>{t} </span>;
        }
        if ('player' in p) return plain ? <b key={i} style={{ fontWeight: 500 }}>{p.player.username} </b> : <PlayerChip key={i} player={p.player} />;
        if ('room' in p) return plain ? <span key={i} className="sub">· {p.name} </span> : <RoomChip key={i} id={p.room} name={p.name} />;
        if ('amount' in p) return <Money key={i} value={p.amount} />;
        return (
          <span key={i} className="sub" style={{ overflowWrap: 'anywhere' }}>
            “{p.quote}”{' '}
          </span>
        );
      })}
    </>
  );
}

/** A signed amount from the perspective of `me` (+ coming in, − going out). */
export function signedAmount(row: LogRow, me?: number): number | null {
  const a = row.amount;
  if (!a) return null;
  if (a.from !== undefined && a.to !== undefined) {
    if (me === a.to) return a.value;
    if (me === a.from) return -a.value;
    return null;
  }
  return a.value;
}

/** One timeline row: time, kind, sentence, source and the raw fields. */
export function LogLine({ row, me, open, onToggle, showDay }: { row: LogRow; me?: number; open: boolean; onToggle: () => void; showDay?: boolean }) {
  const amount = me ? signedAmount(row, me) : null;
  return (
    <div className={`tl ${open ? 'open' : ''}`}>
      <span className="mono">{showDay ? fmtWhen(row.at) : new Date(row.at * 1000).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}</span>
      <Kind icon={KIND_ICON[row.kind] ?? 'list'} tone={KIND_TONE[row.kind] ?? ''} />
      <div className="sum">
        {me && row.ledger ? (
          <>
            <span>{row.ledger.what}</span>
            {row.ledger.other ? <PlayerChip player={row.ledger.other} /> : null}
          </>
        ) : (
          <LogParts parts={row.parts} me={me} />
        )}
        {amount !== null && row.kind === 'money' ? (
          <span className={`num ${amount > 0 ? 'pos' : amount < 0 ? 'neg' : ''}`}>
            {amount > 0 ? '+' : amount < 0 ? '−' : ''}
            {fmtNumber(Math.abs(amount))}
          </span>
        ) : null}
        {row.path ? <Wp path={row.path} /> : null}
      </div>
      <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <span className="mono hide-phone" style={{ fontSize: 11 }}>
          {row.source}
        </span>
        <button className="btn quiet icon sm" type="button" onClick={onToggle} aria-expanded={open} aria-label="Show raw fields">
          <Icon name={open ? 'chevron-up' : 'chevron-down'} />
        </button>
      </span>
      {open ? (
        <div className="raw">
          {Object.entries(row.raw).map(([k, v]) => (
            <React.Fragment key={k}>
              <span>{k}</span>
              <span style={{ color: 'var(--ink)' }}>{v}</span>
            </React.Fragment>
          ))}
        </div>
      ) : null}
    </div>
  );
}

/** Rows grouped under day heads, newest first. */
export function DayGroups({ rows, me, open, setOpen }: { rows: LogRow[]; me?: number; open: string | null; setOpen: (k: string | null) => void }) {
  let day = '';
  return (
    <>
      {rows.map((r) => {
        const head = fmtDayHead(r.at);
        const show = head !== day;
        day = head;
        return (
          <React.Fragment key={r.key}>
            {show ? <div className="dayhead">{head}</div> : null}
            <LogLine row={r} me={me} open={open === r.key} onToggle={() => setOpen(open === r.key ? null : r.key)} />
          </React.Fragment>
        );
      })}
    </>
  );
}

const WHEN = [
  { value: '1h', label: 'Last hour' },
  { value: '24h', label: 'Last 24 hours' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: 'all', label: 'All time' },
];

export default function LogExplorer() {
  useCrumbs([{ label: 'Logs', to: '/logs' }, { label: 'Log explorer' }]);
  const { toast } = useSession();
  const [params, setParams] = useSearchParams();
  const [text, setText] = useState(params.get('q') ?? '');
  const q = useDebounced(text.trim(), 350);
  const kind = params.get('kind') ?? '';
  const player = params.get('player') ?? '';
  const room = params.get('room') ?? '';
  const when = params.get('when') ?? '24h';
  const off = useMemo(() => new Set((params.get('off') ?? '').split(',').filter(Boolean)), [params]);
  const [open, setOpen] = useState<string | null>(null);
  const [streaming, setStreaming] = useState(false);
  const [saving, setSaving] = useState(false);
  const meta = useFetch<Meta>('/logs/counts?meta=1');
  const views = useFetch<{ views: SavedView[] }>('/views?mine_and_shared=1');
  const playerName = useFetch<{ username: string }>(player ? `/players/${player}/card` : null);

  const setParam = (k: string, v: string) => {
    const next = new URLSearchParams(params);
    if (v) next.set(k, v);
    else next.delete(k);
    setParams(next, { replace: true });
  };
  useEffect(() => {
    if (q !== (params.get('q') ?? '')) setParam('q', q);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q]);

  const filterQs = { q: params.get('q'), kind, player, room, when, off: [...off].join(',') };
  const path = `/logs${qs(filterQs)}`;
  const stream = useStream<LogPage & { total: number | null }, LogRow>(path, (d) => d.rows);
  const counts = useFetch<{ counts: Record<string, number>; total: number }>(`/logs/counts${qs(filterQs)}`);

  useEffect(() => {
    if (!streaming) return;
    const t = window.setInterval(() => stream.first.reload(), 15000);
    return () => window.clearInterval(t);
  }, [streaming, stream.first]);

  const sources = meta.data?.sources ?? [];
  const groups = meta.data?.groups ?? {};
  const toggleSource = (key: string) => {
    const next = new Set(off);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    setParam('off', [...next].join(','));
  };
  const whenLabel = WHEN.find((w) => w.value === when)?.label.toLowerCase() ?? '';

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Log explorer</h1>
          <p className="sub">Every log table in one stream · newest first</p>
        </div>
        <div className="actions">
          <Btn href={`/housekeeping/api/logs/export${qs(filterQs)}`} icon="external">
            Export CSV
          </Btn>
          <Btn variant="primary" icon="bookmark" onClick={() => setSaving(true)}>
            Save view
          </Btn>
        </div>
      </div>
      <div className="filters">
        <SearchInput value={text} onChange={setText} placeholder="Search text in messages and fields" width="min(100%, 320px)" />
        <FilterSelect label="Kind" value={kind} anyLabel="All" options={Object.entries(meta.data?.kinds ?? {}).map(([value, label]) => ({ value, label }))} onChange={(v) => setParam('kind', v)} />
        {player ? (
          <span className="select on">
            <span className="k">Player</span> {playerName.data?.username ?? `#${player}`}
            <button type="button" className="btn quiet sm icon" aria-label="Clear player" onClick={() => setParam('player', '')} style={{ width: 22, height: 22 }}>
              <Icon name="close" size={12} />
            </button>
          </span>
        ) : (
          <PlayerPicker onPick={(id) => setParam('player', String(id))} />
        )}
        {room ? (
          <span className="select on">
            <span className="k">Room</span> #{room}
            <button type="button" className="btn quiet sm icon" aria-label="Clear room" onClick={() => setParam('room', '')} style={{ width: 22, height: 22 }}>
              <Icon name="close" size={12} />
            </button>
          </span>
        ) : (
          <RoomField onPick={(id) => setParam('room', String(id))} />
        )}
        <FilterSelect label="When" value={when} options={WHEN} anyLabel="Last 24 hours" onChange={(v) => setParam('when', v || '24h')} />
      </div>
      <div className="split">
        <aside className="side-col stack" style={{ flex: '1 1 240px' }}>
          <Card title="Saved views" action={<Link to="/logs/views" style={{ fontSize: 13 }}>Manage</Link>}>
            <Loadable state={views} empty={(d) => (d.views.length === 0 ? <Empty title="No saved views yet" body="Set filters, then Save view." /> : false)}>
              {(d) =>
                d.views.slice(0, 8).map((v) => (
                  <button key={v.id} type="button" className="row" style={{ paddingTop: 8, paddingBottom: 8 }} onClick={() => setParams(new URLSearchParams(v.filters), { replace: true })}>
                    <span className="grow ell">{v.name}</span>
                    <span className="mono">{v.today !== null ? `${fmtNumber(v.today)} today` : ''}</span>
                  </button>
                ))
              }
            </Loadable>
          </Card>
          <Card title="Sources" action={<span className="sub">{sources.length - [...off].filter((k) => sources.some((s) => s.key === k)).length} of {sources.length}</span>}>
            <div className="card-b stack16" style={{ gap: 14 }}>
              {Object.entries(groups).map(([g, label]) => {
                const inGroup = sources.filter((s) => s.group === g);
                if (inGroup.length === 0) return null;
                return (
                  <div key={g} className="stack16" style={{ gap: 6 }}>
                    <span className="overline">{label}</span>
                    {inGroup.map((s) => (
                      <label key={s.key} className="check">
                        <input type="checkbox" checked={!off.has(s.key)} onChange={() => toggleSource(s.key)} />
                        <span className="mono grow" style={{ color: 'var(--ink)' }}>
                          {s.key}
                        </span>
                        <span className="mono">{counts.data ? (counts.data.counts[s.key] >= 1000 ? '1k+' : counts.data.counts[s.key] ?? 0) : ''}</span>
                      </label>
                    ))}
                  </div>
                );
              })}
            </div>
          </Card>
        </aside>
        <section className="card main-col">
          <div className="card-h">
            <span className="sub">
              {counts.data ? `${counts.data.total >= 1000 * sources.length ? 'Thousands of' : fmtNumber(counts.data.total)} events ${whenLabel === 'all time' ? 'in all' : `in the ${whenLabel.replace('last ', 'last ')}`}` : 'Counting'} · showing {fmtNumber(stream.rows.length)}
            </span>
            <span className="status">
              <Switch checked={streaming} onChange={setStreaming} label="Stream new events" />
              {streaming ? 'Streaming' : 'Paused'}
            </span>
          </div>
          <Loadable state={stream.first} rows={10} empty={() => (stream.rows.length === 0 ? <Empty title="Nothing matches" body="Widen the time range or turn more sources on." /> : false)}>
            {() => (
              <>
                {stream.rows.map((r) => (
                  <LogLine key={r.key} row={r} open={open === r.key} onToggle={() => setOpen(open === r.key ? null : r.key)} showDay />
                ))}
                <More onClick={stream.more} loading={stream.loadingMore} done={stream.next === null} />
              </>
            )}
          </Loadable>
        </section>
      </div>
      {saving ? (
        <SaveView
          filters={Object.fromEntries(Object.entries(filterQs).filter(([, v]) => v)) as Record<string, string>}
          onClose={() => setSaving(false)}
          onSaved={() => {
            views.reload();
            toast('ok', 'View saved');
          }}
        />
      ) : null}
    </main>
  );
}

function SaveView({ filters, onClose, onSaved }: { filters: Record<string, string>; onClose: () => void; onSaved: () => void }) {
  const [name, setName] = useState('');
  const [shared, setShared] = useState(true);
  const [busy, setBusy] = useState(false);
  return (
    <Dialog
      title="Save view"
      wp="saved"
      onClose={onClose}
      footer={
        <>
          <Btn onClick={onClose}>Cancel</Btn>
          <Btn
            variant="primary"
            disabled={name.trim().length < 2 || busy}
            onClick={async () => {
              setBusy(true);
              try {
                await post('/views', { name, filters, shared });
                onSaved();
                onClose();
              } finally {
                setBusy(false);
              }
            }}
          >
            Save view
          </Btn>
        </>
      }
    >
      <Field label="Name">
        <input className="input" value={name} onChange={(e) => setName(e.target.value)} maxLength={64} autoFocus placeholder="Trades over 10,000 coins" />
      </Field>
      <label className="check">
        <input type="checkbox" checked={shared} onChange={(e) => setShared(e.target.checked)} />
        Share with every rank that can open the log explorer
      </label>
      <p className="sub">Saves the current search, kind, player, room, time range and sources.</p>
    </Dialog>
  );
}

/** Pick a player by name for a filter. */
export function PlayerPicker({ onPick, label = 'Player' }: { onPick: (id: number) => void; label?: string }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const term = useDebounced(q.trim(), 200);
  const [found, setFound] = useState<{ id: number; username: string; look: string }[]>([]);
  useEffect(() => {
    if (term.length < 2) {
      setFound([]);
      return;
    }
    let live = true;
    get<{ players: { id: number; username: string; look: string }[] }>(`/search${qs({ q: term })}`).then((r) => live && setFound(r.players));
    return () => {
      live = false;
    };
  }, [term]);
  return (
    <span className="rel" style={{ display: 'inline-flex' }}>
      <button type="button" className="select" onClick={() => setOpen((o) => !o)} aria-expanded={open}>
        <span className="k">{label}</span> Anyone <Icon name="chevron-down" size={14} />
      </button>
      {open ? (
        <>
          <div className="scrim clear" onClick={() => setOpen(false)} />
          <div className="menu" style={{ top: '100%', left: 0, marginTop: 4, width: 260 }}>
            <input className="input" autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name or ID" aria-label="Find a player" style={{ marginBottom: 6 }} />
            {found.map((p) => (
              <button
                key={p.id}
                type="button"
                onClick={() => {
                  onPick(p.id);
                  setOpen(false);
                }}
              >
                {p.username} <span className="mono" style={{ marginLeft: 'auto' }}>#{p.id}</span>
              </button>
            ))}
            {term.length >= 2 && found.length === 0 ? <span className="sub" style={{ padding: '4px 10px' }}>Nobody by that name</span> : null}
          </div>
        </>
      ) : null}
    </span>
  );
}

function RoomField({ onPick }: { onPick: (id: number) => void }) {
  const [open, setOpen] = useState(false);
  const [v, setV] = useState('');
  return (
    <span className="rel" style={{ display: 'inline-flex' }}>
      <button type="button" className="select" onClick={() => setOpen((o) => !o)} aria-expanded={open}>
        <span className="k">Room</span> Any <Icon name="chevron-down" size={14} />
      </button>
      {open ? (
        <>
          <div className="scrim clear" onClick={() => setOpen(false)} />
          <form
            className="menu"
            style={{ top: '100%', left: 0, marginTop: 4, width: 220 }}
            onSubmit={(e) => {
              e.preventDefault();
              if (/^\d+$/.test(v)) {
                onPick(Number(v));
                setOpen(false);
              }
            }}
          >
            <input className="input mono" autoFocus value={v} onChange={(e) => setV(e.target.value.replace(/\D/g, ''))} placeholder="Room ID" aria-label="Room ID" />
          </form>
        </>
      ) : null}
    </span>
  );
}
