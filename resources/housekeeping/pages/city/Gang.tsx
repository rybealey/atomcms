import { useParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useFetch } from '@hk/lib/useFetch';
import { fmtDate, fmtDateTime, fmtNumber } from '@hk/lib/format';
import { Card, CellCheck, Empty, ErrorNote, Pill, PlayerChip, PlayerRef, RoomChip, Skeleton, Stat, Wp } from '@hk/ui';
import { Crest } from './Corporations';
import { initials } from './Gangs';

interface GangData {
  gang: { id: number; name: string; description: string; badge: string; boss: PlayerRef | null; created: number; level: number; xp: number; xp_cap: number; room_id: number };
  stats: { members: number; online: number; turf: number; contested: number; arrests_week: number; arrested_members: number; invites: number };
  roles: { id: number; name: string; invite: boolean; kick: boolean; bank: boolean; admin: boolean; members: number }[];
  members: { player: PlayerRef | null; role: string; status: 'online' | 'offline' | 'jailed'; joined_at: number; jailed_week: number }[];
  turf: { room: { id: number; name: string }; claimed_at: number; by: PlayerRef | null; contested: boolean }[];
  invites: { player: PlayerRef | null; by: PlayerRef | null; expires_at: number }[];
}

const STATUS = { online: { label: 'Online', dot: 'ok' }, offline: { label: 'Offline', dot: '' }, jailed: { label: 'Jailed', dot: 'warn' } } as const;

