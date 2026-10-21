import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post } from '@hk/lib/api';
import { fmtClock, fmtDay, fmtNumber, plural, relTime } from '@hk/lib/format';
import { ActionDialog, Btn, Card, Countdown, Domain, Empty, Field, GangChip, Kind, Loadable, PlayerChip, PlayerRef, Pill, RoomChip, Spark, Stat, Wp, WritePath, fmtLeft, type IconName, type Tone } from '@hk/ui';
import { AuditRow } from './logs/StaffAudit';

interface Attention { key: string; kind: 'call' | 'support' | 'guard' | 'badge' | 'ban' | 'view'; tone: Tone; label: string; title: string; detail: string; player: PlayerRef | null; room: { id: number; name: string } | null; at: number | null; action: string; to: string }
interface World { maintenance: boolean; combat_paused: boolean; weather: { pinned: boolean; name: string | null }; time: { pinned: boolean; label: string; phase: string } }
interface TodayData {
  now: number;
  pulse: { online: number; staff_online: number; on_rotation: number; emulator: { online: boolean }; world: World };
  attention: Attention[];
  jail: { id: number; player: PlayerRef | null; officer: PlayerRef | null; charges: string[]; sentence: number; left: number; online: boolean }[];
  shifts: { id: number; name: string; domain: Domain; ranks: string; on: number; total: number }[];
  turf: { room: { id: number; name: string }; gang: { id: number; name: string }; by: PlayerRef | null; at: number }[];
  registrations: { day: number; count: number }[];
  online: { peaks: { day: number; peak: number | null }[]; recent: number[]; since: number | null };
  activity: AuditRow[] | null;
}

const KIND_ICON: Record<Attention['kind'], IconName> = { call: 'siren', support: 'message', guard: 'shield-alert', badge: 'star', ban: 'ban', view: 'bookmark' };

