import { useNavigate } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useFetch } from '@hk/lib/useFetch';
import { fmtDate, fmtNumber } from '@hk/lib/format';
import { Card, Empty, Loadable, Pill, PlayerChip, PlayerRef, Stat, Wp } from '@hk/ui';
import { Crest } from './Corporations';

interface Gang { id: number; name: string; badge: string; boss: PlayerRef | null; created: number; level: number; xp: number; xp_cap: number; members: number; online: number; turf: number }

/** The initials of a gang's name for its placeholder crest. */
export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w[0])
    .join('')
    .slice(0, 3);
}

export default function Gangs() {
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Gangs' }]);
  const navigate = useNavigate();
  const state = useFetch<{ items: Gang[] }>('/gangs');
  const items = state.data?.items ?? [];

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Gangs</h1>
          <p className="sub">Groups flagged as gangs, their level and the turf they hold</p>
        </div>
        <span className="sub" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <Wp path="game" icon /> Roles and members are changed in game
        </span>
      </div>
      <section className="card stats">
        <Stat label="Gangs" value={state.data ? items.length : '—'} />
        <Stat label="Members" value={state.data ? fmtNumber(items.reduce((n, g) => n + g.members, 0)) : '—'} sub={state.data ? `${items.reduce((n, g) => n + g.online, 0)} online now` : undefined} />
        <Stat dot="gang" label="Turf held" value={state.data ? items.reduce((n, g) => n + g.turf, 0) : '—'} />
      </section>
      <Card>
        <Loadable state={state} rows={5} empty={(d) => (d.items.length === 0 ? <Empty title="No gangs" body="Gangs are founded in game." /> : false)}>
          {(d) => (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Gang</th>
                    <th>Boss</th>
                    <th>Level</th>
                    <th className="r">Members</th>
                    <th className="r">Online</th>
                    <th className="r">Turf</th>
                    <th className="hide-phone">Founded</th>
                  </tr>
                </thead>
                <tbody>
                  {d.items.map((g) => (
                    <tr key={g.id} className="click" onClick={() => navigate(`/city/gangs/${g.id}`)}>
                      <td>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 10 }}>
                          <Crest text={initials(g.name)} size={30} />
                          <span style={{ fontWeight: 500 }}>{g.name}</span>
                        </span>
                      </td>
                      <td>
                        <PlayerChip player={g.boss} />
                      </td>
                      <td>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                          <Pill tone="brand">Level {g.level}</Pill>
                          <span className="bar hide-phone" style={{ width: 64 }}>
                            <i style={{ width: `${Math.min(100, Math.round((g.xp / Math.max(1, g.xp_cap)) * 100))}%`, background: 'var(--gang)' }} />
                          </span>
                        </span>
                      </td>
                      <td className="r num">{g.members}</td>
                      <td className="r num">{g.online}</td>
                      <td className="r num">{g.turf}</td>
                      <td className="hide-phone" style={{ color: 'var(--muted)' }}>
                        {fmtDate(g.created)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Loadable>
      </Card>
    </main>
  );
}