export default function Gang() {
  const id = Number(useParams().id);
  const state = useFetch<GangData>(`/gangs/${id}`);
  const d = state.data;
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Gangs', to: '/city/gangs' }, { label: d?.gang.name ?? 'Gang' }]);

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

  const g = d.gang;
  const pct = Math.min(100, Math.round((g.xp / Math.max(1, g.xp_cap)) * 100));
  const oldestInvite = d.invites.length ? Math.min(...d.invites.map((i) => i.expires_at)) : null;

  return (
    <main className="page">
      <section style={{ display: 'flex', flexWrap: 'wrap', gap: 16, alignItems: 'center' }}>
        <Crest text={initials(g.name)} />
        <div className="grow stack16" style={{ gap: 6, minWidth: 240 }}>
          <div className="actions" style={{ gap: 10 }}>
            <h1 style={{ fontSize: 22, lineHeight: '30px', fontWeight: 600, letterSpacing: '-0.01em' }}>{g.name}</h1>
            <span className="tag">
              <span className="dot gang" />
              Gang
            </span>
            <Pill tone="brand">Level {g.level}</Pill>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, maxWidth: 420 }}>
            <div className="bar grow" style={{ height: 6 }} role="img" aria-label={`${pct}% of the way to level ${g.level + 1}`}>
              <i style={{ width: `${pct}%`, background: 'var(--gang)' }} />
            </div>
            <span className="num" style={{ whiteSpace: 'nowrap' }}>
              {fmtNumber(g.xp)} / {fmtNumber(g.xp_cap)} XP
            </span>
          </div>
          <p className="sub actions" style={{ gap: 6 }}>
            <span>
              Group <span className="mono">#{g.id}</span> · boss
            </span>
            <PlayerChip player={g.boss} />
            <span>· founded {fmtDate(g.created)}</span>
            {g.description ? <span>· {g.description}</span> : null}
          </p>
        </div>
        <span className="sub" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <Wp path="game" /> Roles and members are changed in game
        </span>
      </section>

      <section className="card stats">
        <Stat label="Members" value={d.stats.members} sub={`${d.stats.online} online now`} />
        <Stat dot="gang" label="Turf held" value={d.stats.turf} sub={d.stats.contested ? `${d.stats.contested} contested` : 'None contested'} />
        <Stat label="Level" value={g.level} sub={`${fmtNumber(Math.max(0, g.xp_cap - g.xp))} XP to level ${g.level + 1}`} />
        <Stat label="Arrests this week" value={d.stats.arrests_week} sub={d.stats.arrested_members ? `Across ${d.stats.arrested_members} ${d.stats.arrested_members === 1 ? 'member' : 'members'}` : 'Nobody arrested'} />
        <Stat label="Pending invites" value={d.stats.invites} sub={oldestInvite ? `Next expires ${fmtDateTime(oldestInvite)}` : undefined} />
      </section>

      <div className="split">
        <Card className="side-col" title="Role permissions" sub="rp_gang_roles" action={<Wp path="game" />} style={{ flexBasis: 380 }}>
          {d.roles.length === 0 ? (
            <Empty title="No roles" />
          ) : (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Role</th>
                    <th style={{ textAlign: 'center' }}>Invite</th>
                    <th style={{ textAlign: 'center' }}>Kick</th>
                    <th style={{ textAlign: 'center' }}>Bank</th>
                    <th style={{ textAlign: 'center' }}>Admin</th>
                    <th className="r">Members</th>
                  </tr>
                </thead>
                <tbody>
                  {d.roles.map((r) => (
                    <tr key={r.id}>
                      <td style={{ fontWeight: 500 }}>{r.name}</td>
                      {(['invite', 'kick', 'bank', 'admin'] as const).map((k) => (
                        <td key={k} style={{ textAlign: 'center' }}>
                          <CellCheck checked={r[k]} label={`${r.name} can ${k}`} />
                        </td>
                      ))}
                      <td className="r num">{r.members}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
        <Card className="main-col" title="Members" sub="rp_gang_members">
          {d.members.length === 0 ? (
            <Empty title="No members" />
          ) : (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Member</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th className="hide-phone">Joined</th>
                    <th className="r">Jailed this week</th>
                  </tr>
                </thead>
                <tbody>
                  {d.members.map((m, i) => (
                    <tr key={m.player?.id ?? i}>
                      <td>
                        <PlayerChip player={m.player} />
                      </td>
                      <td>{m.role}</td>
                      <td>
                        <span className="status">
                          <span className={`dot ${STATUS[m.status].dot}`} />
                          {STATUS[m.status].label}
                        </span>
                      </td>
                      <td className="hide-phone" style={{ color: 'var(--muted)' }}>
                        {fmtDate(m.joined_at)}
                      </td>
                      <td className="r num">{m.jailed_week}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>

      <div className="cols">
        <Card title="Turf" sub="rp_turfs">
          {d.turf.length === 0 ? (
            <Empty title="Holds no turf" />
          ) : (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Room</th>
                    <th>Claimed</th>
                    <th>Claimed by</th>
                    <th>State</th>
                  </tr>
                </thead>
                <tbody>
                  {d.turf.map((t) => (
                    <tr key={t.room.id}>
                      <td>
                        <RoomChip id={t.room.id} name={t.room.name} />
                      </td>
                      <td>{fmtDateTime(t.claimed_at)}</td>
                      <td>{t.by ? <PlayerChip player={t.by} /> : <span className="sub">Unknown</span>}</td>
                      <td>
                        <Pill tone={t.contested ? 'warn' : ''}>{t.contested ? 'Contested' : 'Held'}</Pill>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
        <Card title="Pending invites" sub="rp_gang_invites">
          {d.invites.length === 0 ? (
            <Empty title="No pending invites" />
          ) : (
            d.invites.map((iv, i) => (
              <div key={iv.player?.id ?? i} className="row">
                <span className="grow actions" style={{ gap: 6 }}>
                  <PlayerChip player={iv.player} />
                  <span className="sub">invited by {iv.by?.username ?? 'someone'}</span>
                </span>
                <span className="mono" title={fmtDateTime(iv.expires_at)}>
                  Expires {fmtDateTime(iv.expires_at)}
                </span>
              </div>
            ))
          )}
        </Card>
      </div>
    </main>
  );
}
