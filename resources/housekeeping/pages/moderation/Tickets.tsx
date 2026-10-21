import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, qs } from '@hk/lib/api';
import { fmtClock, relTime } from '@hk/lib/format';
import { ActionDialog, Btn, Empty, Field, Loadable, Note, Pill, PlayerChip, PlayerRef, RoomChip, Seg, Tone, Wp } from '@hk/ui';

type Group = 'open' | 'picked' | 'closed';
interface Ticket { id: number; status: string; group: Group; score: number; category: string; reporter: PlayerRef | null; reported: PlayerRef | null; moderator: PlayerRef | null; message: string; room: { id: number; name: string } | null; at: number }
interface Detail extends Ticket { attached: string[]; chat: { id: number; at: number; player: PlayerRef | null; text: string; reported: boolean }[] }

const STATUS: Record<string, { label: string; tone: Tone }> = {
  open: { label: 'Open', tone: 'warn' },
  picked: { label: 'Picked up', tone: 'info' },
  resolved: { label: 'Resolved', tone: 'ok' },
  abusive: { label: 'False report', tone: '' },
  invalid: { label: 'No action needed', tone: '' },
  deleted: { label: 'Deleted', tone: '' },
};
const age = (ts: number) => relTime(ts).replace(' ago', '');

export default function Tickets() {
  useCrumbs([{ label: 'Moderation', to: '/moderation/support' }, { label: 'Tickets' }]);
  const { able } = useSession();
  const navigate = useNavigate();
  const [group, setGroup] = useState<Group>('open');
  const [sel, setSel] = useState<number | null>(null);
  const [alerting, setAlerting] = useState<PlayerRef | null>(null);
  const list = useFetch<{ tickets: Ticket[]; counts: Record<Group, number> }>(`/tickets${qs({ status: group })}`);
  const tickets = list.data?.tickets ?? [];
  const current = sel ?? tickets[0]?.id ?? null;
  const detail = useFetch<Detail>(current ? `/tickets/${current}` : null);
  const d = detail.data && detail.data.id === current ? detail.data : null;
  const counts = list.data?.counts;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Moderation tickets</h1>
          <p className="sub">Player reports from the in-game report button · moderation_tickets</p>
        </div>
        <Seg
          label="Status"
          value={group}
          onChange={(g) => {
            setGroup(g);
            setSel(null);
          }}
          options={[
            { value: 'open', label: `Open ${counts?.open ?? ''}` },
            { value: 'picked', label: `Picked up ${counts?.picked ?? ''}` },
            { value: 'closed', label: 'Closed' },
          ]}
        />
      </div>
      <div className="split">
        <section className="card main-col">
          <Loadable state={list} rows={6} empty={(x) => (x.tickets.length === 0 ? <Empty title={group === 'closed' ? 'No closed tickets' : 'No tickets waiting'} body="Reports from the in-game report button show up here." /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Ticket</th>
                      <th>Reported</th>
                      <th>By</th>
                      <th>Category</th>
                      <th className="hide-phone">Room</th>
                      <th>Status</th>
                      <th className="r">Age</th>
                    </tr>
                  </thead>
                  <tbody>
                    {x.tickets.map((t) => (
                      <tr key={t.id} className={`click ${t.id === current ? 'sel' : ''}`} onClick={() => setSel(t.id)}>
                        <td>
                          <button type="button" className="mono" onClick={() => setSel(t.id)} style={{ border: 0, background: 'none', padding: 0, color: 'var(--ink)', cursor: 'pointer' }}>
                            #{t.id}
                          </button>
                        </td>
                        <td style={{ fontWeight: 500 }}>{t.reported?.username ?? 'Unknown'}</td>
                        <td style={{ color: 'var(--muted)' }}>{t.reporter?.username ?? 'Unknown'}</td>
                        <td>
                          <Pill>{t.category}</Pill>
                        </td>
                        <td className="hide-phone">{t.room?.name ?? <span className="sub">Not in a room</span>}</td>
                        <td>
                          <Pill tone={STATUS[t.status]?.tone ?? ''}>{STATUS[t.status]?.label ?? t.status}</Pill>
                        </td>
                        <td className="r mono">{age(t.at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
        {current ? (
          <aside className="peek side-col" style={{ flexBasis: 420 }} aria-label="Ticket detail">
            {!d ? (
              <Loadable state={detail}>{() => null}</Loadable>
            ) : (
              <>
                <div className="card-h">
                  <div>
                    <h3>
                      #{d.id} · {d.reported?.username ?? 'Unknown'}
                    </h3>
                    <p className="sub">
                      Reported by {d.reporter?.username ?? 'unknown'}
                      {d.room ? ` in ${d.room.name}` : ''} · {age(d.at)} ago
                      {d.moderator ? ` · picked up by ${d.moderator.username}` : ''}
                    </p>
                  </div>
                  <Pill tone={STATUS[d.status]?.tone ?? ''}>{STATUS[d.status]?.label ?? d.status}</Pill>
                </div>
                <div className="card-b stack16" style={{ gap: 8, borderBottom: '1px solid var(--line)' }}>
                  <div className="actions" style={{ gap: 6 }}>
                    <PlayerChip player={d.reported} />
                    <span className="sub">reported by</span>
                    <PlayerChip player={d.reporter} />
                    {d.room ? <RoomChip id={d.room.id} name={d.room.name} /> : null}
                  </div>
                  <span style={{ fontSize: 13, fontWeight: 500 }}>Message from the reporter</span>
                  <p style={{ overflowWrap: 'anywhere' }}>{d.message || <span className="sub">No message</span>}</p>
                  {d.attached.length ? (
                    <>
                      <span style={{ fontSize: 13, fontWeight: 500 }}>Lines they attached</span>
                      {d.attached.map((l, i) => (
                        <p key={i} className="sub" style={{ overflowWrap: 'anywhere' }}>
                          “{l}”
                        </p>
                      ))}
                    </>
                  ) : null}
                </div>
                <div className="dayhead">Chat in the room around the report</div>
                {d.chat.length === 0 ? (
                  <Empty title="No room chat around the report" body={d.room ? 'Nobody spoke in the room in the five minutes either side.' : 'The report was not made from a room.'} />
                ) : (
                  d.chat.map((m) => (
                    <div key={m.id} style={{ display: 'grid', gridTemplateColumns: '64px 96px minmax(0, 1fr)', gap: 10, padding: '7px 20px', borderBottom: '1px solid var(--line)', background: m.reported ? 'var(--warn-soft)' : 'transparent', fontSize: 13 }}>
                      <span className="mono">{fmtClock(m.at)}</span>
                      <span className="ell" style={{ fontWeight: 500 }}>
                        {m.player?.username ?? 'Unknown'}
                      </span>
                      <span style={{ overflowWrap: 'anywhere' }}>{m.text}</span>
                    </div>
                  ))
                )}
                <div className="card-b stack16" style={{ gap: 10 }}>
                  <div className="actions">
                    {d.reported ? (
                      <Btn variant="primary" onClick={() => navigate(`/players/${d.reported!.id}/discipline#ban`)}>
                        Mute or ban {d.reported.username}
                      </Btn>
                    ) : null}
                    {d.reported && able('command_alert_user') ? (
                      <Btn wp="live" onClick={() => setAlerting(d.reported)}>
                        Alert {d.reported.username}
                      </Btn>
                    ) : null}
                  </div>
                  {d.group !== 'closed' ? (
                    <Note icon="game" title="Pick up and close in the mod tool">
                      The emulator keeps open tickets in memory, so their status changes in game (<Wp path="game" />). A change written here would be lost.
                    </Note>
                  ) : null}
                </div>
              </>
            )}
          </aside>
        ) : null}
      </div>
      {alerting ? <AlertPlayer player={alerting} onClose={() => setAlerting(null)} /> : null}
    </main>
  );
}

function AlertPlayer({ player, onClose }: { player: PlayerRef; onClose: () => void }) {
  const { toast } = useSession();
  const [message, setMessage] = useState('');
  return (
    <ActionDialog
      title={`Alert ${player.username}`}
      wp="live"
      cta="Send alert"
      reason={false}
      valid={message.trim().length >= 2}
      note="Shown in their client at once, if they are online. Logged with your name."
      onClose={onClose}
      onSubmit={async () => {
        const r = await post<{ message: string }>(`/players/${player.id}/alert`, { message });
        toast('ok', r.message);
      }}
    >
      <Field label="Message">
        <textarea className="input" rows={3} value={message} onChange={(e) => setMessage(e.target.value)} maxLength={500} autoFocus />
      </Field>
    </ActionDialog>
  );
}
