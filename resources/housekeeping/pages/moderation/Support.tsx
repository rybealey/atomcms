import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, qs } from '@hk/lib/api';
import { fmtClock, fmtDayHead, relTime } from '@hk/lib/format';
import { Avatar, Btn, Card, Empty, FilterSelect, Icon, Loadable, Note, Pill, PlayerChip, PlayerRef, RoomChip, Seg, Tone, Wp } from '@hk/ui';

type Status = 'waiting' | 'offered' | 'open' | 'resolved' | 'mine';
interface Thread { id: number; player: PlayerRef | null; staff: PlayerRef | null; category: string; status: Exclude<Status, 'mine'>; subject: string; offers: number; created_at: number; updated_at: number; resolved_at: number }
interface Rotation { player: PlayerRef | null; me: boolean; state: 'available' | 'busy' | 'away' | 'offline'; open: number; last_offered_at: number; missed: number }
interface QueueData { threads: Thread[]; counts: Record<Status, number>; categories: string[]; rotation: Rotation[]; can_answer: boolean }
interface Detail { id: number; player: PlayerRef | null; staff: PlayerRef | null; category: string; status: Thread['status']; created_at: number; resolved_at: number; online: boolean; room: { id: number; name: string } | null; messages: { id: number; author: PlayerRef | null; staff: boolean; body: string; at: number }[] }

export const STATUS: Record<Thread['status'], { label: string; tone: Tone }> = {
  waiting: { label: 'Waiting', tone: 'warn' },
  offered: { label: 'Offered', tone: 'info' },
  open: { label: 'Active', tone: 'brand' },
  resolved: { label: 'Resolved', tone: 'ok' },
};
const CATEGORY: Record<string, string> = { report: 'Report', broken: 'Something broke', appeal: 'Appeal', other: 'Other' };
const ROTATION: Record<Rotation['state'], { label: string; tone: Tone; dot: string }> = {
  available: { label: 'Available', tone: 'ok', dot: 'ok' },
  busy: { label: 'Answering', tone: 'warn', dot: 'warn' },
  away: { label: 'Away', tone: '', dot: '' },
  offline: { label: 'Offline', tone: '', dot: '' },
};
const age = (ts: number) => relTime(ts).replace(' ago', '');

