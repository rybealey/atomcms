import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, qs } from '@hk/lib/api';
import { fmtNumber, relTime } from '@hk/lib/format';
import { ActionDialog, AsOf, Avatar, Bar, Btn, Coin, Domain, Empty, Field, FilterSelect, Icon, Kv, Loadable, MenuButton, Pill, SearchInput, Seg, Tone, useDebounced } from '@hk/ui';
import { LogParts, LogRow } from '../logs/LogExplorer';

interface Row {
  id: number;
  username: string;
  look: string;
  online: boolean;
  last_online: number;
  account: number;
  siblings: number;
  job: { corporation: { id: number; name: string; acronym: string }; rank: string; domain: Domain } | null;
  gang: { id: number; name: string } | null;
  coins: number;
  bank: number | null;
  flag: { label: string; tone: Tone } | null;
}
interface ListData { items: Row[]; total: number; page: number; per_page: number; last_page: number; summary: { characters: number; accounts: number; online: number } }
interface Filters { ranks: Opt[]; corporations: Opt[]; gangs: Opt[]; regions: Opt[] }
interface Opt { value: string; label: string }
interface Peek {
  id: number;
  username: string;
  look: string;
  online: boolean;
  characters: number;
  account: number;
  job: { corporation: { id: number; name: string }; rank: string | null; domain: Domain; shift_week: number } | null;
  stats: { health: number; health_max: number; energy: number; energy_max: number } | null;
  money: { coins: number; current: number | null; savings: number | null; online: boolean };
  gang: { id: number; name: string; role: string } | null;
  vip: boolean;
  recent: LogRow[];
}

const VIEWS = [
  { value: 'all', label: 'All' },
  { value: 'online', label: 'Online now' },
  { value: 'jailed', label: 'Jailed' },
  { value: 'new', label: 'New this week' },
] as const;
type View = (typeof VIEWS)[number]['value'];

const COLUMNS = [
  { id: 'account', label: 'Account' },
  { id: 'job', label: 'Job' },
  { id: 'gang', label: 'Gang' },
  { id: 'coins', label: 'Coins' },
  { id: 'bank', label: 'Bank' },
  { id: 'last', label: 'Last online' },
] as const;
type Column = (typeof COLUMNS)[number]['id'];

function storedColumns(): Column[] {
  try {
    const v = JSON.parse(localStorage.getItem('hk-player-columns') ?? 'null');
    if (Array.isArray(v)) return v.filter((c): c is Column => COLUMNS.some((x) => x.id === c));
  } catch {
    /* default */
  }
  return COLUMNS.map((c) => c.id);
}

