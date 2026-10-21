import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useFetch } from '@hk/lib/useFetch';
import { fmtCompact, fmtDay, fmtNumber, fmtWhen } from '@hk/lib/format';
import { Card, Coin, Empty, Loadable, Pill, PlayerChip, PlayerRef, Seg, Stat, Tone, Wp } from '@hk/ui';

interface EconomyData {
  days: { day: number; created: number; removed: number }[];
  totals: { on_hand: number; bank_current: number; bank_savings: number; created_today: number; removed_today: number; today: number };
  sources: { name: string; amount: number }[];
  largest: { at: number; from: PlayerRef | null; to: PlayerRef | null; book: string; amount: number; what: string }[];
  bins: { reward: string; weight: number; min: number; max: number }[];
  prices: { key: string; name: string; price: number; corporation: string | null }[];
  diamond_sales: { cents: number; orders: number } | null;
  payments_rank: number;
}

const RANGES = [
  { value: 'today', label: 'Today' },
  { value: '8d', label: '8 days' },
  { value: '30d', label: '30 days' },
] as const;
type Range = (typeof RANGES)[number]['value'];

const BOOK_TONE: Record<string, Tone> = { Coins: 'acc', Current: 'info', Savings: 'info', Transfer: 'brand', Trade: '' };

function binLabel(b: EconomyData['bins'][number]): string {
  const name = b.reward.replace(/_/g, ' ');
  if (b.reward === 'nothing') return 'Nothing';
  if (b.reward === 'coins') return b.min === b.max ? `${b.min} coins` : `${b.min} to ${b.max} coins`;
  const label = name.charAt(0).toUpperCase() + name.slice(1);
  return b.max > 1 ? `${label} (${b.min === b.max ? b.min : `${b.min} to ${b.max}`})` : label;
}

