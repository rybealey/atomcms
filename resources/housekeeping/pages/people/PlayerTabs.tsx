import React, { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useSession } from '@hk/lib/session';
import { useFetch, useStream } from '@hk/lib/useFetch';
import { get, patch, post, qs } from '@hk/lib/api';
import { fmtClock, fmtDate, fmtDateTime, fmtDay, fmtDayHead, fmtNumber, fmtResponse, fmtWhen, relTime } from '@hk/lib/format';
import { ActionDialog, AsOf, Avatar, BadgeImg, Btn, Card, Coin, Empty, Field, FilterSelect, Icon, Kind, Loadable, More, Note, Pill, PlayerChip, PlayerRef, RoomChip, SearchInput, Seg, Stat, Tone, Wp, useDebounced } from '@hk/ui';
import { DayGroups, LogPage, LogRow } from '../logs/LogExplorer';
import type { PlayerData } from './Player360';

const RANGES = [
  { value: '24h', label: 'Last 24 hours' },
  { value: '48h', label: 'Last 48 hours' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: 'all', label: 'All time' },
];

/* ---------------- Timeline ---------------- */

export function TimelineTab({ id }: { id: number }) {
  const [kind, setKind] = useState('all');
  const [range, setRange] = useState('48h');
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const [open, setOpen] = useState<string | null>(null);
  const stream = useStream<LogPage & { counts: Record<string, number> | null; kinds: Record<string, string> }, LogRow>(`/players/${id}/timeline${qs({ kind, range, q })}`, (d) => d.rows);
  const [counts, setCounts] = useState<Record<string, number> | null>(null);
  const [kinds, setKinds] = useState<Record<string, string>>({});
  useEffect(() => {
    if (stream.first.data?.counts && kind === 'all') setCounts(stream.first.data.counts);
    if (stream.first.data?.kinds) setKinds(stream.first.data.kinds);
  }, [stream.first.data, kind]);

  return (
    <>
      <div className="filters" style={{ paddingBottom: 16 }}>
        {[['all', 'All'], ...Object.entries(kinds)].map(([k, label]) => (
          <button key={k} type="button" className={`select ${kind === k ? 'on' : ''}`} aria-pressed={kind === k} onClick={() => setKind(k)}>
            {label}{' '}
            <span className="mono" style={{ fontSize: 11 }}>
              {counts ? (counts[k] ?? 0) >= 500 ? '500+' : counts[k] ?? 0 : ''}
            </span>
          </button>
        ))}
        <span className="grow" />
        <FilterSelect label="Range" value={range} options={RANGES} anyLabel="Last 48 hours" onChange={(v) => setRange(v || '48h')} />
        <SearchInput value={text} onChange={setText} placeholder="Search text" width={220} />
      </div>
      <section className="card">
        <Loadable state={stream.first} rows={8} empty={() => (stream.rows.length === 0 ? <Empty title="Nothing in this range" body="Try a longer range or another kind." /> : false)}>
          {() => (
            <>
              <DayGroups rows={stream.rows} me={id} open={open} setOpen={setOpen} />
              <More onClick={stream.more} loading={stream.loadingMore} done={stream.next === null} />
            </>
          )}
        </Loadable>
      </section>
    </>
  );
}

/* ---------------- Money ---------------- */

interface MoneyData {
  balances: { coins: number; diamonds: number; current: number | null; savings: number | null; interest_total: number; online: boolean };
  net: { in: number; out: number };
  flows: { label: string; amount: number }[];
  ledger: LogPage;
  payments: ({ kind: 'order'; id: number; at: number; diamonds: number; cents: number; currency: string; status: string } | { kind: 'store'; id: number; at: number; diamonds: number; item: string })[] | null;
  payments_rank: number;
  deposit_box: { item: string; count: number }[];
}

const BOOK_TONE: Record<string, Tone> = { Coins: 'acc', Current: 'info', Savings: 'info', Transfer: 'brand', Trade: '' };

