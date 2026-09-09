import { Link, useParams } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { fmtDate, fmtDuration, fmtNumber } from '@hk/lib/format';
import { Avatar, Card, ErrorBox, OnlineDot, Pill, Skeleton } from '@hk/ui';
import { CorpBadge, CorpRank } from './Corporations';

interface Employee { id: number; username: string; look: string; online: boolean; rank_name: string; rank_order: number; tier: number; hired_at: number; shift_seconds: number; shift_seconds_week: number; on_duty: boolean }
interface CorpDetail {
  corporation: { id: number; name: string; acronym: string; description: string; badge: string; service_type: string; stock: number; stock_capacity: number };
  employees: Employee[];
}

export default function CorporationDetail() {
  const { id } = useParams();
  const { me } = useSession();
  const { data, error, loading, reload } = useFetch<CorpDetail>(id ? `/roleplay/corporations/${id}` : null);
  const list = useFetch<{ items: { id: number; ranks: CorpRank[] }[] }>('/roleplay/corporations');
  const name = data?.corporation.name ?? '…';
  useCrumbs([{ label: 'Roleplay' }, { label: 'Corporations', to: '/roleplay/corporations' }, { label: name }]);

  if (error) return <ErrorBox message={error} retry={reload} />;
  if (loading || !data) {
    return (
      <div className="hk-card">
        <Skeleton rows={6} />
      </div>
    );
  }
  const c = data.corporation;
  const ranks = list.data?.items.find((x) => x.id === c.id)?.ranks ?? [];
  const onDuty = data.employees.filter((e) => e.on_duty).length;

  return (
    <section className="hk-page" aria-label="Corporation" style={{ gap: 20 }}>
      <Link to="/roleplay/corporations" className="small" style={{ alignSelf: 'flex-start', textDecoration: 'none', color: 'var(--muted)', minHeight: 32, display: 'inline-flex', alignItems: 'center', gap: 6 }}>
        ◀ All corporations
      </Link>
      <div className="hk-card hk-card--lg" style={{ display: 'flex', flexWrap: 'wrap', gap: 20, padding: 20, alignItems: 'center' }}>
        <CorpBadge corp={c} badgesPath={me.config.badges_path} />
        <div style={{ flex: 1, minWidth: 220, display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
            <h1 className="hk-h1 hk-h1--lg">{c.name}</h1>
            {c.acronym ? <code>{c.acronym}</code> : null}
            {c.service_type ? <Pill tone="info">{c.service_type}</Pill> : null}
          </div>
          <p className="hk-lede">{c.description || 'No description'}</p>
          <div className="small muted" style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
            <span>{data.employees.length} employees</span>
            <span>{onDuty} on duty now</span>
            {c.stock_capacity > 0 ? (
              <span>
                Stock {fmtNumber(c.stock)} / {fmtNumber(c.stock_capacity)}
              </span>
            ) : null}
          </div>
        </div>
      </div>

      <div className="hk-grid" style={{ gridTemplateColumns: 'minmax(0, 2fr) minmax(260px, 1fr)' }}>
        <Card title="Employees" action={<span className="small muted">{data.employees.length}</span>} className="hk-tablewrap">
          {data.employees.length === 0 ? (
            <div className="hk-card__body muted small">Nobody works here yet.</div>
          ) : (
            <table className="hk-table" style={{ minWidth: 560 }}>
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Rank</th>
                  <th>Shift</th>
                  <th>This week</th>
                  <th>Lifetime</th>
                  <th>Hired</th>
                </tr>
              </thead>
              <tbody>
                {data.employees.map((e) => (
                  <tr key={e.id}>
                    <td>
                      <Link to={`/players/${e.id}`} className="hk-cell-btn">
                        <Avatar look={e.look} imager={me.config.imager} />
                        <span className="name" style={{ fontWeight: 600 }}>{e.username}</span>
                      </Link>
                    </td>
                    <td>
                      <span className="hk-rank">
                        {e.rank_name || '—'} <small>tier {e.tier}</small>
                      </span>
                    </td>
                    <td>
                      <span className="hk-status">
                        <OnlineDot online={e.on_duty} />
                        {e.on_duty ? 'On duty' : e.online ? 'Online, off shift' : 'Off shift'}
                      </span>
                    </td>
                    <td className="tabular">{fmtDuration(e.shift_seconds_week)}</td>
                    <td className="tabular muted">{fmtDuration(e.shift_seconds)}</td>
                    <td className="muted">{fmtDate(e.hired_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Card>
        <Card title="Rank ladder" action={<span className="small muted">highest first</span>}>
          {ranks.length === 0 ? (
            <div className="hk-card__body muted small">No ranks defined.</div>
          ) : (
            ranks.map((r) => (
              <div key={r.id} className="hk-row">
                <span className="hk-rank hk-rank--staff" style={{ width: 32, justifyContent: 'center', padding: 0, fontWeight: 700 }}>
                  {r.order}
                </span>
                <span className="hk-row__main">
                  <span className="hk-row__title">{r.name}</span>
                  <span className="hk-row__sub">
                    {fmtNumber(r.pay)} coins per pay interval · up to tier {r.tiers}
                  </span>
                </span>
              </div>
            ))
          )}
        </Card>
      </div>
    </section>
  );
}