export default function Economy() {
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Economy' }]);
  const [range, setRange] = useState<Range>('8d');
  const state = useFetch<EconomyData>(`/economy?range=${range}`);
  const d = state.data;
  const t = d?.totals;
  const net = t ? t.created_today - t.removed_today : 0;
  const supply = t ? t.on_hand + t.bank_current + t.bank_savings : 0;
  const rangeLabel = range === 'today' ? 'Today' : range === '8d' ? 'Last 8 days' : 'Last 30 days';

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Economy</h1>
          <p className="sub">Where coins enter the city, move around and leave</p>
        </div>
        <Seg label="Range" value={range} onChange={setRange} options={[...RANGES]} />
      </div>

      <section className="card stats">
        <Stat label={<><Coin /> Coins on hand</>} value={t ? fmtCompact(t.on_hand) : '—'} sub="All players, as stored" />
        <Stat label="Bank deposits" value={t ? fmtCompact(t.bank_current + t.bank_savings) : '—'} sub="Current and savings" />
        <Stat label="Created today" value={t ? fmtNumber(t.created_today) : '—'} sub="Wages, interest, bins, gifts" />
        <Stat label="Removed today" value={t ? fmtNumber(t.removed_today) : '—'} sub="Shops, fees, services" />
        <Stat label="Net today" value={t ? `${net >= 0 ? '+' : '−'}${fmtNumber(Math.abs(net))}` : '—'} tone={net >= 0 ? 'pos' : 'neg'} sub={t && supply > 0 ? `${net >= 0 ? '+' : '−'}${((Math.abs(net) / supply) * 100).toFixed(2)}% money supply` : undefined} />
        {d?.diamond_sales ? (
          <Stat
            label={<><Coin type="diamonds" /> Diamond sales</>}
            value={(d.diamond_sales.cents / 100).toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })}
            sub={`This week · ${d.diamond_sales.orders} ${d.diamond_sales.orders === 1 ? 'order' : 'orders'}${d.payments_rank ? ` · rank ${d.payments_rank}+` : ''}`}
          />
        ) : null}
      </section>

      <div className="split">
        <Card className="main-col" title="Coins created and removed" sub="Per day" action={<Legend />}>
          <Loadable state={state} empty={(x) => (x.days.every((day) => !day.created && !day.removed) ? <Empty title="No coins moved in this range" /> : false)}>
            {(x) => <FlowChart days={x.days} />}
          </Loadable>
        </Card>
        <Card className="side-col" title="By source" sub={rangeLabel}>
          <Loadable state={state} empty={(x) => (x.sources.length === 0 ? <Empty title="Nothing recorded" /> : false)}>
            {(x) => {
              const max = Math.max(1, ...x.sources.map((s) => Math.abs(s.amount)));
              return x.sources.map((s) => (
                <div key={s.name} className="row" style={{ paddingTop: 8, paddingBottom: 8 }}>
                  <span className="grow">{s.name}</span>
                  <span className="bar" style={{ width: 64 }}>
                    <i style={{ width: `${Math.round((Math.abs(s.amount) / max) * 100)}%`, background: s.amount >= 0 ? 'var(--info)' : 'var(--accent)' }} />
                  </span>
                  <span className={`num ${s.amount >= 0 ? 'pos' : 'neg'}`} style={{ width: 80, textAlign: 'right' }}>
                    {s.amount >= 0 ? '+' : '−'}
                    {fmtNumber(Math.abs(s.amount))}
                  </span>
                </div>
              ));
            }}
          </Loadable>
        </Card>
      </div>

      <div className="split">
        <Card className="main-col" title="Largest movements" sub="Last 24 hours · all ledgers" action={<Link to="/logs?kind=money&when=24h" style={{ fontSize: 13 }}>Open ledger explorer</Link>}>
          <Loadable state={state} empty={(x) => (x.largest.length === 0 ? <Empty title="No money moved in the last day" /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>From</th>
                      <th>To</th>
                      <th>Ledger</th>
                      <th className="hide-phone">What</th>
                      <th className="r">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {x.largest.map((m, i) => (
                      <tr key={i}>
                        <td className="mono">{fmtWhen(m.at)}</td>
                        <td>{m.from ? <PlayerChip player={m.from} /> : <span className="sub">The city</span>}</td>
                        <td>{m.to ? <PlayerChip player={m.to} /> : <span className="sub">The city</span>}</td>
                        <td>
                          <Pill tone={BOOK_TONE[m.book] ?? 'info'}>{m.book}</Pill>
                        </td>
                        <td className="hide-phone sub ell" style={{ maxWidth: 220 }}>
                          {m.what}
                        </td>
                        <td className="r num">{fmtNumber(m.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>
        <div className="side-col stack">
          <Card title="Bin rewards" sub="Chance per search · rp_bin_rewards" action={<Wp path="game" />}>
            <Loadable state={state} empty={(x) => (x.bins.length === 0 ? <Empty title="No bin rewards set" /> : false)}>
              {(x) => {
                const total = Math.max(1, x.bins.reduce((n, b) => n + b.weight, 0));
                return (
                  <>
                    {x.bins.map((b) => {
                      const pct = Math.round((b.weight / total) * 100);
                      return (
                        <div key={b.reward} className="row" style={{ paddingTop: 8, paddingBottom: 8 }}>
                          <span className="grow">{binLabel(b)}</span>
                          <span className="bar" style={{ width: 72 }}>
                            <i style={{ width: `${pct}%` }} />
                          </span>
                          <span className="num" style={{ width: 40, textAlign: 'right' }}>
                            {pct}%
                          </span>
                        </div>
                      );
                    })}
                    <div className="row">
                      <span className="sub">Edit weights in the City Panel until the RCON command ships</span>
                    </div>
                  </>
                );
              }}
            </Loadable>
          </Card>
          <Card title="Service prices" sub="rp_service_prices" action={<Wp path="game" />}>
            <Loadable state={state} empty={(x) => (x.prices.length === 0 ? <Empty title="No service prices" /> : false)}>
              {(x) =>
                x.prices.map((p) => (
                  <div key={p.key} className="row" style={{ paddingTop: 8, paddingBottom: 8 }}>
                    <span className="grow">
                      {p.name}
                      {p.corporation ? <span className="sub"> · {p.corporation}</span> : null}
                    </span>
                    <span className="num">{fmtNumber(p.price)}</span>
                    <Coin alt="coins" />
                  </div>
                ))
              }
            </Loadable>
          </Card>
        </div>
      </div>
    </main>
  );
}

function Legend() {
  return (
    <div style={{ display: 'flex', gap: 14 }} className="sub">
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
        <span style={{ width: 10, height: 10, borderRadius: 2, background: 'var(--info)' }} />
        Created
      </span>
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
        <span style={{ width: 10, height: 10, borderRadius: 2, background: 'var(--accent)' }} />
        Removed
      </span>
    </div>
  );
}

/** Paired bars per day: coins created against coins removed. */
function FlowChart({ days }: { days: EconomyData['days'] }) {
  const max = Math.max(1, ...days.flatMap((day) => [day.created, day.removed]));
  const many = days.length > 10;
  const ahead = days.filter((day) => day.created > day.removed).length;
  return (
    <div className="card-b">
      <div
        style={{ display: 'flex', alignItems: 'flex-end', gap: many ? 4 : 14, height: 200, borderBottom: '1px solid var(--line)', padding: '0 4px' }}
        role="img"
        aria-label={`Coins created ran ahead of coins removed on ${ahead} of ${days.length} days`}
      >
        {days.map((day) => (
          <div key={day.day} style={{ flex: 1, display: 'flex', alignItems: 'flex-end', gap: many ? 1 : 3, height: '100%', justifyContent: 'center' }}>
            <div title={`Created ${fmtNumber(day.created)} on ${fmtDay(day.day)}`} style={{ width: '40%', maxWidth: 18, height: `${Math.round((day.created / max) * 170)}px`, minHeight: day.created ? 2 : 0, background: 'var(--info)', borderRadius: '3px 3px 0 0' }} />
            <div title={`Removed ${fmtNumber(day.removed)} on ${fmtDay(day.day)}`} style={{ width: '40%', maxWidth: 18, height: `${Math.round((day.removed / max) * 170)}px`, minHeight: day.removed ? 2 : 0, background: 'var(--accent)', borderRadius: '3px 3px 0 0' }} />
          </div>
        ))}
      </div>
      <div style={{ display: 'flex', gap: many ? 4 : 14, padding: '6px 4px 0' }}>
        {days.map((day, i) => (
          <span key={day.day} className="sub" style={{ flex: 1, textAlign: 'center', fontSize: 11, whiteSpace: 'nowrap', overflow: 'hidden' }}>
            {many && i % 5 !== 0 && i !== days.length - 1 ? '' : i === 0 || new Date(day.day * 1000).getDate() === 1 ? fmtDay(day.day) : new Date(day.day * 1000).getDate()}
          </span>
        ))}
      </div>
    </div>
  );
}