export default function Players() {
  useCrumbs([{ label: 'People', to: '/players' }, { label: 'Players' }]);
  const { can, able, toast } = useSession();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [q, setQ] = useState(params.get('q') ?? '');
  const query = useDebounced(q.trim(), 300);
  const get = (k: string) => params.get(k) ?? '';
  const set = (k: string, v: string) => {
    const next = new URLSearchParams(params);
    if (v) next.set(k, v);
    else next.delete(k);
    next.delete('page');
    setParams(next, { replace: true });
  };
  const view: View = get('flag') === 'jailed' ? 'jailed' : get('created') === '7' ? 'new' : get('status') === 'online' ? 'online' : 'all';
  const setView = (v: View) => {
    const next = new URLSearchParams(params);
    ['status', 'flag', 'created', 'page'].forEach((k) => next.delete(k));
    if (v === 'online') next.set('status', 'online');
    if (v === 'jailed') next.set('flag', 'jailed');
    if (v === 'new') next.set('created', '7');
    setParams(next, { replace: true });
  };

  useEffect(() => {
    if (query !== (params.get('q') ?? '')) set('q', query);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  const list = useFetch<ListData>(
    `/players${qs({ q: params.get('q'), status: get('status'), rank: get('rank'), corp: get('corp'), gang: get('gang'), flag: get('flag'), region: get('region'), created: get('created'), page: get('page'), per_page: get('per_page') })}`,
  );
  const filters = useFetch<Filters>('/players/filters');
  const [columns, setColumns] = useState<Column[]>(storedColumns);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [peekId, setPeekId] = useState<number | null>(null);
  const [bulk, setBulk] = useState<'alert' | 'note' | null>(null);
  const show = (c: Column) => columns.includes(c);
  const items = list.data?.items ?? [];
  const allSelected = items.length > 0 && items.every((r) => selected.has(r.id));

  useEffect(() => {
    try {
      localStorage.setItem('hk-player-columns', JSON.stringify(columns));
    } catch {
      /* ignore */
    }
  }, [columns]);
  useEffect(() => setSelected(new Set()), [list.data]);

  const summary = list.data?.summary;
  const [text, setText] = useState('');

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Players</h1>
          <p className="sub">{summary ? `${fmtNumber(summary.characters)} characters on ${fmtNumber(summary.accounts)} accounts · ${fmtNumber(summary.online)} online` : 'Loading'}</p>
        </div>
        <Seg label="Saved views" value={view} options={[...VIEWS]} onChange={setView} />
      </div>
      <div className="filters">
        <SearchInput value={q} onChange={setQ} placeholder={can('edit_user') ? 'Name, ID, IP or machine ID' : 'Name or ID'} width={280} />
        <FilterSelect label="Status" value={get('status') as '' | 'online' | 'offline'} options={[{ value: 'online', label: 'Online' }, { value: 'offline', label: 'Offline' }]} onChange={(v) => set('status', v)} />
        <FilterSelect label="Rank" value={get('rank')} options={filters.data?.ranks ?? []} onChange={(v) => set('rank', v)} />
        <FilterSelect label="Corporation" value={get('corp')} options={filters.data?.corporations ?? []} onChange={(v) => set('corp', v)} />
        <FilterSelect label="Gang" value={get('gang')} options={filters.data?.gangs ?? []} onChange={(v) => set('gang', v)} />
        <FilterSelect
          label="Flags"
          value={get('flag')}
          options={[
            { value: 'jailed', label: 'Jailed' },
            { value: 'banned', label: 'Banned' },
            { value: 'muted', label: 'Muted' },
            { value: 'vip', label: 'VIP' },
            { value: 'staff', label: 'Staff' },
            ...(can('edit_user') ? [{ value: 'flagged', label: 'Flagged by the browser guard' }] : []),
          ]}
          onChange={(v) => set('flag', v)}
        />
        {filters.data?.regions.length ? <FilterSelect label="Region" value={get('region')} options={filters.data.regions} onChange={(v) => set('region', v)} /> : null}
        <FilterSelect label="Created" value={get('created')} anyLabel="Any time" options={[{ value: '1', label: 'Last 24 hours' }, { value: '7', label: 'Last 7 days' }, { value: '30', label: 'Last 30 days' }, { value: '365', label: 'Last year' }]} onChange={(v) => set('created', v)} />
        <span style={{ marginLeft: 'auto' }}>
          <MenuButton label="Columns" icon="filter" sm={false}>
            {() =>
              COLUMNS.map((c) => (
                <label key={c.id} className="check" style={{ padding: '6px 10px' }}>
                  <input type="checkbox" checked={show(c.id)} onChange={() => setColumns((cols) => (cols.includes(c.id) ? cols.filter((x) => x !== c.id) : [...cols, c.id]))} />
                  {c.label}
                </label>
              ))
            }
          </MenuButton>
        </span>
      </div>
      <div className="split">
        <section className="card main-col">
          <Loadable state={list} rows={10} empty={(d) => (d.items.length === 0 ? <Empty title="No players match" body="Try fewer filters, or search by name." /> : false)}>
            {(d) => (
              <>
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th className="cb">
                          <input type="checkbox" aria-label="Select all" checked={allSelected} onChange={() => setSelected(allSelected ? new Set() : new Set(items.map((r) => r.id)))} />
                        </th>
                        <th>Player</th>
                        {show('account') ? <th className="hide-phone">Account</th> : null}
                        {show('job') ? <th>Job</th> : null}
                        {show('gang') ? <th className="hide-phone">Gang</th> : null}
                        {show('coins') ? <th className="r">Coins</th> : null}
                        {show('bank') ? <th className="r hide-phone">Bank</th> : null}
                        {show('last') ? <th>Last online</th> : null}
                      </tr>
                    </thead>
                    <tbody>
                      {d.items.map((p) => (
                        <tr key={p.id} className={`click ${selected.has(p.id) || peekId === p.id ? 'sel' : ''}`} onClick={() => (window.innerWidth < 900 ? navigate(`/players/${p.id}`) : setPeekId(p.id))}>
                          <td className="cb" onClick={(e) => e.stopPropagation()}>
                            <input
                              type="checkbox"
                              aria-label={`Select ${p.username}`}
                              checked={selected.has(p.id)}
                              onChange={() =>
                                setSelected((s) => {
                                  const n = new Set(s);
                                  if (n.has(p.id)) n.delete(p.id);
                                  else n.add(p.id);
                                  return n;
                                })
                              }
                            />
                          </td>
                          <td>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                              <Link to={`/players/${p.id}`} onClick={(e) => e.stopPropagation()} style={{ display: 'inline-flex', alignItems: 'center', gap: 8, color: 'var(--ink)', fontWeight: 500 }}>
                                <Avatar look={p.look} size={30} />
                                {p.username}
                              </Link>
                              {p.flag ? <Pill tone={p.flag.tone}>{p.flag.label}</Pill> : null}
                            </span>
                          </td>
                          {show('account') ? (
                            <td className="hide-phone">
                              <span className="mono">#{p.account}</span> {p.siblings > 0 ? <Pill>+{p.siblings}</Pill> : null}
                            </td>
                          ) : null}
                          {show('job') ? (
                            <td>
                              {p.job ? (
                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                  <span className={`dot ${p.job.domain}`} />
                                  {[p.job.rank, p.job.corporation.acronym || p.job.corporation.name].filter(Boolean).join(' · ')}
                                </span>
                              ) : (
                                <span className="sub">Unemployed</span>
                              )}
                            </td>
                          ) : null}
                          {show('gang') ? <td className="hide-phone">{p.gang ? p.gang.name : <span className="sub">None</span>}</td> : null}
                          {show('coins') ? (
                            <td className="r">
                              <span className="num">{fmtNumber(p.coins)}</span> <Coin />
                            </td>
                          ) : null}
                          {show('bank') ? <td className="r num hide-phone">{p.bank === null ? '—' : fmtNumber(p.bank)}</td> : null}
                          {show('last') ? (
                            <td>
                              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <span className={`dot ${p.online ? 'ok' : ''}`} />
                                {p.online ? 'Online' : relTime(p.last_online)}
                              </span>
                            </td>
                          ) : null}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="pager">
                  <span>
                    {selected.size > 0 ? (
                      <>
                        {selected.size} selected ·{' '}
                        {able('command_alert_user') ? (
                          <button type="button" className="btn quiet sm" onClick={() => setBulk('alert')}>
                            Alert
                          </button>
                        ) : null}
                        {can('manage_bans') ? (
                          <button type="button" className="btn quiet sm" onClick={() => setBulk('note')}>
                            Add note
                          </button>
                        ) : null}
                      </>
                    ) : (
                      `${fmtNumber((d.page - 1) * d.per_page + 1)} to ${fmtNumber(Math.min(d.total, d.page * d.per_page))} of ${fmtNumber(d.total)}`
                    )}
                  </span>
                  <div className="actions">
                    <Btn sm disabled={d.page <= 1} onClick={() => setParams((p) => { const n = new URLSearchParams(p); n.set('page', String(d.page - 1)); return n; })}>
                      Previous
                    </Btn>
                    <Btn sm disabled={d.page >= d.last_page} onClick={() => setParams((p) => { const n = new URLSearchParams(p); n.set('page', String(d.page + 1)); return n; })}>
                      Next
                    </Btn>
                  </div>
                </div>
              </>
            )}
          </Loadable>
        </section>
        {peekId ? <PeekPanel id={peekId} onClose={() => setPeekId(null)} /> : null}
      </div>
      {bulk === 'alert' ? (
        <ActionDialog
          title={`Alert ${selected.size} ${selected.size === 1 ? 'player' : 'players'}`}
          wp="live"
          cta="Send alert"
          reason={false}
          note="Only players who are online receive it."
          valid={text.trim().length >= 2}
          onClose={() => { setBulk(null); setText(''); }}
          onSubmit={async () => {
            const r = await post<{ message: string }>('/players/alert', { message: text, ids: [...selected] });
            toast('ok', r.message);
          }}
        >
          <Field label="Message">
            <textarea className="input" rows={3} maxLength={500} autoFocus value={text} onChange={(e) => setText(e.target.value)} />
          </Field>
        </ActionDialog>
      ) : null}
      {bulk === 'note' ? (
        <ActionDialog
          title={`Add a note to ${selected.size} ${selected.size === 1 ? 'player' : 'players'}`}
          wp="saved"
          cta="Save note"
          reason={false}
          valid={text.trim().length >= 3}
          onClose={() => { setBulk(null); setText(''); }}
          onSubmit={async () => {
            const [first, ...rest] = [...selected];
            const r = await post<{ message: string }>(`/players/${first}/notes`, { body: text, ids: rest });
            toast('ok', r.message);
          }}
        >
          <Field label="Note" hint="Only staff see notes. Each player's account gets a copy.">
            <textarea className="input" rows={3} maxLength={2000} autoFocus value={text} onChange={(e) => setText(e.target.value)} placeholder="What happened, and what did you tell them?" />
          </Field>
        </ActionDialog>
      ) : null}
    </main>
  );
}

function PeekPanel({ id, onClose }: { id: number; onClose: () => void }) {
  const state = useFetch<Peek>(`/players/${id}/peek`);
  const navigate = useNavigate();
  const d = state.data;
  const tone = (v: number, max: number) => (v / max >= 0.6 ? 'ok' : v / max >= 0.3 ? 'warn' : 'bad');
  const domainPill = useMemo(() => (d?.job ? { police: 'info', medical: 'ok', corp: 'acc', gang: 'brand', staff: 'brand' }[d.job.domain] : ''), [d]);

  return (
    <aside className="peek side-col" aria-label="Player preview">
      {!d ? (
        <Loadable state={state}>{() => null}</Loadable>
      ) : (
        <>
          <div style={{ display: 'flex', gap: 14, padding: 20, borderBottom: '1px solid var(--line)' }}>
            <Avatar look={d.look} size={80} />
            <div className="grow" style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                <h3 style={{ fontSize: 17, lineHeight: '24px', fontWeight: 600 }}>{d.username}</h3>
                <Btn variant="quiet" sm iconOnly icon="close" aria-label="Close preview" onClick={onClose} />
              </div>
              <span className="status">
                <span className={`dot ${d.online ? 'ok' : ''}`} />
                {d.online ? 'Online' : 'Offline'}
              </span>
              <div className="actions" style={{ gap: 6 }}>
                {d.job ? <Pill tone={domainPill as Tone}>{d.job.corporation.name}</Pill> : null}
                {d.vip ? <Pill tone="acc">VIP</Pill> : null}
              </div>
            </div>
          </div>
          <div style={{ padding: '16px 20px' }} className="stack16">
            <Kv
              rows={[
                ['Account', <span className="mono" key="a">#{d.account} · {d.characters} {d.characters === 1 ? 'character' : 'characters'}</span>],
                ['Job', d.job ? [d.job.rank, d.job.corporation.name].filter(Boolean).join(', ') : <span className="sub" key="j">Unemployed</span>],
                ...(d.job ? ([['Shift this week', <span className="num" key="s">{Math.floor(d.job.shift_week / 3600)}h {Math.floor((d.job.shift_week % 3600) / 60)}m</span>]] as [string, React.ReactNode][]) : []),
                ['Gang', d.gang ? `${d.gang.name} · ${d.gang.role}` : <span className="sub" key="g">None</span>],
                ['Coins', <span key="c"><span className="num">{fmtNumber(d.money.coins)}</span> <Coin /></span>],
                ['Bank', <span className="num" key="b">{d.money.current === null ? '—' : fmtNumber((d.money.current ?? 0) + (d.money.savings ?? 0))}</span>],
              ]}
            />
            {d.stats ? (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 12 }}>
                <div className="stack16" style={{ gap: 4 }}>
                  <span className="sub">Health {d.stats.health}</span>
                  <Bar pct={(d.stats.health / d.stats.health_max) * 100} tone={tone(d.stats.health, d.stats.health_max)} width="100%" />
                </div>
                <div className="stack16" style={{ gap: 4 }}>
                  <span className="sub">Energy {d.stats.energy}</span>
                  <Bar pct={(d.stats.energy / d.stats.energy_max) * 100} tone={tone(d.stats.energy, d.stats.energy_max)} width="100%" />
                </div>
              </div>
            ) : null}
            {d.money.online ? (
              <p className="sub" style={{ fontSize: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
                <AsOf online /> Coins and stats are written when they log out
              </p>
            ) : null}
          </div>
          {d.recent.length > 0 ? (
            <div style={{ borderTop: '1px solid var(--line)' }}>
              {d.recent.map((r) => (
                <div key={r.key} className="row">
                  <span className="mono" style={{ width: 60 }}>
                    {relTime(r.at)}
                  </span>
                  <span className="grow ell">
                    <LogParts parts={r.parts} me={d.id} plain />
                  </span>
                </div>
              ))}
            </div>
          ) : null}
          <div className="card-f" style={{ justifyContent: 'flex-start', flexWrap: 'wrap' }}>
            <Btn variant="primary" onClick={() => navigate(`/players/${d.id}`)}>
              Open Player 360
            </Btn>
            <Btn onClick={() => navigate(`/players/${d.id}?act=alert`)}>Alert</Btn>
            <Btn variant="quiet" onClick={() => navigate(`/players/${d.id}/timeline`)}>
              <Icon name="list" /> Timeline
            </Btn>
          </div>
        </>
      )}
    </aside>
  );
}