export default function Support() {
  useCrumbs([{ label: 'Moderation', to: '/moderation/support' }, { label: 'Support queue' }]);
  const [params, setParams] = useSearchParams();
  const [status, setStatus] = useState<Status>('waiting');
  const [category, setCategory] = useState('');
  const queue = useFetch<QueueData>(`/support${qs({ status, category })}`);
  const fromLink = Number(params.get('thread')) || null;
  const [sel, setSel] = useState<number | null>(fromLink);
  // On a phone the list and the thread are separate screens.
  const [phoneThread, setPhoneThread] = useState<boolean>(fromLink !== null);
  const threads = queue.data?.threads ?? [];
  const current = sel ?? threads[0]?.id ?? null;
  const counts = queue.data?.counts;
  const me = queue.data?.rotation.find((r) => r.me);

  useEffect(() => {
    if (fromLink) {
      const next = new URLSearchParams(params);
      next.delete('thread');
      setParams(next, { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const pick = (id: number) => {
    setSel(id);
    setPhoneThread(true);
  };

  return (
    <main className="page">
      <div className={`ph ${phoneThread ? 'hide-phone' : ''}`}>
        <div>
          <h1>Support queue</h1>
          <p className="sub">
            Player threads from the in-game help button · rp_support_threads
            {me ? <> · <Pill tone={ROTATION[me.state].tone}>{me.state === 'available' ? 'You’re available' : `You’re ${ROTATION[me.state].label.toLowerCase()}`}</Pill></> : null}
          </p>
        </div>
        <Seg
          label="Status"
          value={status}
          onChange={(s) => {
            setStatus(s);
            setSel(null);
          }}
          options={(['waiting', 'offered', 'open', 'resolved', 'mine'] as Status[]).map((s) => ({ value: s, label: `${s === 'mine' ? 'Mine' : STATUS[s].label} ${counts ? counts[s] : ''}` }))}
        />
      </div>
      <div className="split">
        <section className={`card ${phoneThread ? 'hide-phone' : ''}`} style={{ flex: '1 1 320px' }} aria-label="Threads">
          <div className="card-h">
            <FilterSelect label="Category" value={category} anyLabel="All" options={(queue.data?.categories ?? []).map((c) => ({ value: c, label: CATEGORY[c] ?? c }))} onChange={(v) => { setCategory(v); setSel(null); }} />
            <span className="sub">{status === 'resolved' ? 'Newest first' : 'Oldest first'}</span>
          </div>
          <Loadable state={queue} rows={5} empty={(d) => (d.threads.length === 0 ? <Empty title="Nothing here" body="Threads with this status show up here." /> : false)}>
            {(d) =>
              d.threads.map((t) => (
                <button key={t.id} type="button" className={`row ${t.id === current ? 'on' : ''}`} aria-pressed={t.id === current} onClick={() => pick(t.id)} style={{ alignItems: 'flex-start', minHeight: 72 }}>
                  <Avatar look={t.player?.look} size={30} />
                  <span className="grow" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                    <span style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                      <span style={{ fontWeight: 500 }}>{t.player?.username ?? 'Unknown player'}</span>
                      <span className="mono">{age(t.status === 'resolved' ? t.resolved_at : t.created_at)}</span>
                    </span>
                    <span className="ell" style={{ fontSize: 13 }}>
                      {t.subject}
                    </span>
                    <span style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                      <Pill>{CATEGORY[t.category] ?? t.category}</Pill>
                      <Pill tone={STATUS[t.status].tone}>{STATUS[t.status].label}</Pill>
                      {t.staff ? <span className="sub">{t.staff.username}</span> : null}
                      {t.offers > 0 ? <span className="sub">{t.offers} lapsed {t.offers === 1 ? 'offer' : 'offers'}</span> : null}
                    </span>
                  </span>
                </button>
              ))
            }
          </Loadable>
        </section>
        <div className={phoneThread ? '' : 'hide-phone'} style={{ flex: '999 1 520px', minWidth: 0 }}>
          {current ? (
            <ThreadPane
              key={current}
              id={current}
              canAnswer={queue.data?.can_answer ?? false}
              onBack={() => setPhoneThread(false)}
              onChanged={() => {
                // Keep the thread open even when it moves to another status.
                setSel(current);
                queue.reload();
              }}
            />
          ) : (
            <section className="card">
              <Empty title="Pick a thread" />
            </section>
          )}
        </div>
        <Card className={phoneThread ? 'hide-phone' : ''} style={{ flex: '1 1 280px' }} title="Rotation" sub="Who gets the next thread">
          <Loadable state={queue} empty={(d) => (d.rotation.length === 0 ? <Empty title="Nobody on the rotation" body="Staff join it from the Support app in game." /> : false)}>
            {(d) => (
              <>
                {d.rotation.map((r, i) => (
                  <div key={r.player?.id ?? i} className="row">
                    <span className={`dot ${ROTATION[r.state].dot}`} />
                    <div className="grow">
                      <p style={{ fontWeight: 500 }}>
                        {r.player?.username ?? 'Unknown'}
                        {r.me ? <span className="sub"> · you</span> : null}
                      </p>
                      <p className="sub">Last offered {r.last_offered_at ? relTime(r.last_offered_at) : 'never'}</p>
                    </div>
                    <span style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 }}>
                      <Pill tone={ROTATION[r.state].tone}>{r.state === 'busy' ? `${ROTATION.busy.label} ${r.open}` : ROTATION[r.state].label}</Pill>
                      <span className="mono" style={{ fontSize: 11 }}>
                        {r.missed} missed
                      </span>
                    </span>
                  </div>
                ))}
                <div className="row">
                  <span className="sub">From rp_support_rotation · staff switch themselves on and off in the Support app</span>
                </div>
              </>
            )}
          </Loadable>
        </Card>
      </div>
    </main>
  );
}

const QUICK = ['I’m on my way', 'Try relogging', 'Thanks, looking into it now'];

function ThreadPane({ id, canAnswer, onBack, onChanged }: { id: number; canAnswer: boolean; onBack: () => void; onChanged: () => void }) {
  const { toast, confirm } = useSession();
  const navigate = useNavigate();
  const state = useFetch<Detail>(`/support/${id}`);
  const [reply, setReply] = useState('');
  const [busy, setBusy] = useState(false);
  const d = state.data;
  const send = async () => {
    if (!reply.trim()) return;
    setBusy(true);
    try {
      const r = await post<{ message: string }>(`/support/${id}/reply`, { body: reply });
      setReply('');
      toast('ok', 'Reply saved', r.message.replace(/^Reply saved\. /, ''));
      state.reload();
      onChanged();
    } catch (e) {
      toast('bad', 'Could not send', e instanceof Error ? e.message : undefined);
    } finally {
      setBusy(false);
    }
  };
  const resolve = async () => {
    if (!(await confirm({ title: 'Resolve this thread?', body: 'It leaves the queue. The player can open a new one from the Support app.', cta: 'Resolve' }))) return;
    try {
      await post(`/support/${id}/resolve`, {});
      toast('ok', 'Thread resolved');
      state.reload();
      onChanged();
    } catch (e) {
      toast('bad', 'Could not resolve', e instanceof Error ? e.message : undefined);
    }
  };

  let day = '';
  return (
    <section className="card" aria-label="Thread">
      <Loadable state={state} rows={6}>
        {() =>
          d ? (
            <>
              <div className="card-h">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', minWidth: 0 }}>
                  <button type="button" className="btn quiet sm only-phone" onClick={onBack}>
                    <Icon name="chevron-left" /> Queue
                  </button>
                  <PlayerChip player={d.player} />
                  <h3 className="ell">{d.messages[0]?.body.slice(0, 80) ?? 'Support thread'}</h3>
                </div>
                <div className="actions">
                  <Pill>{CATEGORY[d.category] ?? d.category}</Pill>
                  <Pill tone={STATUS[d.status].tone}>{STATUS[d.status].label}</Pill>
                </div>
              </div>
              <div className="row" style={{ background: 'var(--sunken)', flexWrap: 'wrap' }}>
                <span className="sub" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                  {d.online ? (d.room ? <>Online in <RoomChip id={d.room.id} name={d.room.name} /></> : 'Online') : 'Offline'} · opened {relTime(d.created_at)} · thread <span className="mono">#{d.id}</span>
                  {d.staff ? <> · with {d.staff.username}</> : null}
                </span>
                <span className="grow" />
                {d.player ? (
                  <Link to={`/players/${d.player.id}/timeline`} style={{ fontSize: 13 }}>
                    Recent activity
                  </Link>
                ) : null}
              </div>
              {d.messages.length === 0 ? <Empty title="No messages yet" /> : null}
              {d.messages.map((m) => {
                const head = fmtDayHead(m.at);
                const show = head !== day;
                day = head;
                return (
                  <div key={m.id}>
                    {show ? <div className="dayhead">{head}</div> : null}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4, padding: '12px 20px', borderBottom: '1px solid var(--line)', background: m.staff ? 'var(--sunken)' : 'transparent' }}>
                      <span style={{ display: 'flex', gap: 8, alignItems: 'baseline' }}>
                        <span style={{ fontWeight: 500, color: m.staff ? 'var(--brand-ink)' : 'var(--ink)' }}>
                          {m.author?.username ?? 'Unknown'}
                          {m.staff ? ' (staff)' : ''}
                        </span>
                        <span className="mono" style={{ fontSize: 11 }}>
                          {fmtClock(m.at)}
                        </span>
                      </span>
                      <span style={{ overflowWrap: 'anywhere' }}>{m.body}</span>
                    </div>
                  </div>
                );
              })}
              {d.status === 'resolved' ? (
                <div className="card-b">
                  <Note tone="ok" icon="check" title={`Resolved ${relTime(d.resolved_at)}`} />
                </div>
              ) : (
                <div className="card-b stack16" style={{ gap: 10 }}>
                  {canAnswer ? (
                    <>
                      <div style={{ display: 'flex', gap: 8, overflowX: 'auto' }}>
                        {QUICK.map((q) => (
                          <button key={q} type="button" className="select" style={{ flex: 'none' }} onClick={() => setReply(q)}>
                            {q}
                          </button>
                        ))}
                      </div>
                      <label className="field">
                        <span>Reply</span>
                        <textarea className="input" rows={3} value={reply} onChange={(e) => setReply(e.target.value)} maxLength={1000} placeholder="Shown to the player as Trina, in their Support app" />
                      </label>
                    </>
                  ) : (
                    <Note icon="lock" title="Answering support needs rank 5">
                      The same rank the Support app asks for in game.
                    </Note>
                  )}
                  <div className="actions">
                    <Btn wp="game" disabled title="Teleporting to a player happens in game">
                      Teleport to player
                    </Btn>
                    {d.player ? (
                      <Btn wp="live" onClick={() => navigate(`/players/${d.player!.id}?act=give`)}>
                        Give
                      </Btn>
                    ) : null}
                    <span className="grow" />
                    {canAnswer ? (
                      <>
                        <Btn onClick={resolve}>Resolve</Btn>
                        <Btn variant="primary" disabled={!reply.trim() || busy} onClick={send}>
                          Send reply <Wp path="saved" />
                        </Btn>
                      </>
                    ) : null}
                  </div>
                  {canAnswer ? (
                    <p className="sub" style={{ fontSize: 12 }}>
                      Saved to the thread and taken by you; the player reads it the next time their Support app loads. A live push needs the phone in game.
                    </p>
                  ) : null}
                </div>
              )}
            </>
          ) : null
        }
      </Loadable>
    </section>
  );
}
