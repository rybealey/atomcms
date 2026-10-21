import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put } from '@hk/lib/api';
import { fmtDay, fmtNumber, fmtResponse, relTime } from '@hk/lib/format';
import { ActionDialog, Avatar, Btn, Empty, Field, Kv, Loadable, Pill, PlayerRef, Wp } from '@hk/ui';
import { PlayerPicker } from '../logs/LogExplorer';

interface StaffRow { player: PlayerRef; rank: number; rank_name: string; team: { id: number; name: string } | null; online: boolean; last_online: number; actions_week: number; threads: number; missed: number; rotation: boolean }
interface Data { staff: StaffRow[]; ranks: { id: number; name: string }[]; teams: { id: number; name: string }[]; min_staff_rank: number }
interface Detail { id: number; since: number | null; last_online: number; bans_week: number; coins_week: number; average_reply: number | null }

export default function Staff() {
  useCrumbs([{ label: 'Admin', to: '/admin/access' }, { label: 'Staff' }]);
  const { can } = useSession();
  const state = useFetch<Data>('/staff');
  const [sel, setSel] = useState<number | null>(null);
  const [adding, setAdding] = useState<number | null>(null);
  const d = state.data;
  const cur = d?.staff.find((s) => s.player.id === sel) ?? d?.staff[0] ?? null;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Staff</h1>
          <p className="sub">{d ? `${d.staff.length} staff · ${d.staff.filter((s) => s.online).length} online · anyone at rank ${d.min_staff_rank} and up` : 'Loading'}</p>
        </div>
        {can('manage_housekeeping_permissions') ? <PlayerPicker label="Add staff member" onPick={(id) => setAdding(id)} /> : null}
      </div>
      <div className="split">
        <section className="card main-col">
          <Loadable state={state} rows={8} empty={(x) => (x.staff.length === 0 ? <Empty title="No staff" /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Staff</th>
                      <th>Rank</th>
                      <th className="hide-phone">Team</th>
                      <th>Status</th>
                      <th className="r">Actions, 7 days</th>
                      <th className="r hide-phone">Threads handled</th>
                      <th className="r hide-phone">Missed offers</th>
                    </tr>
                  </thead>
                  <tbody>
                    {x.staff.map((s) => (
                      <tr key={s.player.id} className={`click ${cur?.player.id === s.player.id ? 'sel' : ''}`} onClick={() => setSel(s.player.id)}>
                        <td>
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontWeight: 500 }}>
                            <Avatar look={s.player.look} size={30} />
                            {s.player.username}
                          </span>
                        </td>
                        <td>
                          <Pill>
                            Rank {s.rank} · {s.rank_name}
                          </Pill>
                        </td>
                        <td className="hide-phone" style={{ color: 'var(--muted)' }}>
                          {s.team?.name ?? '—'}
                        </td>
                        <td>
                          <span className="status">
                            <span className={`dot ${s.online ? 'ok' : ''}`} />
                            {s.online ? 'Online' : relTime(s.last_online)}
                          </span>
                        </td>
                        <td className="r num">{s.actions_week}</td>
                        <td className="r num hide-phone">{s.threads}</td>
                        <td className="r num hide-phone">{s.missed}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
        {cur && d ? <StaffPanel key={cur.player.id} row={cur} data={d} onSaved={state.reload} /> : null}
      </div>
      {adding !== null && d ? (
        <AddStaff
          id={adding}
          data={d}
          onClose={() => setAdding(null)}
          onDone={() => {
            state.reload();
            setSel(adding);
          }}
        />
      ) : null}
    </main>
  );
}

function StaffPanel({ row, data, onSaved }: { row: StaffRow; data: Data; onSaved: () => void }) {
  const { me, can } = useSession();
  const detail = useFetch<Detail>(`/staff/${row.player.id}`);
  const [rank, setRank] = useState(row.rank);
  const [team, setTeam] = useState(row.team?.id ?? 0);
  const [rotation, setRotation] = useState(row.rotation);
  const [saving, setSaving] = useState<'save' | 'remove' | null>(null);
  useEffect(() => {
    setRank(row.rank);
    setTeam(row.team?.id ?? 0);
    setRotation(row.rotation);
  }, [row]);
  const editable = can('manage_housekeeping_permissions') && row.rank < me.user.rank;
  const dirty = rank !== row.rank || team !== (row.team?.id ?? 0) || rotation !== row.rotation;
  const x = detail.data;

  return (
    <aside className="peek side-col" aria-label="Staff member" style={{ flexBasis: 360 }}>
      <div className="card-h">
        <div>
          <h3>{row.player.username}</h3>
          <p className="sub">
            {x?.since ? `Staff actions since ${fmtDay(x.since)}` : 'No panel actions yet'} · last active {x ? relTime(x.last_online) : '…'}
          </p>
        </div>
        <Btn sm to={`/players/${row.player.id}`}>
          Player 360
        </Btn>
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <Field label="Rank">
          <select className="input" value={rank} disabled={!editable} onChange={(e) => setRank(Number(e.target.value))}>
            {data.ranks.filter((r) => r.id < me.user.rank || r.id === row.rank).map((r) => (
              <option key={r.id} value={r.id}>
                Rank {r.id} · {r.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Team" hint="Teams group staff on the public staff page.">
          <select className="input" value={team} disabled={!editable} onChange={(e) => setTeam(Number(e.target.value))}>
            <option value={0}>No team</option>
            {data.teams.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>
        </Field>
        <label className="check">
          <input type="checkbox" checked={rotation} disabled={!editable} onChange={(e) => setRotation(e.target.checked)} />
          In the support rotation
        </label>
        <Kv
          rows={[
            ['Bans this week', <span className="num" key="b">{x ? x.bans_week : '…'}</span>],
            ['Coins given this week', <span className="num" key="c">{x ? fmtNumber(x.coins_week) : '…'}</span>],
            ['Average reply in support', <span className="num" key="r">{x ? (x.average_reply === null ? 'None' : fmtResponse(x.average_reply)) : '…'}</span>],
          ]}
        />
        <Link to={`/logs/audit?staff=${row.player.id}`} style={{ fontSize: 13 }}>
          See everything {row.player.username} did
        </Link>
      </div>
      {editable ? (
        <div className="card-f" style={{ justifyContent: 'space-between' }}>
          <Btn variant="danger" onClick={() => setSaving('remove')}>
            Remove from staff
          </Btn>
          <span className="actions">
            <Wp path={rank !== row.rank ? 'live' : 'saved'} />
            <Btn variant="primary" disabled={!dirty} onClick={() => setSaving('save')}>
              Save
            </Btn>
          </span>
        </div>
      ) : null}
      {saving ? (
        <ActionDialog
          title={saving === 'remove' ? `Remove ${row.player.username} from staff` : `Save ${row.player.username}`}
          wp={saving === 'remove' || rank !== row.rank ? 'live' : 'saved'}
          cta={saving === 'remove' ? 'Remove from staff' : 'Save'}
          danger={saving === 'remove'}
          note={saving === 'remove' ? 'Their rank goes back to 1 and the hotel re-reads it at once. Their audit history stays.' : rank !== row.rank ? 'The rank is written, then the hotel re-reads it over RCON.' : undefined}
          onClose={() => setSaving(null)}
          onSubmit={async (reason) => {
            await put(`/staff/${row.player.id}`, saving === 'remove' ? { rank: 1, rotation: false, reason } : { rank, team_id: team, rotation, reason });
            onSaved();
          }}
        />
      ) : null}
    </aside>
  );
}

function AddStaff({ id, data, onClose, onDone }: { id: number; data: Data; onClose: () => void; onDone: () => void }) {
  const { me, toast } = useSession();
  const card = useFetch<{ username: string }>(`/players/${id}/card`);
  const [rank, setRank] = useState(data.min_staff_rank);
  return (
    <ActionDialog
      title={`Add ${card.data?.username ?? 'player'} to staff`}
      wp="live"
      cta="Add to staff"
      note="The rank is written, then the hotel re-reads it over RCON."
      onClose={onClose}
      onSubmit={async (reason) => {
        const r = await put<{ message: string }>(`/staff/${id}`, { rank, reason });
        toast('ok', r.message);
        onDone();
      }}
    >
      <Field label="Rank">
        <select className="input" value={rank} onChange={(e) => setRank(Number(e.target.value))}>
          {data.ranks.filter((r) => r.id >= data.min_staff_rank && r.id < me.user.rank).map((r) => (
            <option key={r.id} value={r.id}>
              Rank {r.id} · {r.name}
            </option>
          ))}
        </select>
      </Field>
    </ActionDialog>
  );
}