export default function Today() {
  useCrumbs([{ label: 'Today' }]);
  const { able, toast } = useSession();
  const state = useFetch<TodayData>('/today');
  const [alerting, setAlerting] = useState(false);
  const d = state.data;
  const now = new Date();

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Today</h1>
          <p className="sub">
            {now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })} · {fmtClock(now.getTime() / 1000)}
            {d ? ` · ${plural(d.pulse.staff_online, 'staff member')} online` : ''}
          </p>
        </div>
        <div className="actions">
          <Btn to="/logs" icon="list">
            Log explorer
          </Btn>
          {able('command_hotel_alert') ? (
            <Btn variant="primary" icon="megaphone" onClick={() => setAlerting(true)}>
              Hotel alert
            </Btn>
          ) : null}
        </div>
      </div>

      <section className="card stats" aria-label="City pulse">
        <Stat dot="ok" label="Players online" value={d ? fmtNumber(d.pulse.online) : '—'} spark={d?.online.recent} />
        <Stat dot="staff" label="Staff online" value={d ? fmtNumber(d.pulse.staff_online) : '—'} sub={d ? `${plural(d.pulse.on_rotation, 'moderator')} on rotation` : undefined} />
        <Stat className="hide-phone" dot={d?.pulse.emulator.online ? 'ok' : 'bad'} label="Emulator" word={d ? (d.pulse.emulator.online ? 'Online' : 'Offline') : '—'} sub={d ? (d.pulse.emulator.online ? 'RCON answering' : 'Live actions are paused') : undefined} />
        <Stat className="hide-phone" label="Maintenance" word={d ? (d.pulse.world.maintenance ? 'On' : 'Off') : '—'} dot={d?.pulse.world.maintenance ? 'warn' : undefined} />
        <Stat label="Combat" word={d ? (d.pulse.world.combat_paused ? 'Paused' : 'Active') : '—'} dot={d?.pulse.world.combat_paused ? 'warn' : undefined} />
        <Stat label="Weather" word={d ? (d.pulse.world.weather.pinned ? d.pulse.world.weather.name ?? 'Pinned' : 'Live') : '—'} sub={d ? (d.pulse.world.weather.pinned ? 'Pinned in the City Panel' : 'Real San Francisco weather') : undefined} />
        <Stat className="hide-phone" label="City time" word={d ? `${d.pulse.world.time.phase}` : '—'} sub={d ? `${d.pulse.world.time.label} · ${d.pulse.world.time.pinned ? 'pinned in the City Panel' : 'city clock'}` : undefined} />
      </section>

      <div className="cols">
        <Card title="Needs attention" sub="Sorted by urgency · updates live" action={d ? <Pill tone={d.attention.length ? 'bad' : 'ok'}>{d.attention.length ? `${d.attention.length} open` : 'All clear'}</Pill> : null}>
          <Loadable state={state} rows={6} empty={(x) => (x.attention.length === 0 ? <Empty title="Nothing needs a human right now" body="New 911 calls, support threads and flags show up here." /> : false)}>
            {(x) =>
              x.attention.map((a) => (
                <div key={a.key} className="row" style={{ alignItems: 'flex-start' }}>
                  <Kind icon={KIND_ICON[a.kind]} tone={a.tone} />
                  <div className="grow stack16" style={{ gap: 6 }}>
                    <div className="actions" style={{ gap: 8 }}>
                      <span style={{ fontWeight: 500 }}>{a.title}</span>
                      <Pill tone={a.tone}>{a.label}</Pill>
                    </div>
                    <p className="sub">{a.detail}</p>
                    {a.player || a.room ? (
                      <div className="actions" style={{ gap: 6 }}>
                        {a.player ? <PlayerChip player={a.player} /> : null}
                        {a.room ? <RoomChip id={a.room.id} name={a.room.name} /> : null}
                      </div>
                    ) : null}
                  </div>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 8 }}>
                    <span className="mono">{a.at ? relTime(a.at) : ''}</span>
                    <Btn sm to={a.to}>
                      {a.action}
                    </Btn>
                  </div>
                </div>
              ))
            }
          </Loadable>
        </Card>

        <div className="stack">
          <Card title="In jail" sub={d ? `${plural(d.jail.length, 'prisoner')} serving` : undefined} action={<Link to="/city/justice" style={{ fontSize: 13 }}>Justice desk</Link>}>
            <Loadable state={state} empty={(x) => (x.jail.length === 0 ? <Empty title="Nobody is in jail" /> : false)}>
              {(x) =>
                x.jail.map((j) => (
                  <div key={j.id} className="row">
                    <PlayerChip player={j.player} />
                    <span className="sub grow ell">
                      {j.charges.join(', ') || 'No charge sheet'}
                      {j.officer ? ` · ${j.officer.username}` : ''}
                    </span>
                    {j.online ? (
                      <Countdown until={x.now + j.left} total={j.sentence} />
                    ) : (
                      <div style={{ width: 96, display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 }} title="The clock only runs while the prisoner is in the hotel">
                        <span className="num">{fmtLeft(j.left)}</span>
                        <span className="sub" style={{ fontSize: 11 }}>
                          Paused · offline
                        </span>
                      </div>
                    )}
                  </div>
                ))
              }
            </Loadable>
          </Card>

          <Card title="On shift" sub={d ? `${plural(d.shifts.reduce((n, s) => n + s.on, 0), 'player')} working right now` : undefined}>
            <Loadable state={state} empty={(x) => (x.shifts.length === 0 ? <Empty title="Nobody is clocked in" /> : false)}>
              {(x) =>
                x.shifts.map((s) => (
                  <div key={s.id} className="row">
                    <span className={`dot ${s.domain}`} />
                    <Link to={`/city/corporations/${s.id}`} className="grow ell" style={{ color: 'var(--ink)', fontWeight: 500 }}>
                      {s.name}
                    </Link>
                    <span className="sub hide-phone">{s.ranks}</span>
                    <span className="num" style={{ minWidth: 52, textAlign: 'right' }}>
                      {s.on} / {s.total}
                    </span>
                  </div>
                ))
              }
            </Loadable>
          </Card>

          <Card title="Contested turf" sub="Claims in the last 24 hours">
            <Loadable state={state} empty={(x) => (x.turf.length === 0 ? <Empty title="No turf changed hands" /> : false)}>
              {(x) =>
                x.turf.map((t) => (
                  <div key={t.room.id} className="row">
                    <span className="dot gang" />
                    <div className="grow">
                      <RoomChip id={t.room.id} name={t.room.name} />
                      <p className="sub" style={{ marginTop: 4, display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                        Claimed by <GangChip id={t.gang.id} name={t.gang.name} /> {t.by ? <>· <PlayerChip player={t.by} /></> : null}
                      </p>
                    </div>
                    <span className="mono">{relTime(t.at)}</span>
                  </div>
                ))
              }
            </Loadable>
          </Card>
        </div>
      </div>

      <div className="cols3">
        <Registrations data={d?.registrations} />
        <PeakOnline data={d?.online} />
        {d?.activity !== null ? (
          <Card title="Staff activity" sub="From the audit trail" action={<Link to="/logs/audit" style={{ fontSize: 13 }}>All</Link>}>
            <Loadable state={state} empty={(x) => (!x.activity?.length ? <Empty title="No staff actions yet" /> : false)}>
              {(x) =>
                (x.activity ?? []).map((a) => (
                  <div key={a.id} className="row">
                    <span className="mono" style={{ width: 62 }}>
                      {fmtClock(a.at)}
                    </span>
                    <span className="grow ell">
                      <span style={{ fontWeight: 500 }}>{a.actor?.username ?? 'System'}</span> <span style={{ color: 'var(--muted)' }}>{a.summary}</span>
                    </span>
                    {a.path ? <Wp path={a.path as WritePath} /> : null}
                  </div>
                ))
              }
            </Loadable>
          </Card>
        ) : null}
      </div>

      {alerting ? (
        <HotelAlert
          onClose={() => setAlerting(false)}
          onSent={(m) => toast('ok', 'Hotel alert sent', m)}
        />
      ) : null}
    </main>
  );
}

