import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, qs } from '@hk/lib/api';
import { fmtDate, fmtDateTime, fmtNumber } from '@hk/lib/format';
import { ActionDialog, Btn, Empty, FilterSelect, Loadable, Pill, PlayerChip, PlayerRef, SearchInput, Seg, Stat, useDebounced, Wp, fmtLeft, useNow } from '@hk/ui';
import { PlayerPicker } from '../logs/LogExplorer';

type View = 'active' | 'soon' | 'history';
interface Row { key: string; id: number; type: 'ban' | 'mute'; scope: 'user' | 'ip' | 'machine'; value: string | null; player: PlayerRef | null; also: string[]; characters: number; reason: string; staff: string; started: number | null; ends: number | null; left?: number; active: boolean }
interface Data {
  rows: Row[];
  stats: { bans: { user: number; ip: number; machine: number }; mutes: number; longest_mute: number; ending_soon: number; ending_names: string[]; issued_week: number; mutes_week: number; lifted_week: number };
  counts: Record<View, number>;
  staff: string[];
}

const SCOPE: Record<string, string> = { user: 'Account', ip: 'IP', machine: 'Machine ID' };

function Ends({ row, now }: { row: Row; now: number }) {
  if (row.type === 'mute') return <span className="num" title="A mute's clock only runs while the player is online">{fmtLeft(row.left ?? 0)} online time</span>;
  if (!row.ends) return <span className="sub">—</span>;
  if (row.ends - (row.started ?? 0) >= 315360000 - 86400) return <span className="num">Permanent</span>;
  if (!row.active) return <span className="sub">{row.ends < now ? `Ended ${fmtDate(row.ends)}` : 'Lifted'}</span>;
  return <span className="num">{fmtLeft(row.ends - now)}</span>;
}

