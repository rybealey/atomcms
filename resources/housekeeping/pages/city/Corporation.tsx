import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { fullUrl } from '@hk/lib/avatar';
import { fmtDate, fmtDay, fmtDuration, fmtNumber } from '@hk/lib/format';
import { Btn, Card, Coin, Domain, Empty, ErrorNote, Pill, PlayerChip, PlayerRef, RoomChip, Seg, Skeleton, Stat, Wp } from '@hk/ui';
import { Crest, DOMAIN_LABEL } from './Corporations';

interface CorpData {
  corporation: { id: number; name: string; acronym: string; description: string; badge: string; domain: Domain; service_type: string; police: boolean; hidden: boolean; stock: number; stock_capacity: number; manage_rank_order: number };
  stats: { employees: number; ranks: number; on_duty: PlayerRef[]; shift_week: number; payroll_week: number; last_restock: number | null };
  ladder: { id: number; order: number; name: string; pay: number; tiers: number; emergency: boolean; manages: boolean; headcount: number }[];
  employees: { player: PlayerRef | null; rank: string; tier: number; on_duty: boolean; online: boolean; shift_week: number; shift_total: number; hired_at: number }[];
  stock: { at: number; value: number }[];
  prices: { key: string; name: string; price: number }[];
  hq: { id: number; name: string; now: number; lowest: string | null; ranks: number }[];
  uniforms: { rank: string; male: string; female: string }[];
}

const DOMAIN_COLOR: Record<string, string> = { police: 'var(--police)', medical: 'var(--medical)', corp: 'var(--corp)' };

