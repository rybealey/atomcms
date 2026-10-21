import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useFetch } from '@hk/lib/useFetch';
import { qs } from '@hk/lib/api';
import { fmtDateTime, fmtNumber, relTime } from '@hk/lib/format';
import { Btn, Card, CorpChip, Empty, GangChip, Kv, Loadable, Pill, PlayerChip, PlayerRef, SearchInput, Skeleton, Tone, Wp, useDebounced } from '@hk/ui';

type Role = 'hq' | 'turf' | 'jail' | 'arrest' | 'safe';
interface RoomRow {
  id: number;
  name: string;
  owner: string;
  now: number;
  roles: Role[];
  corporation: { id: number; name: string } | null;
  gang: { id: number; name: string; claimed_at: number; contested: boolean } | null;
  access: string[];
}
interface RoomsData {
  world: { label: string; state: string; pill: string; tone: Tone; detail: string; key: string }[];
  counts: Record<string, number>;
  rooms: RoomRow[];
}
interface RoomDetail extends RoomRow {
  description: string;
  owner_ref: PlayerRef | null;
  state: string;
  max: number;
  furni: number;
  hq_ranks: string[];
  claimed_by: PlayerRef | null;
  chat_today: number;
  bans: number;
}

const ROLES: { id: 'all' | Role; label: string; dot: string }[] = [
  { id: 'all', label: 'All', dot: '' },
  { id: 'hq', label: 'HQ', dot: 'corp' },
  { id: 'turf', label: 'Turf', dot: 'gang' },
  { id: 'jail', label: 'Jail', dot: 'police' },
  { id: 'arrest', label: 'Arrest', dot: 'police' },
  { id: 'safe', label: 'Safe zone', dot: 'ok' },
];
const TAG: Record<Role, { label: string; dot: string }> = { hq: { label: 'HQ', dot: 'corp' }, turf: { label: 'Turf', dot: 'gang' }, jail: { label: 'Jail', dot: 'police' }, arrest: { label: 'Arrest', dot: 'police' }, safe: { label: 'Safe zone', dot: 'ok' } };

function RoleTags({ roles }: { roles: Role[] }) {
  if (roles.length === 0) return <span className="sub">None</span>;
  return (
    <span style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
      {roles.map((r) => (
        <span key={r} className="tag">
          <span className={`dot ${TAG[r].dot}`} />
          {TAG[r].label}
        </span>
      ))}
    </span>
  );
}

function LinkedTo({ room }: { room: RoomRow }) {
  if (room.corporation) return <CorpChip id={room.corporation.id} name={room.corporation.name} />;
  if (room.gang)
    return (
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
        <GangChip id={room.gang.id} name={room.gang.name} />
        {room.gang.contested ? <Pill tone="warn">Contested</Pill> : null}
      </span>
    );
  return <span className="sub">None</span>;
}