export default function Discipline() {
  useCrumbs([{ label: 'People', to: '/players' }, { label: 'Discipline' }]);
  const { toast } = useSession();
  const navigate = useNavigate();
  const now = useNow(30000);
  const [view, setView] = useState<View>('active');
  const [staff, setStaff] = useState('');
  const [scope, setScope] = useState('');
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const [lifting, setLifting] = useState<Row | null>(null);
  const [picking, setPicking] = useState(false);
  const state = useFetch<Data>(`/discipline${qs({ view, staff, scope, q })}`);
  const s = state.data?.stats;
  const counts = state.data?.counts;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Discipline</h1>
          <p className="sub">Bans and mutes across every account · account, IP and machine bans in one list</p>
        </div>
        {picking ? (
          <span className="actions">
            <span className="sub">Who?</span>
            <PlayerPicker label="Player" onPick={(id) => navigate(`/players/${id}/discipline#ban`)} />
            <Btn variant="quiet" sm onClick={() => setPicking(false)}>
              Cancel
            </Btn>
          </span>
        ) : (
          <Btn variant="primary" onClick={() => setPicking(true)}>
            New ban or mute <Wp path="saved" />
          </Btn>
        )}
      </div>
      <section className="card stats">
        <Stat label="Active bans" value={s ? fmtNumber(s.bans.user + s.bans.ip + s.bans.machine) : '—'} sub={s ? `${s.bans.user} account · ${s.bans.ip} IP · ${s.bans.machine} machine` : undefined} />
        <Stat label="Active mutes" value={s ? fmtNumber(s.mutes) : '—'} sub={s && s.longest_mute ? `Longest has ${fmtLeft(s.longest_mute)} of online time left` : 'None'} />
        <Stat label="Ending in 24 hours" value={s ? s.ending_soon : '—'} sub={s?.ending_names.length ? s.ending_names.join(', ') : undefined} />
        <Stat label="Issued this week" value={s ? s.issued_week + s.mutes_week : '—'} sub={s ? `${s.issued_week} bans · ${s.mutes_week} mutes from the panel` : undefined} />
        <Stat label="Lifted early" value={s ? s.lifted_week : '—'} sub="This week, from the panel" />
      </section>
      <div className="filters">
        <Seg
          label="View"
          value={view}
          onChange={setView}
          options={[
            { value: 'active', label: `Active ${counts?.active ?? ''}` },
            { value: 'soon', label: `Ending soon ${counts?.soon ?? ''}` },
            { value: 'history', label: `History ${counts?.history ?? ''}` },
          ]}
        />
        <FilterSelect label="Staff" value={staff} anyLabel="Anyone" options={(state.data?.staff ?? []).map((n) => ({ value: n, label: n }))} onChange={setStaff} />
        <FilterSelect label="Scope" value={scope} options={[{ value: 'user', label: 'Account' }, { value: 'ip', label: 'IP' }, { value: 'machine', label: 'Machine ID' }, { value: 'mute', label: 'Mutes' }]} onChange={setScope} />
        <span style={{ marginLeft: 'auto' }}>
          <SearchInput value={text} onChange={setText} placeholder="Player, IP or reason" width={240} />
        </span>
      </div>
      <section className="card">
        <Loadable state={state} rows={8} empty={(d) => (d.rows.length === 0 ? <Empty title={view === 'history' ? 'No past bans match' : 'Nothing active matches'} /> : false)}>
          {(d) => (
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Scope</th>
                    <th>Reason</th>
                    <th>Staff</th>
                    <th>Started</th>
                    <th>Ends</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {d.rows.map((r) => (
                    <tr key={r.key}>
                      <td>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                          {r.player && r.player.id ? <PlayerChip player={r.player} /> : <span className="mono">{r.value ?? r.player?.username ?? '—'}</span>}
                          {r.characters > 1 ? <Pill>{r.scope === 'user' ? `${r.characters} characters` : `${r.characters} accounts`}</Pill> : null}
                        </span>
                      </td>
                      <td>
                        <Pill tone={r.type === 'ban' ? 'bad' : 'warn'}>{r.type === 'ban' ? 'Ban' : 'Mute'}</Pill>
                      </td>
                      <td>
                        {SCOPE[r.scope]}
                        {r.value && r.scope !== 'user' ? <span className="mono"> {r.scope === 'machine' ? `${r.value.slice(0, 4)}…${r.value.slice(-3)}` : r.value}</span> : null}
                      </td>
                      <td className="wrap" style={{ minWidth: 200 }}>
                        {r.reason}
                      </td>
                      <td style={{ color: 'var(--muted)' }}>{r.staff}</td>
                      <td className="mono">{r.started ? fmtDateTime(r.started) : '—'}</td>
                      <td>
                        <Ends row={r} now={now} />
                      </td>
                      <td className="r">
                        {r.type === 'ban' && r.active ? (
                          <Btn variant="quiet" sm onClick={() => setLifting(r)}>
                            Lift
                          </Btn>
                        ) : r.player && r.player.id ? (
                          <Btn variant="quiet" sm to={`/players/${r.player.id}/discipline`}>
                            View
                          </Btn>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Loadable>
        <div className="pager">
          <span>Lifting a ban saves it, then the hotel reloads bans over RCON. A reason is required. Mutes change on the player’s page.</span>
          <Wp path="saved" label="Saved, then reloaded" />
        </div>
      </section>
      {lifting ? (
        <ActionDialog
          title={lifting.scope === 'user' ? `Lift the ban on ${lifting.player?.username ?? 'this account'}` : `Lift the ${SCOPE[lifting.scope]} ban`}
          wp="saved"
          cta="Lift ban"
          danger
          note={lifting.scope === 'user' ? 'Lifts every active ban on the account’s characters. The rows stay as history.' : 'The ban row stays as history. The hotel reloads bans through RCON.'}
          onClose={() => setLifting(null)}
          onSubmit={async (reason) => {
            const r =
              lifting.scope === 'user' && lifting.player && lifting.player.id
                ? await post<{ message: string }>(`/players/${lifting.player.id}/unban`, { reason })
                : await post<{ message: string }>(`/discipline/bans/${lifting.id}/lift`, { reason });
            toast('ok', r.message);
            state.reload();
          }}
        />
      ) : null}
    </main>
  );
}