export default function Corporation() {
  const id = Number(useParams().id);
  const state = useFetch<CorpData>(`/corporations/${id}`);
  const d = state.data;
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Corporations', to: '/city/corporations' }, { label: d?.corporation.name ?? 'Corporation' }]);

  if (state.error && !d) {
    return (
      <main className="page">
        <ErrorNote message={state.error} retry={state.reload} />
      </main>
    );
  }
  if (!d) {
    return (
      <main className="page">
        <div className="card">
          <Skeleton rows={5} />
        </div>
      </main>
    );
  }

  const c = d.corporation;
  const color = DOMAIN_COLOR[c.domain] ?? 'var(--corp)';
  const stockPct = c.stock_capacity > 0 ? Math.round((c.stock / c.stock_capacity) * 100) : 0;
  const stockLabel = c.domain === 'medical' ? 'Medical stock' : 'Stock';

  return (
    <main className="page">
      <section style={{ display: 'flex', flexWrap: 'wrap', gap: 16, alignItems: 'center' }}>
        <Crest text={c.acronym || c.name} />
        <div className="grow" style={{ minWidth: 240 }}>
          <div className="actions" style={{ gap: 10 }}>
            <h1 style={{ fontSize: 22, lineHeight: '30px', fontWeight: 600, letterSpacing: '-0.01em' }}>{c.name}</h1>
            <span className="tag">
              <span className={`dot ${c.domain}`} />
              {DOMAIN_LABEL[c.domain] ?? 'Business'}
            </span>
            <Pill tone={c.police ? 'info' : ''}>{c.police ? 'Police powers' : 'No police powers'}</Pill>
            <Pill tone={c.hidden ? '' : 'ok'}>{c.hidden ? 'Hidden' : 'Visible'}</Pill>
          </div>
          <p className="sub">
            {c.acronym ? (
              <>
                Acronym <span className="mono">{c.acronym}</span> ·{' '}
              </>
            ) : null}
            corporation <span className="mono">#{c.id}</span>
            {c.service_type ? ` · service ${c.service_type}` : ''}
            {c.description ? ` · ${c.description}` : ''}
          </p>
        </div>
        <div className="actions">
          <span className="sub">Structure is edited in game</span>
          <Btn wp="game" disabled title="Hide or show a corporation from the City Panel for now">
            {c.hidden ? 'Show corporation' : 'Hide corporation'}
          </Btn>
        </div>
      </section>

      <section className="card stats">
        <Stat label="Employees" value={fmtNumber(d.stats.employees)} sub={`${d.stats.ranks} ranks`} />
        <Stat dot="ok" label="On duty now" value={d.stats.on_duty.length} sub={d.stats.on_duty.length ? d.stats.on_duty.slice(0, 3).map((p) => p.username).join(', ') + (d.stats.on_duty.length > 3 ? ` and ${d.stats.on_duty.length - 3} more` : '') : 'Nobody clocked in'} />
        <Stat label="Shift time this week" value={`${fmtNumber(Math.round(d.stats.shift_week / 3600))}h`} sub={`${fmtNumber(Math.round(d.stats.shift_week / 3600 / Math.max(1, d.stats.employees)))}h per employee`} />
        <Stat label="Payroll this week" value={fmtNumber(d.stats.payroll_week)} sub="Coins paid in wages" />
        <Stat label={stockLabel} value={c.stock_capacity > 0 ? `${stockPct}%` : '—'} sub={d.stats.last_restock ? `Restocked ${fmtDay(d.stats.last_restock)}` : `${fmtNumber(c.stock)} of ${fmtNumber(c.stock_capacity)}`} />
      </section>

      <div className="split">
        <Card className="side-col" title="Rank ladder" sub="Pay per shift · headcount" action={<Wp path="game" />}>
          {d.ladder.length === 0 ? (
            <Empty title="No ranks" />
          ) : (
            <div className="card-b" style={{ display: 'flex', flexDirection: 'column' }}>
              {d.ladder.map((r, i) => {
                const max = Math.max(1, ...d.ladder.map((x) => x.headcount));
                const rights = [r.manages ? 'Hire, fire, pay' : null, r.emergency ? 'Emergency calls' : null].filter(Boolean).join(' · ');
                return (
                  <div key={r.id} style={{ display: 'grid', gridTemplateColumns: '20px minmax(0, 1fr) 60px 76px', gap: 10, alignItems: 'center', minHeight: 44 }}>
                    <span style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', alignSelf: 'stretch' }} aria-hidden="true">
                      <i style={{ width: 1, flex: 1, background: i === 0 ? 'transparent' : 'var(--line-strong)' }} />
                      <span className="dot" style={{ background: color }} />
                      <i style={{ width: 1, flex: 1, background: i === d.ladder.length - 1 ? 'transparent' : 'var(--line-strong)' }} />
                    </span>
                    <span>
                      <span style={{ fontWeight: 500 }}>{r.name}</span>
                      <span className="sub" style={{ display: 'block', fontSize: 12 }}>
                        {r.tiers > 0 ? `${r.tiers} tiers` : 'No tiers'}
                        {rights ? ` · ${rights}` : ''}
                      </span>
                    </span>
                    <span className="num r" style={{ display: 'flex', alignItems: 'center', gap: 4, justifyContent: 'flex-end' }}>
                      {fmtNumber(r.pay)}
                      <Coin alt="coins" />
                    </span>
                    <span style={{ display: 'flex', alignItems: 'center', gap: 6, justifyContent: 'flex-end' }}>
                      <span className="bar" style={{ width: 40 }}>
                        <i style={{ width: `${Math.round((r.headcount / max) * 100)}%`, background: color }} />
                      </span>
                      <span className="num">{r.headcount}</span>
                    </span>
                  </div>
                );
              })}
            </div>
          )}
        </Card>
        <Employees employees={d.employees} />
      </div>

      <div className="cols3">
        <Card title={stockLabel} sub="Last 14 days · rp_corporation_stock_samples">
          <div className="card-b">{d.stock.length < 2 ? <Empty bare title="Not enough samples yet" body="The emulator samples stock every 15 minutes." /> : <StockChart samples={d.stock} capacity={c.stock_capacity} color={color} />}</div>
        </Card>
        <Card title="Service prices" sub="rp_service_prices" action={<Wp path="game" />}>
          {d.prices.length === 0 ? (
            <Empty title="No priced services" />
          ) : (
            d.prices.map((p) => (
              <div key={p.key} className="row">
                <span className="grow">{p.name}</span>
                <span className="num">{fmtNumber(p.price)}</span>
                <Coin alt="coins" />
              </div>
            ))
          )}
        </Card>
        <Card title="HQ rooms" sub="Lowest rank allowed in">
          {d.hq.length === 0 ? (
            <Empty title="No HQ rooms" />
          ) : (
            d.hq.map((r) => (
              <div key={r.id} className="row">
                <RoomChip id={r.id} name={r.name} />
                <span className="grow" />
                <span className="sub hide-phone">{r.now} here</span>
                <Pill>{r.lowest ? (r.ranks > 1 ? `${r.lowest} and up` : r.lowest) : 'Everyone'}</Pill>
              </div>
            ))
          )}
        </Card>
      </div>

      <Uniforms uniforms={d.uniforms} />
    </main>
  );
}