export function MoneyTab({ id, data, onChanged }: { id: number; data: PlayerData; onChanged: () => void }) {
  const [book, setBook] = useState<'all' | 'coins' | 'bank' | 'transfers' | 'trades'>('all');
  const [range, setRange] = useState('7d');
  const [adjusting, setAdjusting] = useState(false);
  const state = useFetch<MoneyData>(`/players/${id}/money${qs({ book, range })}`);
  const first = state.data;
  const [extra, setExtra] = useState<LogRow[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  useEffect(() => {
    setExtra([]);
    setCursor(first?.ledger.next ?? null);
  }, [first]);
  const rows = [...(first?.ledger.rows ?? []), ...extra];

  const more = async () => {
    if (!cursor) return;
    setLoadingMore(true);
    try {
      const r = await get<{ ledger: LogPage }>(`/players/${id}/money${qs({ book, range, cursor })}`);
      setExtra((e) => [...e, ...r.ledger.rows]);
      setCursor(r.ledger.next);
    } finally {
      setLoadingMore(false);
    }
  };
  const maxFlow = Math.max(1, ...(first?.flows ?? []).map((f) => Math.abs(f.amount)));
  const net = first ? first.net.in - first.net.out : 0;

  return (
    <div className="stack">
      <section className="card stats">
        <Stat label={<><Coin /> Coins</>} value={first ? fmtNumber(first.balances.coins) : '—'} sub={first?.balances.online ? <AsOf online /> : 'As stored'} />
        <Stat label="Current account" value={first?.balances.current === null || !first ? '—' : fmtNumber(first.balances.current)} sub="Live from the bank" />
        <Stat label="Savings" value={first?.balances.savings === null || !first ? '—' : fmtNumber(first.balances.savings)} sub={first ? `${fmtNumber(first.balances.interest_total)} interest earned` : undefined} />
        <Stat label={<><Coin type="diamonds" /> Diamonds</>} value={first ? fmtNumber(first.balances.diamonds) : '—'} sub={first?.payments ? `${first.payments.filter((p) => p.kind === 'order').length} purchases` : undefined} />
        <Stat label="Net, last 7 days" value={first ? `${net >= 0 ? '+' : '−'}${fmtNumber(Math.abs(net))}` : '—'} tone={net >= 0 ? 'pos' : 'neg'} sub={first ? `In ${fmtNumber(first.net.in)} · out ${fmtNumber(first.net.out)}` : undefined} />
      </section>
      <div className="split">
        <section className="card main-col">
          <div className="card-h">
            <Seg
              label="Ledger source"
              value={book}
              onChange={setBook}
              options={[
                { value: 'all', label: 'All' },
                { value: 'coins', label: 'Coins' },
                { value: 'bank', label: 'Bank' },
                { value: 'transfers', label: 'Transfers' },
                { value: 'trades', label: 'Trades' },
              ]}
            />
            <div className="actions">
              <FilterSelect label="Range" value={range} options={RANGES} anyLabel="Last 7 days" onChange={(v) => setRange(v || '7d')} />
              {data.can.give_coins ? (
                <Btn sm wp={data.player.online ? 'live' : 'saved'} onClick={() => setAdjusting(true)}>
                  Adjust coins
                </Btn>
              ) : null}
              <Btn sm wp="game" disabled title="Moving money between accounts happens in the City Panel">
                Move between accounts
              </Btn>
            </div>
          </div>
          <Loadable state={state} rows={8} empty={() => (rows.length === 0 ? <Empty title="No money moved in this range" /> : false)}>
            {() => (
              <>
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th>Time</th>
                        <th>Ledger</th>
                        <th>What</th>
                        <th>Counterparty</th>
                        <th className="r">Amount</th>
                        <th className="r">Balance after</th>
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r) => {
                        const l = r.ledger;
                        if (!l) return null;
                        return (
                          <tr key={r.key}>
                            <td className="mono">{fmtDateTime(r.at)}</td>
                            <td>
                              <Pill tone={BOOK_TONE[l.book] ?? ''}>{l.book}</Pill>
                            </td>
                            <td className="wrap" style={{ minWidth: 200 }}>{l.what}</td>
                            <td>{l.other ? <PlayerChip player={l.other} /> : null}</td>
                            <td className="r">
                              {l.amount !== null ? (
                                <span className={`num ${l.amount > 0 ? 'pos' : l.amount < 0 ? 'neg' : ''}`}>
                                  {l.amount > 0 ? '+' : l.amount < 0 ? '−' : ''}
                                  {fmtNumber(Math.abs(l.amount))}
                                </span>
                              ) : (
                                <span className="sub">Items</span>
                              )}
                            </td>
                            <td className="r num" style={{ color: 'var(--muted)' }}>
                              {l.after === null ? '' : fmtNumber(l.after)}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
                <div className="pager">
                  <span>Merged from rp_coin_ledger, rp_bank_transactions, rp_pay_transfers and rp_trades</span>
                  {cursor ? (
                    <Btn sm onClick={more} disabled={loadingMore}>
                      Load more
                    </Btn>
                  ) : null}
                </div>
              </>
            )}
          </Loadable>
        </section>
        <div className="side-col stack">
          <Card title="Where it came from" sub="Last 7 days">
            <Loadable state={state} empty={(d) => (d.flows.length === 0 ? <Empty title="No coins moved this week" /> : false)}>
              {(d) => (
                <div className="card-b stack16" style={{ gap: 12 }}>
                  {d.flows.map((f) => (
                    <div key={f.label} className="stack16" style={{ gap: 5 }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                        <span>{f.label}</span>
                        <span className={`num ${f.amount > 0 ? 'pos' : 'neg'}`}>
                          {f.amount > 0 ? '+' : '−'}
                          {fmtNumber(Math.abs(f.amount))}
                        </span>
                      </div>
                      <div className={`bar ${f.amount > 0 ? 'ok' : 'bad'}`}>
                        <i style={{ width: `${Math.round((Math.abs(f.amount) / maxFlow) * 100)}%` }} />
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </Loadable>
          </Card>
          {first?.payments ? (
            <Card title="Diamond purchases" action={first.payments_rank ? <Pill>Rank {first.payments_rank}+</Pill> : null}>
              {first.payments.length === 0 ? <Empty title="No purchases" /> : null}
              {first.payments.map((p) => (
                <div key={`${p.kind}-${p.id}`} className="row" title={p.kind === 'order' ? `Order #${p.id} · ${p.status}` : undefined}>
                  <span className="mono" style={{ width: 52 }}>
                    {fmtDay(p.at)}
                  </span>
                  {p.kind === 'order' ? (
                    <>
                      <span className="grow">
                        <span className="num">{fmtNumber(p.diamonds)}</span> diamonds {p.status !== 'paid' ? <Pill tone="warn">{p.status}</Pill> : null}
                      </span>
                      <span className="num">
                        {(p.cents / 100).toLocaleString('en-US', { style: 'currency', currency: p.currency || 'USD' })}
                      </span>
                    </>
                  ) : (
                    <>
                      <span className="grow ell">{p.item}</span>
                      <span className="num neg">−{fmtNumber(Math.abs(p.diamonds))}</span>
                    </>
                  )}
                </div>
              ))}
              <div className="row">
                <span className="sub">From website_diamond_orders and diamonds_store_purchases</span>
              </div>
            </Card>
          ) : null}
          <DepositBox items={first?.deposit_box} />
        </div>
      </div>
      {adjusting ? (
        <AdjustCoins
          data={data}
          onClose={() => setAdjusting(false)}
          onDone={() => {
            onChanged();
            state.reload();
          }}
        />
      ) : null}
    </div>
  );
}

function AdjustCoins({ data, onClose, onDone }: { data: PlayerData; onClose: () => void; onDone: () => void }) {
  const { toast } = useSession();
  const [amount, setAmount] = useState('');
  const n = Number(amount);
  const p = data.player;
  return (
    <ActionDialog
      title={`Adjust ${p.username}'s coins`}
      wp={p.online ? 'live' : 'saved'}
      cta={n < 0 ? `Take ${fmtNumber(Math.abs(n))} coins` : `Give ${fmtNumber(n || 0)} coins`}
      valid={Number.isInteger(n) && n !== 0}
      note={p.online ? 'Sent to the hotel through RCON and logged with your name and reason.' : `${p.username} is offline: written to their account for their next login.`}
      onClose={onClose}
      onSubmit={async (reason) => {
        const r = await post<{ message: string }>(`/players/${p.id}/coins`, { amount: n, reason });
        toast('ok', r.message);
        onDone();
      }}
    >
      <Field label="Amount" hint="A negative amount takes coins.">
        <span className="input">
          <Coin />
          <input className="mono" inputMode="numeric" value={amount} onChange={(e) => setAmount(e.target.value.replace(/[^\d-]/g, ''))} aria-label="Amount" autoFocus />
        </span>
      </Field>
    </ActionDialog>
  );
}

function DepositBox({ items }: { items?: { item: string; count: number }[] }) {
  return (
    <Card title="Deposit box" sub="Items stored at the bank" action={<Wp path="game" />}>
      {!items ? null : items.length === 0 ? (
        <Empty title="Empty" />
      ) : (
        items.map((b) => (
          <div key={b.item} className="row">
            <span className="grow">{b.item}</span>
            <span className="num">×{b.count}</span>
          </div>
        ))
      )}
    </Card>
  );
}

/* ---------------- Justice ---------------- */

interface JusticeData {
  summary: { open_charges: number; times_jailed: number; arrests_30d: number; calls_made: number; calls_answered: number; avg_response: number | null; last_jailed: number | null; last_call: number | null };
  charges: { id: number; at: number; crime: string; severity: number; officer: PlayerRef | null; outcome: { label: string; tone: Tone } }[];
  jail: { id: number; at: number; sentence: number; served: number; released_at: number; officer: PlayerRef | null; room: { id: number; name: string }; charges: string[]; trial: string | null }[];
  arrests: { id: number; at: number; suspect: PlayerRef | null; charges: string[]; sentence: number; room: { id: number; name: string } }[];
  calls: { made: Call[]; answered: Call[] };
}
interface Call { id: number; at: number; message: string; room: { id: number; name: string }; other: PlayerRef | null; response: number | null }

export function severity(n: number): { label: string; tone: Tone } {
  if (n >= 4) return { label: `Severe · ${n}★`, tone: 'bad' };
  if (n === 3) return { label: `Moderate · ${n}★`, tone: 'warn' };
  return { label: `Minor · ${n}★`, tone: '' };
}
const mins = (s: number) => `${Math.max(1, Math.round(s / 60))}m`;

export function JusticeTab({ id, name }: { id: number; name: string }) {
  const state = useFetch<JusticeData>(`/players/${id}/justice`);
  const [calls, setCalls] = useState<'made' | 'answered'>('answered');
  const s = state.data?.summary;
  return (
    <div className="stack">
      <section className="card stats">
        <Stat label="Open charges" value={s ? s.open_charges : '—'} sub={s ? (s.open_charges ? 'Wanted' : 'Nothing outstanding') : undefined} />
        <Stat label="Times jailed" value={s ? s.times_jailed : '—'} sub={s?.last_jailed ? `Last on ${fmtDay(s.last_jailed)}` : undefined} />
        <Stat dot="police" label="Arrests made" value={s ? s.arrests_30d : '—'} sub="As an officer · last 30 days" />
        <Stat label="911 calls made" value={s ? s.calls_made : '—'} sub={s?.last_call ? `Last ${relTime(s.last_call)}` : undefined} />
        <Stat label="911 calls answered" value={s ? s.calls_answered : '—'} sub={s?.avg_response ? `Average response ${fmtResponse(s.avg_response)}` : undefined} />
      </section>
      <div className="cols">
        <Card title="Charge history" sub={`Charges filed against ${name}`} action={<Wp path="game" label="Clear in game" />}>
          <Loadable state={state} empty={(d) => (d.charges.length === 0 ? <Empty title="No charges" /> : false)}>
            {(d) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Charge</th>
                      <th>Severity</th>
                      <th>Officer</th>
                      <th>Outcome</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.charges.map((c) => (
                      <tr key={c.id}>
                        <td className="mono">{fmtDay(c.at)}</td>
                        <td style={{ fontWeight: 500 }}>{c.crime}</td>
                        <td>
                          <Pill tone={severity(c.severity).tone}>{severity(c.severity).label}</Pill>
                        </td>
                        <td>{c.officer ? <PlayerChip player={c.officer} /> : <span className="sub">Automatic</span>}</td>
                        <td>
                          <Pill tone={c.outcome.tone}>{c.outcome.label}</Pill>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>
        <Card title="Jail history" sub="From rp_jail">
          <Loadable state={state} empty={(d) => (d.jail.length === 0 ? <Empty title="Never jailed" /> : false)}>
            {(d) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Jailed</th>
                      <th className="r">Sentence</th>
                      <th className="r">Served</th>
                      <th>Arrested by</th>
                      <th>Room</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.jail.map((j) => (
                      <tr key={j.id} title={j.charges.join(', ')}>
                        <td className="mono">{fmtDateTime(j.at)}</td>
                        <td className="r num">{mins(j.sentence)}</td>
                        <td className="r num">
                          {j.released_at ? mins(j.served) : <Pill tone="warn">Serving</Pill>}
                          {j.trial ? <span className="sub"> · {j.trial}</span> : null}
                        </td>
                        <td>{j.officer ? <PlayerChip player={j.officer} /> : null}</td>
                        <td>
                          <RoomChip id={j.room.id} name={j.room.name} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>
      </div>
      <div className="split">
        <Card className="main-col" title={`Arrests made by ${name}`} sub={s ? `${s.arrests_30d} in the last 30 days · for checking officer conduct` : undefined} action={<Link to={`/logs?player=${id}&kind=justice&when=30d`} style={{ fontSize: 13 }}>Open in log explorer</Link>}>
          <Loadable state={state} empty={(d) => (d.arrests.length === 0 ? <Empty title="No arrests in the last 30 days" /> : false)}>
            {(d) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>Suspect</th>
                      <th>Charges</th>
                      <th className="r">Sentence</th>
                      <th>Room</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.arrests.map((a) => (
                      <tr key={a.id}>
                        <td className="mono">{fmtDateTime(a.at)}</td>
                        <td>
                          <PlayerChip player={a.suspect} />
                        </td>
                        <td className="wrap">{a.charges.join(', ') || '—'}</td>
                        <td className="r num">{mins(a.sentence)}</td>
                        <td>
                          <RoomChip id={a.room.id} name={a.room.name} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>
        <Card
          className="side-col"
          title="911 calls"
          action={<Seg label="Calls" value={calls} onChange={setCalls} options={[{ value: 'made', label: `Made ${s?.calls_made ?? ''}` }, { value: 'answered', label: `Answered ${s?.calls_answered ?? ''}` }]} />}
        >
          <Loadable state={state} empty={(d) => (d.calls[calls].length === 0 ? <Empty title={calls === 'made' ? 'No calls made' : 'No calls answered'} /> : false)}>
            {(d) =>
              d.calls[calls].map((c) => (
                <div key={c.id} className="row">
                  <span className="mono" style={{ width: 56 }}>
                    {fmtWhen(c.at)}
                  </span>
                  <div className="grow">
                    <p className="ell">{c.message}</p>
                    <p className="sub ell">
                      {c.other ? c.other.username : calls === 'made' ? 'No responder' : ''} · {c.room.name}
                    </p>
                  </div>
                  <span className="num">{fmtResponse(c.response)}</span>
                </div>
              ))
            }
          </Loadable>
        </Card>
      </div>
    </div>
  );
}

/* ---------------- Chat ---------------- */

interface Convos { player: { id: number; username: string }; rooms: { key: string; room: { id: number; name: string }; said: number; first_at: number; last_at: number; filtered: number }[]; private: { key: string; with: PlayerRef; messages: number; last_at: number }[] }
interface Transcript { title: string; sub: string; source: string; lines: { id: number; at: number; player: PlayerRef; text: string; filtered: string | null }[]; before: number | null }

export function ChatTab({ id, data }: { id: number; data: PlayerData }) {
  const [range, setRange] = useState('48h');
  const [show, setShow] = useState<'' | 'room' | 'private'>('');
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const convos = useFetch<Convos>(`/players/${id}/chat${qs({ range, show, q })}`);
  const [sel, setSel] = useState<string | null>(null);
  const [muting, setMuting] = useState(false);
  const first = convos.data ? convos.data.rooms[0]?.key ?? convos.data.private[0]?.key ?? null : null;
  const current = sel ?? first;
  const roomId = current?.startsWith('room-') ? Number(current.slice(5)) : null;
  const withId = current?.startsWith('dm-') ? Number(current.slice(3)) : null;
  const [older, setOlder] = useState<Transcript['lines']>([]);
  const [before, setBefore] = useState<number | null>(null);
  const transcript = useFetch<Transcript>(current ? `/players/${id}/chat/transcript${qs({ room: roomId, with: withId, range })}` : null);
  const earlier = useFetch<Transcript>(current && before ? `/players/${id}/chat/transcript${qs({ room: roomId, with: withId, range, before })}` : null);
  useEffect(() => {
    setOlder([]);
    setBefore(null);
  }, [current, range]);
  useEffect(() => {
    if (earlier.data) setOlder((o) => [...earlier.data!.lines, ...o]);
  }, [earlier.data]);
  const t = transcript.data;
  const lines = [...older, ...(t?.lines ?? [])];
  const nextBefore = before ? earlier.data?.before ?? null : t?.before ?? null;
  const p = data.player;

  const exportCsv = () => {
    const rows = [['time', 'player', 'message'], ...lines.map((l) => [new Date(l.at * 1000).toISOString(), l.player.username, l.text])];
    const blob = new Blob([rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `chat-${p.username}-${current}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  };

  let day = '';
  return (
    <>
      <div className="filters" style={{ paddingBottom: 16 }}>
        <SearchInput value={text} onChange={setText} placeholder={`Search ${p.username}’s messages`} width={300} />
        <FilterSelect label="Range" value={range} options={RANGES} anyLabel="Last 48 hours" onChange={(v) => setRange(v || '48h')} />
        <FilterSelect label="Show" value={show} anyLabel="Room and private" options={[{ value: 'room', label: 'Room chat' }, { value: 'private', label: 'Private chat' }]} onChange={setShow} />
        <span className="grow" />
        <Btn onClick={exportCsv} disabled={!lines.length}>
          Export
        </Btn>
        {data.can.mute ? (
          <Btn wp="saved" onClick={() => setMuting(true)} disabled={p.online} title={p.online ? 'Online: mute in game with :mute' : undefined}>
            {p.flags.muted ? 'Change mute' : `Mute ${p.username}`}
          </Btn>
        ) : null}
      </div>
      <div className="split" style={{ paddingBottom: 24 }}>
        <section className="card" style={{ flex: '1 1 300px' }} aria-label="Conversations">
          <Loadable state={convos} empty={(c) => (c.rooms.length === 0 && c.private.length === 0 ? <Empty title="No chat in this range" /> : false)}>
            {(c) => (
              <>
                {c.rooms.length ? <div className="dayhead">Room chat</div> : null}
                {c.rooms.map((r) => (
                  <button key={r.key} type="button" className={`row ${current === r.key ? 'on' : ''}`} aria-pressed={current === r.key} onClick={() => setSel(r.key)}>
                    <Kind icon="message" tone="info" />
                    <span className="grow">
                      <span style={{ display: 'block', fontWeight: 500 }}>{r.room.name}</span>
                      <span className="sub">
                        {p.username} said {r.said} · {relTime(r.last_at)}
                      </span>
                    </span>
                    {r.filtered ? <Pill tone="warn">{r.filtered} filtered</Pill> : null}
                  </button>
                ))}
                {c.private.length ? <div className="dayhead">Private chat</div> : null}
                {c.private.map((m) => (
                  <button key={m.key} type="button" className={`row ${current === m.key ? 'on' : ''}`} aria-pressed={current === m.key} onClick={() => setSel(m.key)}>
                    <Avatar look={m.with.look} size={30} />
                    <span className="grow">
                      <span style={{ display: 'block', fontWeight: 500 }}>{m.with.username}</span>
                      <span className="sub">
                        {m.messages} messages · {relTime(m.last_at)}
                      </span>
                    </span>
                  </button>
                ))}
              </>
            )}
          </Loadable>
        </section>
        <section className="card main-col" aria-label="Transcript">
          {!current ? (
            <Empty title="Pick a conversation" />
          ) : (
            <>
              <div className="card-h">
                <div>
                  <h3>{t?.title ?? '…'}</h3>
                  <p className="sub">{t?.sub}</p>
                </div>
                <span className="mono" style={{ fontSize: 11 }}>
                  {t?.source}
                </span>
              </div>
              {nextBefore ? (
                <div className="pager" style={{ justifyContent: 'center', borderTop: 0, borderBottom: '1px solid var(--line)' }}>
                  <Btn sm onClick={() => setBefore(nextBefore)} disabled={earlier.loading}>
                    Load earlier
                  </Btn>
                </div>
              ) : null}
              <Loadable state={transcript} rows={8} empty={() => (lines.length === 0 ? <Empty title="No lines" /> : false)}>
                {() =>
                  lines.map((m) => {
                    const head = fmtDayHead(m.at);
                    const showHead = head !== day;
                    day = head;
                    const mine = m.player.id === id;
                    return (
                      <React.Fragment key={m.id}>
                        {showHead ? <div className="dayhead">{head}</div> : null}
                        <div style={{ display: 'grid', gridTemplateColumns: '68px 120px minmax(0, 1fr)', gap: 12, alignItems: 'baseline', padding: '8px 20px', borderBottom: '1px solid var(--line)', background: mine ? 'var(--sunken)' : 'transparent' }}>
                          <span className="mono">{fmtClock(m.at)}</span>
                          <Link to={`/players/${m.player.id}/chat`} className="ell" style={{ fontWeight: 500, color: mine ? 'var(--brand-ink)' : 'var(--ink)' }}>
                            {m.player.username}
                          </Link>
                          <span style={{ display: 'flex', flexDirection: 'column', gap: 4, overflowWrap: 'anywhere' }}>
                            <span>{m.text}</span>
                            {m.filtered ? (
                              <span style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                <Pill tone="warn">Word filter</Pill>
                                <span className="sub">Matches “{m.filtered}”</span>
                              </span>
                            ) : null}
                          </span>
                        </div>
                      </React.Fragment>
                    );
                  })
                }
              </Loadable>
              <div className="row">
                <span className="sub">Lines from {p.username} are highlighted · the other side of private chats is included</span>
              </div>
            </>
          )}
        </section>
      </div>
      {muting ? <MuteDialog data={data} onClose={() => setMuting(false)} /> : null}
    </>
  );
}

export const LENGTHS = [
  { value: '600', label: '10 minutes' },
  { value: '3600', label: '1 hour' },
  { value: '7200', label: '2 hours' },
  { value: '86400', label: '1 day' },
  { value: '259200', label: '3 days' },
  { value: '604800', label: '7 days' },
  { value: '2592000', label: '30 days' },
  { value: '31536000', label: '1 year' },
  { value: '315360000', label: 'Permanent (10 years)' },
];

export function MuteDialog({ data, onClose, onDone }: { data: PlayerData; onClose: () => void; onDone?: () => void }) {
  const { toast } = useSession();
  const [seconds, setSeconds] = useState(data.player.flags.muted ? '0' : '3600');
  const p = data.player;
  return (
    <ActionDialog
      title={`Mute ${p.username}`}
      wp="saved"
      cta={seconds === '0' ? 'Lift mute' : 'Mute'}
      note={`${p.username} is offline, so the mute is written to their account and starts at their next login.`}
      onClose={onClose}
      onSubmit={async (reason) => {
        const r = await post<{ message: string }>(`/players/${p.id}/mute`, { seconds: Number(seconds), reason });
        toast('ok', r.message);
        onDone?.();
      }}
    >
      <Field label="Length">
        <select className="input" value={seconds} onChange={(e) => setSeconds(e.target.value)}>
          {data.player.flags.muted ? <option value="0">Lift the mute</option> : null}
          {LENGTHS.slice(0, 7).map((l) => (
            <option key={l.value} value={l.value}>
              {l.label}
            </option>
          ))}
        </select>
      </Field>
    </ActionDialog>
  );
}

/* ---------------- Security ---------------- */

interface SecurityData {
  flags: { id: number; player: PlayerRef | null; reason: string; detail: string; at: number }[];
  linked: { player: PlayerRef | null; how: string; status: 'same' | 'flagged' | 'household' | 'dismissed'; account: number; last_seen?: number | null }[];
  devices: { device: string; label: string; last_seen: number | null; accounts: number }[];
  logins: { ip: string; device: string; ok: boolean; method: string; at: number | null; country: string | null }[];
  ips: { ip: string; country: string | null; first_seen: number | null; last_seen: number | null; visits: number; others: number }[];
}
const LINK_STATUS: Record<string, { label: string; tone: Tone }> = { same: { label: 'Same account', tone: '' }, flagged: { label: 'Flagged', tone: 'warn' }, household: { label: 'Household', tone: 'ok' }, dismissed: { label: 'Dismissed', tone: '' } };

export function SecurityTab({ id, name }: { id: number; name: string }) {
  const { toast } = useSession();
  const state = useFetch<SecurityData>(`/players/${id}/security`);
  const d = state.data;
  const flaggedLink = d?.linked.find((l) => l.status === 'flagged');
  const review = async (other: number, status: 'household' | 'dismissed' | 'flagged') => {
    const r = await post<{ message: string }>(`/players/${id}/links`, { other, status });
    toast('ok', r.message);
    state.reload();
  };

  return (
    <div className="stack">
      {d && (d.flags.length > 0 || flaggedLink) ? (
        <section className="card" style={{ borderColor: 'var(--warn)' }}>
          <div className="row" style={{ alignItems: 'flex-start', padding: '16px 20px' }}>
            <Kind icon="shield-alert" tone="warn" />
            <div className="grow stack16" style={{ gap: 6 }}>
              <div className="actions" style={{ gap: 8 }}>
                <span style={{ fontWeight: 600 }}>{flaggedLink ? 'Shares an IP or device with another account' : `Browser guard: ${d.flags[0].reason.toLowerCase()}`}</span>
                <Pill tone="warn">Needs review</Pill>
              </div>
              <p className="sub">
                {flaggedLink && flaggedLink.player ? (
                  <>
                    {name} and <Link to={`/players/${flaggedLink.player.id}/security`}>{flaggedLink.player.username}</Link> (account <span className="mono">#{flaggedLink.account}</span>): {flaggedLink.how}
                    {flaggedLink.last_seen ? `, last together ${relTime(flaggedLink.last_seen)}` : ''}.
                  </>
                ) : (
                  <>
                    {d.flags[0].detail || 'No detail recorded'} · {relTime(d.flags[0].at)}
                  </>
                )}
              </p>
            </div>
            {flaggedLink?.player ? (
              <div className="actions">
                <Btn sm wp="saved" onClick={() => review(flaggedLink.player!.id, 'household')}>
                  Mark as household
                </Btn>
                <Btn sm wp="saved" onClick={() => review(flaggedLink.player!.id, 'dismissed')}>
                  Dismiss
                </Btn>
                <Btn sm to={`/players/${id}/discipline`}>
                  Open discipline
                </Btn>
              </div>
            ) : null}
          </div>
        </section>
      ) : null}
      <div className="cols">
        <Card title="Linked accounts" sub="Other accounts sharing an IP or machine">
          <Loadable state={state} empty={(x) => (x.linked.length === 0 ? <Empty title="No other accounts" body="Nobody else has played from this account's IPs or devices." /> : false)}>
            {(x) =>
              x.linked.map((l, i) => (
                <div key={i} className="row">
                  <PlayerChip player={l.player} />
                  <p className="sub grow ell">{l.how}</p>
                  <Pill tone={LINK_STATUS[l.status].tone}>{LINK_STATUS[l.status].label}</Pill>
                  {l.status !== 'same' && l.player ? (
                    <Btn variant="quiet" sm onClick={() => review(l.player!.id, l.status === 'flagged' ? 'household' : 'flagged')}>
                      {l.status === 'flagged' ? 'Household' : 'Flag again'}
                    </Btn>
                  ) : null}
                </div>
              ))
            }
          </Loadable>
        </Card>
        <Card title="Devices" sub="Devices this account has used">
          <Loadable state={state} empty={(x) => (x.devices.length === 0 ? <Empty title="No devices recorded" /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Device ID</th>
                      <th>Device</th>
                      <th>Last used</th>
                      <th className="r">Accounts</th>
                    </tr>
                  </thead>
                  <tbody>
                    {x.devices.map((dv) => (
                      <tr key={dv.device}>
                        <td className="mono" style={{ color: 'var(--ink)' }}>
                          {dv.device}…
                        </td>
                        <td>{dv.label}</td>
                        <td>{fmtWhen(dv.last_seen)}</td>
                        <td className="r">
                          <Pill tone={dv.accounts > 1 ? 'warn' : ''}>{dv.accounts}</Pill>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>
      </div>
      <div className="cols">
        <Card title="Login history" sub="From website_login_logs">
          <Loadable state={state} empty={(x) => (x.logins.length === 0 ? <Empty title="No website logins recorded" /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>IP</th>
                      <th>Region</th>
                      <th>Device</th>
                      <th>Result</th>
                    </tr>
                  </thead>
                  <tbody>
                    {x.logins.map((g, i) => (
                      <tr key={i}>
                        <td className="mono">{fmtDateTime(g.at)}</td>
                        <td className="mono" style={{ color: 'var(--ink)' }}>
                          {g.ip}
                        </td>
                        <td>{g.country ?? '—'}</td>
                        <td style={{ color: 'var(--muted)' }}>{g.device}</td>
                        <td>
                          <Pill tone={g.ok ? 'ok' : 'bad'}>{g.ok ? 'Success' : 'Failed'}</Pill>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>
        <Card title="IP addresses" sub="Every address seen on this account">
          <Loadable state={state} empty={(x) => (x.ips.length === 0 ? <Empty title="No IPs recorded" /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>IP</th>
                      <th>First seen</th>
                      <th>Last seen</th>
                      <th className="r">Visits</th>
                      <th className="r">Other accounts</th>
                    </tr>
                  </thead>
                  <tbody>
                    {x.ips.map((ip) => (
                      <tr key={ip.ip}>
                        <td className="mono" style={{ color: 'var(--ink)' }}>
                          {ip.ip} {ip.country ? <span className="sub">· {ip.country}</span> : null}
                        </td>
                        <td>{fmtDate(ip.first_seen)}</td>
                        <td>{fmtWhen(ip.last_seen)}</td>
                        <td className="r num">{fmtNumber(ip.visits)}</td>
                        <td className="r">
                          <Link to={`/players?q=${encodeURIComponent(ip.ip)}`}>
                            <Pill tone={ip.others > 0 ? 'warn' : ''}>{ip.others}</Pill>
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
          <div className="row">
            <span className="sub">IPs and devices are visible to ranks with the edit_user capability.</span>
          </div>
        </Card>
      </div>
    </div>
  );
}

/* ---------------- Inventory ---------------- */

interface InventoryData {
  rooms: { id: number; name: string; now: number; placed: number; role: string; state: string }[];
  inventory: { name: string; item: string; function: string | null; overridden: number; count: number }[];
  in_hand: number;
  badges: { code: string; worn: boolean }[];
  deposit_box: { item: string; count: number }[];
}

export function InventoryTab({ id, data }: { id: number; data: PlayerData }) {
  const { toast } = useSession();
  const state = useFetch<InventoryData>(`/players/${id}/inventory`);
  const [giving, setGiving] = useState(false);
  const d = state.data;
  const worn = d?.badges.filter((b) => b.worn).length ?? 0;
  const [taking, setTaking] = useState<string | null>(null);
  return (
    <div className="stack">
      <section className="card stats">
        <Stat label="Rooms owned" value={d ? d.rooms.length : '—'} sub={d ? `${d.rooms.reduce((n, r) => n + r.placed, 0)} furni placed` : undefined} />
        <Stat label="Furni in inventory" value={d ? fmtNumber(d.in_hand) : '—'} sub="Not placed in a room" />
        <Stat label="Badges" value={d ? d.badges.length : '—'} sub={d ? `${worn} worn` : undefined} />
        <Stat label="Deposit box" value={d ? d.deposit_box.reduce((n, b) => n + b.count, 0) : '—'} sub="Items stored at the bank" />
      </section>
      <Card title="Rooms" sub={`Rooms ${data.player.username} owns`}>
        <Loadable state={state} empty={(x) => (x.rooms.length === 0 ? <Empty title="Owns no rooms" /> : false)}>
          {(x) => (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Room</th>
                    <th>ID</th>
                    <th>Role</th>
                    <th className="r">In the room now</th>
                    <th className="r">Furni placed</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {x.rooms.map((r) => (
                    <tr key={r.id}>
                      <td style={{ fontWeight: 500 }}>{r.name}</td>
                      <td className="mono">{r.id}</td>
                      <td>
                        <span className="tag">
                          <span className={`dot ${r.role === 'HQ' ? 'corp' : r.role === 'Turf' ? 'gang' : r.role === 'Jail' ? 'police' : ''}`} />
                          {r.role}
                        </span>
                      </td>
                      <td className="r num">{r.now}</td>
                      <td className="r num">{fmtNumber(r.placed)}</td>
                      <td className="r">
                        <Btn sm to={`/city/rooms?room=${r.id}`}>
                          Open room
                        </Btn>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Loadable>
      </Card>
      <div className="cols">
        <Card title="Badges" sub="Badge art comes from the game" action={data.can.give_badge ? <Btn sm wp={data.player.online ? 'live' : 'saved'} onClick={() => setGiving(true)}>Give badge</Btn> : null}>
          <Loadable state={state} empty={(x) => (x.badges.length === 0 ? <Empty title="No badges" /> : false)}>
            {(x) => (
              <div className="card-b" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(88px, 1fr))', gap: 10 }}>
                {x.badges.map((b) => (
                  <div key={b.code} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, padding: '10px 6px', border: '1px solid var(--line)', borderRadius: 10, background: b.worn ? 'var(--brand-soft)' : 'var(--surface)', position: 'relative' }}>
                    <span style={{ width: 44, height: 44, borderRadius: 8, background: 'var(--tile)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <BadgeImg code={b.code} />
                    </span>
                    <span className="mono ell" style={{ fontSize: 11, maxWidth: '100%' }}>
                      {b.code}
                    </span>
                    {b.worn ? <Pill tone="brand">Worn</Pill> : null}
                    {data.can.give_badge ? (
                      <button type="button" className="btn quiet sm icon" style={{ position: 'absolute', top: 2, right: 2, width: 22, height: 22 }} aria-label={`Take ${b.code}`} onClick={() => setTaking(b.code)}>
                        <Icon name="close" size={12} />
                      </button>
                    ) : null}
                  </div>
                ))}
              </div>
            )}
          </Loadable>
        </Card>
        <div className="stack">
          <Card title="Inventory" sub="Grouped by item · function from the furni definition and rp_item_function">
            <Loadable state={state} empty={(x) => (x.inventory.length === 0 ? <Empty title="Nothing in the inventory" /> : false)}>
              {(x) => (
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>Function</th>
                        <th className="r">Count</th>
                      </tr>
                    </thead>
                    <tbody>
                      {x.inventory.map((it) => (
                        <tr key={it.item}>
                          <td title={it.item}>{it.name}</td>
                          <td>
                            {it.function ? <span className="mono">{it.function}</span> : null}
                            {it.overridden ? <span className="sub"> · {it.overridden} set per item</span> : null}
                          </td>
                          <td className="r num">{fmtNumber(it.count)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Loadable>
          </Card>
          <DepositBox items={d?.deposit_box} />
        </div>
      </div>
      {giving ? <GiveBadge data={data} onClose={() => setGiving(false)} onDone={state.reload} /> : null}
      {taking ? (
        <ActionDialog
          title={`Take the ${taking} badge`}
          wp={data.player.online ? 'live' : 'saved'}
          cta="Take badge"
          danger
          note={`${data.player.username} loses it ${data.player.online ? 'at once' : 'before their next login'}.`}
          onClose={() => setTaking(null)}
          onSubmit={async (reason) => {
            const r = await post<{ message: string }>(`/players/${id}/badges/take`, { badge: taking, reason });
            toast('ok', r.message);
            state.reload();
          }}
        />
      ) : null}
    </div>
  );
}

function GiveBadge({ data, onClose, onDone }: { data: PlayerData; onClose: () => void; onDone: () => void }) {
  const { toast } = useSession();
  const [badge, setBadge] = useState('');
  const p = data.player;
  return (
    <ActionDialog
      title={`Give ${p.username} a badge`}
      wp={p.online ? 'live' : 'saved'}
      cta="Give badge"
      valid={/^[A-Za-z0-9_-]+$/.test(badge)}
      onClose={onClose}
      onSubmit={async (reason) => {
        const r = await post<{ message: string }>(`/players/${p.id}/badges`, { badge, reason });
        toast('ok', r.message);
        onDone();
      }}
    >
      <Field label="Badge code">
        <span className="input">
          {badge ? <BadgeImg code={badge} size={24} /> : null}
          <input className="mono" value={badge} onChange={(e) => setBadge(e.target.value.trim())} maxLength={100} autoFocus placeholder="POL03" aria-label="Badge code" />
        </span>
      </Field>
    </ActionDialog>
  );
}

/* ---------------- Discipline ---------------- */

interface DisciplineData {
  account: number;
  characters: { id: number; username: string; online: boolean }[];
  history: { key: string; id: number; type: 'ban' | 'mute'; scope: string; at: number; character: string; character_id: number | null; staff: string; reason: string; length: number; expires_at: number; active: boolean }[];
  notes: { id: number; body: string; pinned: boolean; author: PlayerRef | null; character: string | null; at: number }[];
  room_bans: { room: { id: number; name: string }; expires_at: number }[];
  muted: { character_id: number; seconds_left: number }[];
  block: { ip: string | null; machine: string | null };
}

function lengthLabel(s: number): string {
  if (s >= 315360000) return 'Permanent';
  if (s >= 86400) return `${Math.round(s / 86400)}d`;
  if (s >= 3600) return `${Math.round(s / 3600)}h`;
  return `${Math.max(1, Math.round(s / 60))}m`;
}

export function DisciplineTab({ id, data, onChanged }: { id: number; data: PlayerData; onChanged: () => void }) {
  const { toast, confirm } = useSession();
  const location = useLocation();
  const state = useFetch<DisciplineData>(`/players/${id}/discipline`);
  const [note, setNote] = useState('');
  const [saving, setSaving] = useState(false);
  const [lifting, setLifting] = useState(false);
  const d = state.data;
  const names = d?.characters.map((c) => c.username) ?? [];
  const active = d?.history.find((h) => h.type === 'ban' && h.active);
  const bans = d?.history.filter((h) => h.type === 'ban') ?? [];
  const mutes = d?.history.filter((h) => h.type === 'mute') ?? [];

  useEffect(() => {
    if (location.hash === '#ban') document.getElementById('ban')?.scrollIntoView({ behavior: 'smooth' });
  }, [location.hash, d]);

  const saveNote = async () => {
    setSaving(true);
    try {
      await post(`/players/${id}/notes`, { body: note });
      setNote('');
      toast('ok', 'Note saved');
      state.reload();
      onChanged();
    } catch (e) {
      toast('bad', 'Could not save the note', e instanceof Error ? e.message : undefined);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="stack">
      <Note tone="info" title={names.length > 1 ? `Discipline covers the whole account. Bans and mutes here apply to ${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}.` : 'Discipline covers the whole account.'} />
      <section className="card stats">
        <Stat label="Ban" word={active ? `Until ${fmtDay(active.expires_at)}` : 'None active'} sub={`${bans.filter((b) => !b.active).length} expired`} />
        <Stat label="Mute" word={d?.muted.length ? `${Math.ceil(d.muted[0].seconds_left / 60)}m left` : 'None active'} sub={`${mutes.length} from the panel`} />
        <Stat label="Staff notes" word={d ? String(d.notes.length) : '—'} sub={d?.notes[0] ? `Last on ${fmtDay(d.notes[0].at)}` : undefined} />
        <Stat label="Room bans" word={d ? String(d.room_bans.length) : '—'} sub="From room_bans" />
      </section>
      <div className="split">
        <div className="main-col stack">
          <Card title="History" sub="Every ban, mute and note on this account">
            <Loadable state={state} empty={(x) => (x.history.length === 0 && x.notes.length === 0 ? <Empty title="A clean record" /> : false)}>
              {(x) => (
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Character</th>
                        <th>Staff</th>
                        <th>Reason</th>
                        <th>Length</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {[
                        ...x.history.map((h) => ({ ...h, sort: h.at })),
                        ...x.notes.map((n) => ({ key: `note-${n.id}`, type: 'note' as const, at: n.at, sort: n.at, character: n.character ?? '', staff: n.author?.username ?? '', reason: n.body, length: 0, active: true, scope: 'user' })),
                      ]
                        .sort((a, b) => b.sort - a.sort)
                        .map((h) => (
                          <tr key={h.key}>
                            <td className="mono">{fmtDate(h.at)}</td>
                            <td>
                              <Pill tone={h.type === 'ban' ? 'bad' : h.type === 'mute' ? 'warn' : ''}>
                                {h.type === 'ban' ? (h.scope === 'user' ? 'Ban' : `${h.scope.toUpperCase()} ban`) : h.type === 'mute' ? 'Mute' : 'Note'}
                              </Pill>
                            </td>
                            <td>{h.character}</td>
                            <td style={{ color: 'var(--muted)' }}>{h.staff}</td>
                            <td className="wrap" style={{ minWidth: 220 }}>
                              {h.reason}
                            </td>
                            <td className="num">{h.type === 'note' ? 'None' : lengthLabel(h.length)}</td>
                            <td>
                              <Pill tone={h.type === 'note' ? 'info' : h.active ? 'bad' : ''}>{h.type === 'note' ? 'Open' : h.active ? 'Active' : 'Expired'}</Pill>
                            </td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Loadable>
          </Card>
          <Card title="Staff notes" sub="Only staff see these">
            {d?.notes.map((n) => (
              <div key={n.id} className="row" style={{ alignItems: 'flex-start' }}>
                <Kind icon="edit" tone={n.pinned ? 'brand' : ''} />
                <div className="grow">
                  <p style={{ whiteSpace: 'pre-wrap' }}>{n.body}</p>
                  <p className="sub">
                    {n.author?.username ?? 'Staff'} · {fmtDate(n.at)}
                    {n.character ? ` · on ${n.character}` : ''}
                  </p>
                </div>
                {data.can.note ? (
                  <span className="actions">
                    <Btn variant="quiet" sm onClick={async () => { await patch(`/players/${id}/notes/${n.id}`, { pinned: !n.pinned }); state.reload(); }}>
                      {n.pinned ? 'Unpin' : 'Pin'}
                    </Btn>
                    <Btn
                      variant="quiet"
                      sm
                      iconOnly
                      icon="trash"
                      aria-label="Delete note"
                      onClick={async () => {
                        if (!(await confirm({ title: 'Delete this note?', body: 'It is kept in the staff audit trail.', cta: 'Delete note', danger: true }))) return;
                        await patch(`/players/${id}/notes/${n.id}`, { delete: true });
                        state.reload();
                        onChanged();
                      }}
                    />
                  </span>
                ) : null}
              </div>
            ))}
            {data.can.note ? (
              <div className="card-b stack16" style={{ gap: 10, borderTop: d?.notes.length ? '1px solid var(--line)' : 0 }}>
                <Field label="Add a note">
                  <textarea className="input" rows={3} value={note} onChange={(e) => setNote(e.target.value)} maxLength={2000} placeholder="What happened, and what did you tell the player?" />
                </Field>
                <div className="actions" style={{ justifyContent: 'flex-end' }}>
                  <Wp path="saved" />
                  <Btn onClick={saveNote} disabled={note.trim().length < 3 || saving}>
                    Save note
                  </Btn>
                </div>
              </div>
            ) : null}
          </Card>
        </div>
        {data.can.ban || data.can.mute ? (
          <div className="side-col stack" id="ban">
            {active && data.can.ban ? (
              <section className="peek">
                <div className="card-h">
                  <h3>Active ban</h3>
                  <Pill tone="bad">Until {fmtDateTime(active.expires_at)}</Pill>
                </div>
                <div className="card-b">
                  <p>{active.reason}</p>
                  <p className="sub">By {active.staff}</p>
                </div>
                <div className="card-f">
                  <Btn variant="danger" wp="saved" onClick={() => setLifting(true)}>
                    Lift ban
                  </Btn>
                </div>
              </section>
            ) : null}
            <BanForm data={data} disc={d ?? null} onDone={() => { state.reload(); onChanged(); }} />
          </div>
        ) : null}
      </div>
      {lifting ? (
        <ActionDialog
          title="Lift the ban"
          wp="saved"
          cta="Lift ban"
          note="The ban stays in the history. The hotel reloads bans through RCON."
          onClose={() => setLifting(false)}
          onSubmit={async (reason) => {
            const r = await post<{ message: string }>(`/players/${id}/unban`, { reason });
            toast('ok', r.message);
            state.reload();
            onChanged();
          }}
        />
      ) : null}
    </div>
  );
}

function BanForm({ data, disc, onDone }: { data: PlayerData; disc: DisciplineData | null; onDone: () => void }) {
  const { toast } = useSession();
  const p = data.player;
  const [mode, setMode] = useState<'ban' | 'mute'>(data.can.ban ? 'ban' : 'mute');
  const [seconds, setSeconds] = useState('259200');
  const [ip, setIp] = useState(false);
  const [machine, setMachine] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const count = disc?.characters.length ?? 1;
  const label = LENGTHS.find((l) => l.value === seconds)?.label ?? '';
  const muteBlocked = mode === 'mute' && p.online;

  const submit = async () => {
    setBusy(true);
    setError(null);
    try {
      const r = mode === 'ban' ? await post<{ message: string }>(`/players/${p.id}/ban`, { seconds: Number(seconds), reason, ip, machine }) : await post<{ message: string }>(`/players/${p.id}/mute`, { seconds: Number(seconds), reason });
      toast('ok', r.message);
      setReason('');
      onDone();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="peek" aria-label="Ban or mute">
      <div className="card-h">
        <h3>Ban or mute</h3>
        <Wp path="saved" label={mode === 'ban' ? 'Saved, then reloaded' : 'Saved'} />
      </div>
      <div className="card-b stack16">
        {data.can.ban && data.can.mute ? (
          <Seg label="Action" value={mode} onChange={setMode} options={[{ value: 'ban', label: 'Ban' }, { value: 'mute', label: 'Mute' }]} />
        ) : null}
        <Field label="Length">
          <select className="input" value={seconds} onChange={(e) => setSeconds(e.target.value)}>
            {LENGTHS.filter((l) => mode === 'ban' || Number(l.value) <= 31536000).map((l) => (
              <option key={l.value} value={l.value}>
                {l.label}
              </option>
            ))}
          </select>
        </Field>
        {mode === 'ban' && (disc?.block.ip || disc?.block.machine) ? (
          <fieldset style={{ border: 0, padding: 0, margin: 0 }} className="stack16">
            <legend style={{ fontSize: 13, fontWeight: 500, padding: 0, marginBottom: 8 }}>Also block</legend>
            {disc.block.ip ? (
              <label className="check">
                <input type="checkbox" checked={ip} onChange={(e) => setIp(e.target.checked)} />
                IP address <span className="mono">{disc.block.ip}</span>
              </label>
            ) : null}
            {disc.block.machine ? (
              <label className="check">
                <input type="checkbox" checked={machine} onChange={(e) => setMachine(e.target.checked)} />
                Machine ID <span className="mono">{disc.block.machine.slice(0, 4)}…{disc.block.machine.slice(-3)}</span>
              </label>
            ) : null}
          </fieldset>
        ) : null}
        <Field label="Reason" optional={mode === 'ban' ? 'required, shown to the player' : 'required'}>
          <textarea className="input" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} placeholder="Explain the rule that was broken" />
        </Field>
        {muteBlocked ? (
          <Note tone="warn" icon="game" title={`${p.username} is online`}>
            The emulator keeps a mute in memory and writes it at logout. Mute them in game with :mute.
          </Note>
        ) : (
          <p className="sub" style={{ fontSize: 12 }}>
            {mode === 'ban'
              ? `Applies to all ${count} ${count === 1 ? 'character' : 'characters'}. The ban is written to the database, then the hotel reloads bans and disconnects ${p.username} through RCON.`
              : `Written to ${p.username}'s account; starts at their next login.`}
          </p>
        )}
        {error ? <Note tone="bad" icon="warning" title={error} /> : null}
      </div>
      <div className="card-f">
        <Btn variant={mode === 'ban' ? 'danger-primary' : 'primary'} disabled={reason.trim().length < 3 || busy || muteBlocked} onClick={submit}>
          {mode === 'ban' ? `Ban account for ${label.toLowerCase()}` : `Mute for ${label.toLowerCase()}`}
        </Btn>
      </div>
    </section>
  );
}