function HotelAlert({ onClose, onSent }: { onClose: () => void; onSent: (message: string) => void }) {
  const [message, setMessage] = useState('');
  return (
    <ActionDialog
      title="Hotel alert"
      wp="live"
      cta="Send to everyone online"
      reason={false}
      valid={message.trim().length >= 2}
      note="Sent through RCON to every player online, and logged with your name."
      onClose={onClose}
      onSubmit={async () => {
        const r = await post<{ message: string }>('/players/alert', { message, everyone: true });
        onSent(r.message);
      }}
    >
      <Field label="Message">
        <textarea className="input" rows={3} value={message} onChange={(e) => setMessage(e.target.value)} maxLength={500} autoFocus />
      </Field>
    </ActionDialog>
  );
}

function Registrations({ data }: { data?: { day: number; count: number }[] }) {
  const total = data?.reduce((n, d) => n + d.count, 0) ?? 0;
  const max = Math.max(1, ...(data ?? []).map((d) => d.count));
  const thisWeek = data ? data.slice(1).reduce((n, d) => n + d.count, 0) : 0;
  const first = data?.[0]?.count ?? 0;
  return (
    <Card title="Registrations" sub={data ? `Last 8 days · ${fmtNumber(total)} total` : 'Last 8 days'} action={data && first > 0 ? <Pill tone="ok">{fmtNumber(Math.round(thisWeek / 7))} a day</Pill> : null}>
      <div className="bars" role="img" aria-label={data ? `New accounts per day: ${data.map((d) => `${fmtDay(d.day)} ${d.count}`).join(', ')}` : 'Loading'}>
        {(data ?? Array.from({ length: 8 }, (_, i) => ({ day: i, count: 0 }))).map((d) => (
          <div key={d.day}>
            <span className="mono" style={{ fontSize: 11 }}>
              {data ? d.count : ''}
            </span>
            <span className="b" style={{ height: `${Math.round((d.count / max) * 100)}px` }} />
            <span className="sub" style={{ fontSize: 11 }}>
              {data ? new Date(d.day * 1000).getDate() : ''}
            </span>
          </div>
        ))}
      </div>
    </Card>
  );
}

function PeakOnline({ data }: { data?: TodayData['online'] }) {
  const peaks = data?.peaks ?? [];
  const known = peaks.filter((p) => p.peak !== null) as { day: number; peak: number }[];
  const best = known.reduce<{ day: number; peak: number } | null>((b, p) => (!b || p.peak > b.peak ? p : b), null);
  return (
    <Card title="Peak online" sub={best ? `Last 8 days · highest ${fmtNumber(best.peak)} on ${fmtDay(best.day)}` : 'Last 8 days'}>
      <div className="card-b">
        {known.length < 2 ? (
          <Empty bare title="Collecting" body={data?.since ? `Online counts are sampled every five minutes since ${fmtDay(data.since)}.` : 'Online counts are sampled every five minutes; the chart fills in from today.'} />
        ) : (
          <>
            <Spark values={peaks.map((p) => p.peak ?? 0)} width="100%" height={110} stroke={2} />
            <div style={{ display: 'flex', justifyContent: 'space-between' }} className="sub">
              <span>{fmtDay(peaks[0].day)}</span>
              <span>{fmtDay(peaks[peaks.length - 1].day)}</span>
            </div>
          </>
        )}
      </div>
    </Card>
  );
}
