import { Link } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { badgeUrl } from '@hk/lib/avatar';
import { fmtNumber } from '@hk/lib/format';
import { EmptyState, ErrorBox, Pill, Skeleton } from '@hk/ui';

export interface CorpRank { id: number; order: number; name: string; pay: number; tiers: number }
export interface Corp {
  id: number; name: string; acronym: string; description: string; badge: string; service_type: string; stock: number; stock_capacity: number; manage_rank_order: number; employees: number; on_duty: number; ranks: CorpRank[];
}
interface CorpList { available: boolean; items: Corp[] }

export function CorpBadge({ corp, badgesPath }: { corp: { name: string; badge: string; acronym?: string }; badgesPath: string }) {
  return (
    <span className="hk-corpbadge" title={corp.name}>
      {corp.badge ? <img src={badgeUrl(badgesPath, corp.badge)} alt="" className="px-art" onError={(e) => ((e.target as HTMLImageElement).style.display = 'none')} /> : (corp.acronym || corp.name.slice(0, 2)).toUpperCase()}
    </span>
  );
}

export default function Corporations() {
  useCrumbs([{ label: 'Roleplay' }, { label: 'Corporations' }]);
  const { me } = useSession();
  const { data, error, loading, reload } = useFetch<CorpList>('/roleplay/corporations');

  if (error) return <ErrorBox message={error} retry={reload} />;

  return (
    <section className="hk-page" aria-label="Corporations">
      <div className="hk-pagehead">
        <div>
          <h1 className="hk-h1" style={{ marginBottom: 6 }}>Corporations</h1>
          <p className="hk-lede">Every corp in the city with its rank ladder and who is on the clock. Hiring, firing and rank edits still happen in-game until the RCON round-trip lands here.</p>
        </div>
        {data ? <span className="small muted">{data.items.length} corporations · {fmtNumber(data.items.reduce((a, c) => a + c.employees, 0))} employees · {fmtNumber(data.items.reduce((a, c) => a + c.on_duty, 0))} on duty</span> : null}
      </div>

      {loading || !data ? (
        <div className="hk-card">
          <Skeleton rows={6} />
        </div>
      ) : !data.available ? (
        <EmptyState title="Roleplay tables are not installed" body="The emulator's corporation tables are missing from this database, so there is nothing to show yet." />
      ) : data.items.length === 0 ? (
        <EmptyState title="No corporations yet" body="Corporations are created by the emulator's SQL updates. Once they exist they show up here." />
      ) : (
        <div className="hk-grid hk-grid--cards">
          {data.items.map((c) => (
            <Link key={c.id} to={`/roleplay/corporations/${c.id}`} className="hk-card" style={{ display: 'flex', flexDirection: 'column', color: 'var(--fg)', textDecoration: 'none' }}>
              <div style={{ display: 'flex', gap: 14, padding: 16, alignItems: 'center', borderBottom: '2px solid var(--line-soft)' }}>
                <CorpBadge corp={c} badgesPath={me.config.badges_path} />
                <span style={{ flex: 1, minWidth: 0 }}>
                  <span style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <span style={{ fontWeight: 700, fontSize: 16 }}>{c.name}</span>
                    {c.acronym ? <code>{c.acronym}</code> : null}
                    {c.service_type ? <Pill tone="info">{c.service_type}</Pill> : null}
                  </span>
                  <span className="hk-row__sub" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.description || 'No description'}</span>
                </span>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 0, borderBottom: '2px solid var(--line-soft)' }}>
                {[
                  ['Employees', fmtNumber(c.employees)],
                  ['On duty', fmtNumber(c.on_duty)],
                  ['Ranks', String(c.ranks.length)],
                ].map(([label, value], i) => (
                  <div key={label} style={{ padding: '10px 16px', borderLeft: i > 0 ? '2px solid var(--line-soft)' : 'none' }}>
                    <div className="small muted" style={{ fontSize: 12, fontWeight: 600 }}>{label}</div>
                    <div style={{ fontWeight: 700, fontSize: 20, fontVariantNumeric: 'tabular-nums' }}>{value}</div>
                  </div>
                ))}
              </div>
              {c.stock_capacity > 0 ? (
                <div style={{ padding: '10px 16px', display: 'flex', flexDirection: 'column', gap: 6 }}>
                  <div className="hk-kv small">
                    <span className="muted">Stock</span>
                    <strong className="tabular">
                      {fmtNumber(c.stock)} / {fmtNumber(c.stock_capacity)}
                    </strong>
                  </div>
                  <div className="hk-bar">
                    <span style={{ width: `${Math.min(100, Math.round((c.stock / c.stock_capacity) * 100))}%` }} />
                  </div>
                </div>
              ) : null}
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