export default function Rooms() {
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Rooms and world' }]);
  const [params, setParams] = useSearchParams();
  const [role, setRole] = useState<'all' | Role>('all');
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const state = useFetch<RoomsData>(`/rooms${qs({ role, q })}`);
  const selected = Number(params.get('room') ?? 0) || null;
  const d = state.data;

  const select = (id: number | null) => {
    const next = new URLSearchParams(params);
    if (id) next.set('room', String(id));
    else next.delete('room');
    setParams(next, { replace: true });
  };

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Rooms and world</h1>
          <p className="sub">City-wide switches and every room with a roleplay role</p>
        </div>
      </div>

      <Card title="World switches" sub="server_settings · changed in the City Panel today, RCON commands planned" action={<Wp path="game" />}>
        <Loadable state={state} rows={2}>
          {(x) => (
            <div className="stats" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
              {x.world.map((s) => (
                <div key={s.key} className="stat">
                  <span className="l">{s.label}</span>
                  <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span className="w">{s.state}</span>
                    <Pill tone={s.tone}>{s.pill}</Pill>
                  </span>
                  <span className="sub">{s.detail}</span>
                  <span className="mono" style={{ fontSize: 11 }}>
                    {s.key}
                  </span>
                </div>
              ))}
            </div>
          )}
        </Loadable>
      </Card>

      <div className="filters">
        {ROLES.map((r) => (
          <button key={r.id} type="button" className={`select ${role === r.id && !q ? 'on' : ''}`} aria-pressed={role === r.id} onClick={() => { setRole(r.id); setText(''); }}>
            {r.dot ? <span className={`dot ${r.dot}`} /> : null}
            {r.label}{' '}
            <span className="mono" style={{ fontSize: 11 }}>
              {d?.counts[r.id] ?? ''}
            </span>
          </button>
        ))}
        <span style={{ marginLeft: 'auto' }}>
          <SearchInput value={text} onChange={setText} placeholder="Room name or ID" width={220} />
        </span>
      </div>

      <div className="split">
        <section className="card main-col">
          <Loadable state={state} rows={8} empty={(x) => (x.rooms.length === 0 ? <Empty title={q ? `No room matches “${q}”` : 'No rooms with this role'} /> : false)}>
            {(x) => (
              <>
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th>Room</th>
                        <th className="hide-phone">ID</th>
                        <th>Roles</th>
                        <th>Linked to</th>
                        <th className="hide-phone">Owner</th>
                        <th className="hide-phone">Who can enter on duty</th>
                        <th className="r">In the room now</th>
                      </tr>
                    </thead>
                    <tbody>
                      {x.rooms.map((m) => (
                        <tr key={m.id} className={`click ${selected === m.id ? 'sel' : ''}`} onClick={() => select(m.id)}>
                          <td style={{ fontWeight: 500 }}>{m.name}</td>
                          <td className="mono hide-phone">{m.id}</td>
                          <td>
                            <RoleTags roles={m.roles} />
                          </td>
                          <td onClick={(e) => e.stopPropagation()}>
                            <LinkedTo room={m} />
                          </td>
                          <td className="hide-phone" style={{ color: 'var(--muted)' }}>
                            {m.owner}
                          </td>
                          <td className="hide-phone">
                            <span style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                              {m.access.length ? m.access.map((a) => <Pill key={a}>{a}</Pill>) : <span className="sub">Nobody</span>}
                            </span>
                          </td>
                          <td className="r num">{m.now}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="pager">
                  <span>
                    {fmtNumber(x.rooms.length)} rooms · flags from rooms.is_safe_zone, is_turf, rp_jail_room, rp_arrest_room, allow_police, allow_medical, allow_staff
                  </span>
                  <Wp path="game" label="Edit flags in game" />
                </div>
              </>
            )}
          </Loadable>
        </section>
        {selected ? <RoomPeek id={selected} onClose={() => select(null)} /> : null}
      </div>
    </main>
  );
}

function RoomPeek({ id, onClose }: { id: number; onClose: () => void }) {
  const state = useFetch<RoomDetail>(`/rooms/${id}`);
  const r = state.data;
  return (
    <aside className="peek side-col" aria-label="Room detail">
      <div className="card-h">
        <div style={{ minWidth: 0 }}>
          <h3 className="ell">{r?.name ?? `Room ${id}`}</h3>
          <p className="sub">
            Room <span className="mono">#{id}</span>
            {r ? ` · ${r.state} · ${r.now} of ${r.max} here` : ''}
          </p>
        </div>
        <Btn variant="quiet" sm iconOnly icon="close" aria-label="Close room detail" onClick={onClose} />
      </div>
      {state.error && !r ? (
        <div className="card-b">
          <p className="sub">{state.error}</p>
        </div>
      ) : !r ? (
        <Skeleton rows={5} />
      ) : (
        <>
          <div className="card-b stack16">
            <RoleTags roles={r.roles} />
            {r.description ? <p className="sub">{r.description}</p> : null}
            <Kv
              rows={[
                ['Owner', r.owner_ref ? <PlayerChip key="o" player={r.owner_ref} /> : r.owner],
                ['Linked to', <LinkedTo key="l" room={r} />],
                ...(r.gang ? ([['Claimed', <span key="c">{fmtDateTime(r.gang.claimed_at)}{r.claimed_by ? ` by ${r.claimed_by.username}` : ''}</span>]] as [string, React.ReactNode][]) : []),
                ...(r.hq_ranks.length ? ([['Ranks allowed in', r.hq_ranks.join(', ')]] as [string, React.ReactNode][]) : []),
                ['On duty access', r.access.length ? r.access.join(', ') : 'Nobody'],
                ['Furni placed', <span className="num" key="f">{fmtNumber(r.furni)}</span>],
                ['Chat lines today', <span className="num" key="t">{fmtNumber(r.chat_today)}</span>],
                ['Active room bans', <span className="num" key="b">{r.bans}</span>],
              ]}
            />
            {r.gang?.contested ? <p className="sub">Turf changed hands {relTime(r.gang.claimed_at)}.</p> : null}
          </div>
          <div className="card-f" style={{ justifyContent: 'flex-start', flexWrap: 'wrap' }}>
            <Btn to={`/logs?room=${id}`}>Room in log explorer</Btn>
            <Btn to={`/moderation/chat?room=${id}`}>Chat logs</Btn>
          </div>
        </>
      )}
    </aside>
  );
}
