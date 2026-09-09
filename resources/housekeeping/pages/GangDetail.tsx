import { Link, useParams } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { fmtDate, fmtNumber } from '@hk/lib/format';
import { Avatar, Card, ErrorBox, OnlineDot, Pill, Skeleton } from '@hk/ui';
import { Gang } from './Gangs';

interface GangDetailData {
  gang: Omit<Gang, 'members'>;
  roles: { id: number; name: string; can_invite: boolean; can_kick: boolean; can_bank: boolean; is_admin: boolean }[];
  members: { id: number; username: string; look: string; online: boolean; role_name: string; joined_at: number }[];
}

export default function GangDetail() {
  const { id } = useParams();
  const { me } = useSession();
  const { data, error, loading, reload } = useFetch<GangDetailData>(id ? `/roleplay/gangs/${id}` : null);
  const name = data?.gang.name ?? '…';
  useCrumbs([{ label: 'Roleplay' }, { label: 'Gangs', to: '/roleplay/gangs' }, { label: name }]);

  if (error) return <ErrorBox message={error} retry={reload} />;
  if (loading || !data) {
    return (
      <div className="hk-card">
        <Skeleton rows={6} />
      </div>
    );
  }
  const g = data.gang;
  const cap = Math.max(1, g.level) * 200;

  return (
    <section className="hk-page" aria-label="Gang" style={{ gap: 20 }}>
      <Link to="/roleplay/gangs" className="small" style={{ alignSelf: 'flex-start', textDecoration: 'none', color: 'var(--muted)', minHeight: 32, display: 'inline-flex', alignItems: 'center', gap: 6 }}>
        ◀ All gangs
      </Link>
      <div className="hk-card hk-card--lg" style={{ display: 'flex', flexWrap: 'wrap', gap: 20, padding: 20, alignItems: 'center' }}>
        <div style={{ flex: 1, minWidth: 220, display: 'flex', flexDirection: 'column', gap: 6 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
            <h1 className="hk-h1 hk-h1--lg">{g.name}</h1>
            <Pill tone="orange">Level {g.level}</Pill>
          </div>
          <p className="hk-lede">{g.description || 'No description'}</p>
          <div className="small muted" style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
            <span>
              Leader{' '}
              <Link to={`/players/${g.owner_id}`} style={{ fontWeight: 600 }}>
                {g.owner_name || `#${g.owner_id}`}
              </Link>
            </span>
            <span>Founded {fmtDate(g.created)}</span>
            <span>{data.members.length} members</span>
          </div>
          <div style={{ maxWidth: 360, display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div className="hk-kv small">
              <span className="muted">XP to next level</span>
              <strong className="tabular">
                {fmtNumber(g.xp)} / {fmtNumber(cap)}
              </strong>
            </div>
            <div className="hk-bar">
              <span style={{ width: `${Math.min(100, Math.round((g.xp / cap) * 100))}%` }} />
            </div>
          </div>
        </div>
      </div>

      <div className="hk-grid" style={{ gridTemplateColumns: 'minmax(0, 2fr) minmax(260px, 1fr)' }}>
        <Card title="Members" action={<span className="small muted">{data.members.length}</span>}>
          {data.members.map((m) => (
            <div key={m.id} className="hk-row">
              <Link to={`/players/${m.id}`} className="hk-cell-btn" style={{ flex: 1 }}>
                <Avatar look={m.look} imager={me.config.imager} />
                <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.25 }}>
                  <span className="name" style={{ fontWeight: 600 }}>{m.username}</span>
                  <span className="small muted">Joined {fmtDate(m.joined_at)}</span>
                </span>
              </Link>
              <span className={`hk-rank ${m.role_name === 'Leader' ? 'hk-rank--top' : ''}`}>{m.role_name}</span>
              <span className="hk-status">
                <OnlineDot online={m.online} />
                {m.online ? 'Online' : 'Offline'}
              </span>
            </div>
          ))}
        </Card>
        <Card title="Roles">
          {data.roles.length === 0 ? (
            <div className="hk-card__body muted small">No roles beyond Leader and Member.</div>
          ) : (
            data.roles.map((r) => (
              <div key={r.id} className="hk-row" style={{ flexWrap: 'wrap' }}>
                <span className="hk-row__main">
                  <span className="hk-row__title">{r.name}</span>
                  <span className="hk-row__sub">
                    {[r.is_admin ? 'admin' : null, r.can_invite ? 'invite' : null, r.can_kick ? 'kick' : null, r.can_bank ? 'bank' : null].filter(Boolean).join(' · ') || 'no permissions'}
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
