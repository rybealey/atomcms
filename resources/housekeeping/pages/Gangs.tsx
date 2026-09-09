import { Link } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { fmtDate, fmtNumber } from '@hk/lib/format';
import { EmptyState, ErrorBox, Pill, Skeleton } from '@hk/ui';

export interface Gang { id: number; name: string; description: string; badge: string; owner_id: number; owner_name: string; created: number; level: number; xp: number; members: number }
interface GangList { available: boolean; items: Gang[] }

export function GangBadge({ gang, groupBadgePath }: { gang: { name: string; badge: string }; groupBadgePath: string }) {
  return (
    <span className="hk-corpbadge" title={gang.name} style={{ background: 'var(--ink)', color: 'var(--cream)' }}>
      {gang.badge ? <img src={`${groupBadgePath}${gang.badge}.png`} alt="" className="px-art" onError={(e) => ((e.target as HTMLImageElement).style.display = 'none')} /> : gang.name.slice(0, 2).toUpperCase()}
    </span>
  );
}

export default function Gangs() {
  useCrumbs([{ label: 'Roleplay' }, { label: 'Gangs' }]);
  const { data, error, loading, reload } = useFetch<GangList>('/roleplay/gangs');
  useSession();

  if (error) return <ErrorBox message={error} retry={reload} />;

  return (
    <section className="hk-page" aria-label="Gangs">
      <div className="hk-pagehead">
        <div>
          <h1 className="hk-h1" style={{ marginBottom: 6 }}>Gangs</h1>
          <p className="hk-lede">Gangs are Habbo groups flagged as gangs by the emulator, with their own roles, invites and XP. Read-only for now; membership changes must go through the emulator.</p>
        </div>
        {data ? <span className="small muted">{data.items.length} gangs · {fmtNumber(data.items.reduce((a, g) => a + g.members, 0))} members</span> : null}
      </div>

      {loading || !data ? (
        <div className="hk-card">
          <Skeleton rows={6} />
        </div>
      ) : !data.available ? (
        <EmptyState title="Gang tables are not installed" body="The emulator's gang columns are missing from this database." />
      ) : data.items.length === 0 ? (
        <EmptyState title="No gangs yet" body="Players form gangs in-game. The first one shows up here." />
      ) : (
        <div className="hk-card hk-tablewrap">
          <table className="hk-table" style={{ minWidth: 640 }}>
            <thead>
              <tr>
                <th>Gang</th>
                <th>Leader</th>
                <th>Members</th>
                <th>Level</th>
                <th>Founded</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((g) => (
                <tr key={g.id}>
                  <td>
                    <Link to={`/roleplay/gangs/${g.id}`} className="hk-cell-btn">
                      <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.25 }}>
                        <span className="name" style={{ fontWeight: 600 }}>{g.name}</span>
                        <span className="small muted" style={{ maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{g.description}</span>
                      </span>
                    </Link>
                  </td>
                  <td>
                    {g.owner_id ? (
                      <Link to={`/players/${g.owner_id}`} style={{ fontWeight: 600 }}>
                        {g.owner_name || `#${g.owner_id}`}
                      </Link>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td className="tabular">{fmtNumber(g.members)}</td>
                  <td>
                    <Pill tone="orange">
                      Lv {g.level} · {fmtNumber(g.xp)} xp
                    </Pill>
                  </td>
                  <td className="muted">{fmtDate(g.created)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