function Employees({ employees }: { employees: CorpData['employees'] }) {
  const [filter, setFilter] = useState<'all' | 'duty'>('all');
  const onDuty = employees.filter((e) => e.on_duty).length;
  const rows = filter === 'duty' ? employees.filter((e) => e.on_duty) : employees;
  return (
    <Card
      className="main-col"
      title="Employees"
      sub="From rp_corporation_employees"
      action={<Seg label="Filter" value={filter} onChange={setFilter} options={[{ value: 'all', label: `All ${employees.length}` }, { value: 'duty', label: `On duty ${onDuty}` }]} />}
    >
      {rows.length === 0 ? (
        <Empty title={filter === 'duty' ? 'Nobody is clocked in' : 'No employees'} />
      ) : (
        <div className="tw">
          <table className="t">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Rank</th>
                <th>Duty</th>
                <th className="r">Shift this week</th>
                <th className="hide-phone">Hired</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((e, i) => (
                <tr key={e.player?.id ?? i}>
                  <td>
                    <PlayerChip player={e.player} />
                  </td>
                  <td>
                    {e.rank}
                    {e.tier > 0 ? <span className="sub"> · tier {e.tier}</span> : null}
                  </td>
                  <td>
                    <Pill tone={e.on_duty ? 'ok' : ''}>{e.on_duty ? 'On duty' : e.online ? 'Online, off duty' : 'Off duty'}</Pill>
                  </td>
                  <td className="r num">{fmtDuration(e.shift_week)}</td>
                  <td className="hide-phone" style={{ color: 'var(--muted)' }}>
                    {fmtDate(e.hired_at)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

/** Stock over time against capacity, with the 20% low-stock line. */
function StockChart({ samples, capacity, color }: { samples: { at: number; value: number }[]; capacity: number; color: string }) {
  const W = 320;
  const H = 120;
  const cap = Math.max(1, capacity, ...samples.map((s) => s.value));
  const t0 = samples[0].at;
  const span = Math.max(1, samples[samples.length - 1].at - t0);
  const y = (v: number) => (H - 10 - (v / cap) * (H - 20)).toFixed(1);
  const pts = samples.map((s) => `${(((s.at - t0) / span) * W).toFixed(1)},${y(s.value)}`).join(' ');
  const now = samples[samples.length - 1];
  return (
    <>
      <svg viewBox={`0 0 ${W} ${H}`} width="100%" height={140} preserveAspectRatio="none" role="img" aria-label={`Stock is ${Math.round((now.value / cap) * 100)}% of capacity`} style={{ display: 'block', color }}>
        <line x1="0" y1={H - 10} x2={W} y2={H - 10} stroke="var(--line)" vectorEffect="non-scaling-stroke" />
        <line x1="0" y1={y(cap / 2)} x2={W} y2={y(cap / 2)} stroke="var(--line)" strokeDasharray="2 4" vectorEffect="non-scaling-stroke" />
        <line x1="0" y1={y(cap * 0.2)} x2={W} y2={y(cap * 0.2)} stroke="var(--bad)" strokeDasharray="4 3" vectorEffect="non-scaling-stroke" />
        <polyline points={pts} fill="none" stroke="currentColor" strokeWidth={2} strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
      </svg>
      <div className="sub" style={{ display: 'flex', justifyContent: 'space-between' }}>
        <span>{fmtDay(t0)}</span>
        <span>Low-stock line 20%</span>
        <span>{fmtDay(now.at)}</span>
      </div>
    </>
  );
}

function Uniforms({ uniforms }: { uniforms: CorpData['uniforms'] }) {
  const { me } = useSession();
  const set = uniforms.filter((u) => u.male || u.female);
  return (
    <Card title="Uniforms" sub="Per rank and body type · rp_uniforms" action={<Wp path="game" />}>
      {set.length === 0 ? (
        <Empty title="No uniforms set" body="Ranks without one wear their own clothes on duty." />
      ) : (
        <div className="card-b" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))', gap: 12 }}>
          {uniforms.map((u) => (
            <div key={u.rank} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, padding: 12, border: '1px solid var(--line)', borderRadius: 10 }}>
              <div style={{ display: 'flex', gap: 8 }}>
                {(['male', 'female'] as const).map((g) => (
                  <span key={g} role="img" aria-label={u[g] ? `${u.rank} uniform, ${g}` : `No ${g} uniform`} style={{ width: 40, height: 64, borderRadius: 8, background: 'var(--tile)', display: 'flex', alignItems: 'flex-end', justifyContent: 'center', overflow: 'hidden' }} title={g === 'male' ? 'Male' : 'Female'}>
                    {u[g] ? <img src={fullUrl(me.config.imager, `${g === 'male' ? 'hd-180-1' : 'hd-600-1'}.${u[g]}`, 's')} alt="" className="px" loading="lazy" /> : <span className="sub" style={{ fontSize: 11, alignSelf: 'center' }}>None</span>}
                  </span>
                ))}
              </div>
              <span style={{ fontSize: 13, fontWeight: 500 }}>{u.rank}</span>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
