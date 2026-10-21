import { useNavigate } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useFetch } from '@hk/lib/useFetch';
import { fmtNumber } from '@hk/lib/format';
import { Bar, Card, Domain, Empty, Loadable, Pill, Stat, Wp } from '@hk/ui';

interface Corp { id: number; name: string; acronym: string; badge: string; domain: Domain; hidden: boolean; employees: number; on_duty: number; shift_week: number; ranks: number; hq_rooms: number; stock: number; stock_capacity: number }

export const DOMAIN_LABEL: Record<string, string> = { police: 'Police', medical: 'Medical', corp: 'Business' };

/** The square placeholder for a corporation or gang badge: its acronym on the game-art tile. */
export function Crest({ text, size = 56 }: { text: string; size?: number }) {
  return (
    <span style={{ width: size, height: size, borderRadius: size >= 48 ? 14 : 8, background: 'var(--tile)', display: 'flex', alignItems: 'center', justifyContent: 'center', flex: 'none' }} aria-hidden="true">
      <span className="mono" style={{ color: 'var(--ink)', fontWeight: 500, fontSize: size >= 48 ? 13 : 11 }}>
        {text.slice(0, 5).toUpperCase()}
      </span>
    </span>
  );
}

export default function Corporations() {
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Corporations' }]);
  const navigate = useNavigate();
  const state = useFetch<{ items: Corp[] }>('/corporations');
  const items = state.data?.items ?? [];

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Corporations</h1>
          <p className="sub">Every business in the city, its ladder and who is working</p>
        </div>
        <span className="sub" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <Wp path="game" icon /> Structure is edited in game
        </span>
      </div>
      <section className="card stats">
        <Stat label="Corporations" value={state.data ? items.length : '—'} sub={state.data ? `${items.filter((c) => c.hidden).length} hidden` : undefined} />
        <Stat label="Employees" value={state.data ? fmtNumber(items.reduce((n, c) => n + c.employees, 0)) : '—'} />
        <Stat dot="ok" label="On duty now" value={state.data ? fmtNumber(items.reduce((n, c) => n + c.on_duty, 0)) : '—'} />
        <Stat label="Shift time this week" value={state.data ? `${fmtNumber(Math.round(items.reduce((n, c) => n + c.shift_week, 0) / 3600))}h` : '—'} />
      </section>
      <Card>
        <Loadable state={state} rows={6} empty={(d) => (d.items.length === 0 ? <Empty title="No corporations" body="Corporations are created in game." /> : false)}>
          {(d) => (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Corporation</th>
                    <th>Kind</th>
                    <th className="r">Employees</th>
                    <th className="r">On duty</th>
                    <th className="r hide-phone">Ranks</th>
                    <th className="r hide-phone">HQ rooms</th>
                    <th className="r hide-phone">Shift this week</th>
                    <th className="hide-phone">Stock</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {d.items.map((c) => {
                    const pct = c.stock_capacity > 0 ? Math.round((c.stock / c.stock_capacity) * 100) : 0;
                    return (
                      <tr key={c.id} className="click" onClick={() => navigate(`/city/corporations/${c.id}`)}>
                        <td>
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 10 }}>
                            <Crest text={c.acronym || c.name} size={30} />
                            <span style={{ fontWeight: 500 }}>{c.name}</span>
                          </span>
                        </td>
                        <td>
                          <span className="tag">
                            <span className={`dot ${c.domain}`} />
                            {DOMAIN_LABEL[c.domain] ?? 'Business'}
                          </span>
                        </td>
                        <td className="r num">{fmtNumber(c.employees)}</td>
                        <td className="r num">{fmtNumber(c.on_duty)}</td>
                        <td className="r num hide-phone">{c.ranks}</td>
                        <td className="r num hide-phone">{c.hq_rooms}</td>
                        <td className="r num hide-phone">{fmtNumber(Math.round(c.shift_week / 3600))}h</td>
                        <td className="hide-phone">
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                            <Bar pct={pct} width={64} tone={pct < 20 ? 'bad' : undefined} />
                            <span className="num">{pct}%</span>
                          </span>
                        </td>
                        <td>
                          <Pill tone={c.hidden ? '' : 'ok'}>{c.hidden ? 'Hidden' : 'Visible'}</Pill>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Loadable>
      </Card>
    </main>
  );
}
