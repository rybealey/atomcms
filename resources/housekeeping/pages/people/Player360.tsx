import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post } from '@hk/lib/api';
import { fmtDate, fmtDay, fmtDuration, fmtNumber, relTime } from '@hk/lib/format';
import { ActionDialog, AsOf, Avatar, Bar, Btn, Card, Coin, Domain, Empty, ErrorNote, Field, Kind, Kv, Pill, PlayerChip, PlayerRef, RoomChip, Seg, Skeleton, Tabs, Tone, fmtLeft } from '@hk/ui';
import { TimelineTab, MoneyTab, JusticeTab, ChatTab, SecurityTab, InventoryTab, DisciplineTab } from './PlayerTabs';

export interface Player {
  id: number;
  username: string;
  look: string;
  motto: string;
  rank: number;
  rank_name: string;
  online: boolean;
  room: { id: number; name: string } | null;
  region: string;
  created: number;
  last_online: number;
  account: { id: number; characters: { id: number; username: string; look: string; online: boolean }[] };
  domain: Domain | null;
  job_label: string | null;
  flags: { banned: { reason: string; expires_at: number } | null; muted: boolean; jailed: { left: number; sentence: number } | null; vip_until: number | null; ambassador: boolean; staff: boolean };
  counts: { security: number; discipline: number };
}
export interface Can { alert: boolean; disconnect: boolean; give_coins: boolean; give_badge: boolean; ban: boolean; mute: boolean; note: boolean; see_ips: boolean; chat: boolean; payments: boolean }
interface Overview {
  stats: { health: number; health_max: number; energy: number; energy_max: number; strength: number; stamina: number; online_seconds: number; gym_left: number } | null;
  job: { corporation: { id: number; name: string; badge: string }; domain: Domain; rank: string | null; tier: number; tiers: number; pay: number; hired_at: number; shift_week: number; on_duty: boolean; hq: { id: number; name: string }[] } | null;
  money: { coins: number; diamonds: number; current: number | null; savings: number | null; online: boolean };
  relationships: { partner: { player: PlayerRef | null; since: number } | null; gang: { id: number; name: string; role: string; joined_at: number } | null; birthday: { month: number; day: number; visibility: string } | null; region_visibility: string | null };
  justice: { open_charges: number; times_jailed: number; arrests_30d: number; calls_made: number; calls_answered: number; avg_response: number | null; last_jailed: number | null; last_call: number | null };
  flags: { kind: string; tone: Tone; title: string; sub: string; at: number | null; to: string }[];
}
export interface PlayerData { player: Player; overview: Overview; can: Can }

const DOMAIN_PILL: Record<Domain, Tone> = { police: 'info', medical: 'ok', corp: 'acc', gang: 'brand', staff: 'brand' };
const TABS = [
  { id: 'overview', label: 'Overview' },
  { id: 'timeline', label: 'Timeline' },
  { id: 'money', label: 'Money' },
  { id: 'justice', label: 'Justice' },
  { id: 'chat', label: 'Chat' },
  { id: 'security', label: 'Security' },
  { id: 'inventory', label: 'Inventory and rooms' },
  { id: 'discipline', label: 'Discipline' },
] as const;

export default function Player360() {
  const { id: rawId, tab: rawTab } = useParams();
  const id = Number(rawId);
  const tab = TABS.some((t) => t.id === rawTab) ? (rawTab as (typeof TABS)[number]['id']) : 'overview';
  const state = useFetch<PlayerData>(`/players/${id}`);
  const d = state.data;
  useCrumbs([{ label: 'People', to: '/players' }, { label: 'Players', to: '/players' }, { label: d?.player.username ?? 'Player' }]);

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
          <Skeleton rows={4} />
        </div>
      </main>
    );
  }

  const tabs = TABS.filter((t) => (t.id === 'security' ? d.can.see_ips : t.id === 'chat' ? d.can.chat : true)).map((t) => ({
    ...t,
    to: t.id === 'overview' ? `/players/${id}` : `/players/${id}/${t.id}`,
    n: t.id === 'security' ? d.player.counts.security : t.id === 'discipline' ? d.player.counts.discipline : null,
  }));

  return (
    <main className="page" style={{ gap: 0 }}>
      <Header data={d} big={tab === 'overview'} onChanged={state.reload} />
      <Tabs tabs={tabs} active={tab} label="Player sections" />
      <div style={{ padding: '24px 0' }}>
        {tab === 'overview' ? <OverviewTab data={d} /> : null}
        {tab === 'timeline' ? <TimelineTab id={id} /> : null}
        {tab === 'money' ? <MoneyTab id={id} data={d} onChanged={state.reload} /> : null}
        {tab === 'justice' ? <JusticeTab id={id} name={d.player.username} /> : null}
        {tab === 'chat' ? <ChatTab id={id} data={d} /> : null}
        {tab === 'security' ? <SecurityTab id={id} name={d.player.username} /> : null}
        {tab === 'inventory' ? <InventoryTab id={id} data={d} /> : null}
        {tab === 'discipline' ? <DisciplineTab id={id} data={d} onChanged={state.reload} /> : null}
      </div>
    </main>
  );
}

function Header({ data, big, onChanged }: { data: PlayerData; big: boolean; onChanged: () => void }) {
  const { player: p, can } = data;
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();
  const [dialog, setDialog] = useState<'alert' | 'give' | 'disconnect' | null>(null);
  const others = p.account.characters.filter((c) => c.id !== p.id);

  useEffect(() => {
    const act = params.get('act');
    if (act === 'alert' || act === 'give' || act === 'disconnect') {
      setDialog(act);
      const next = new URLSearchParams(params);
      next.delete('act');
      setParams(next, { replace: true });
    }
  }, [params, setParams]);

  return (
    <section style={{ display: 'flex', flexWrap: 'wrap', gap: big ? 20 : 16, alignItems: big ? 'flex-start' : 'center', paddingBottom: 20 }}>
      <Avatar look={p.look} size={big ? 80 : 40} />
      <div className="grow" style={{ display: 'flex', flexDirection: 'column', gap: big ? 8 : 0, minWidth: 260 }}>
        <div className="actions" style={{ gap: 10 }}>
          <h1 style={{ fontSize: 22, lineHeight: '30px', fontWeight: 600, letterSpacing: '-0.01em' }}>{p.username}</h1>
          <span className="status">
            <span className={`dot ${p.online ? 'ok' : ''}`} />
            {p.online ? (p.room ? 'Online in' : 'Online') : `Last online ${relTime(p.last_online)}`}
          </span>
          {p.online && p.room ? <RoomChip id={p.room.id} name={p.room.name} /> : null}
          {p.domain && p.job_label ? <Pill tone={DOMAIN_PILL[p.domain]}>{p.job_label}</Pill> : null}
          {p.flags.banned ? <Pill tone="bad">Banned until {fmtDay(p.flags.banned.expires_at)}</Pill> : null}
          {p.flags.jailed ? <Pill tone="warn">Jailed · {fmtLeft(p.flags.jailed.left)} left</Pill> : null}
          {p.flags.muted ? <Pill tone="warn">Muted</Pill> : null}
          {p.flags.vip_until ? <Pill tone="acc">VIP until {fmtDay(p.flags.vip_until)}</Pill> : null}
          {p.flags.ambassador ? <Pill tone="info">Ambassador</Pill> : null}
          {p.flags.staff ? <Pill tone="brand">{p.rank_name}</Pill> : null}
        </div>
        {big ? (
          <>
            <div className="sub actions" style={{ gap: 6 }}>
              <span>
                Account <span className="mono">#{p.account.id}</span>
                {others.length ? ' · characters' : ''}
              </span>
              {others.length
                ? p.account.characters.map((c) =>
                    c.id === p.id ? (
                      <span key={c.id} className="chip" style={{ borderColor: 'var(--brand)' }} aria-current="page">
                        <Avatar look={c.look} />
                        <span>{c.username}</span>
                      </span>
                    ) : (
                      <PlayerChip key={c.id} player={c} />
                    ),
                  )
                : null}
            </div>
            <div className="sub">
              {[!p.flags.staff ? `${p.rank_name} (rank ${p.rank})` : null, p.region ? `${p.region} region` : null, p.created ? `Created ${fmtDate(p.created)}` : null, p.online ? null : `Last login ${relTime(p.last_online)}`].filter(Boolean).join(' · ')}
            </div>
          </>
        ) : (
          <p className="sub">
            Account <span className="mono">#{p.account.id}</span>
            {others.length ? ` · ${others.map((o) => o.username).join(' and ')} on the same account` : ''}
          </p>
        )}
      </div>
      <div className="actions">
        {can.alert ? (
          <Btn wp="live" disabled={!p.online} title={p.online ? undefined : 'Only online players receive alerts'} onClick={() => setDialog('alert')}>
            Alert
          </Btn>
        ) : null}
        {can.give_coins || can.give_badge ? (
          <Btn wp={p.online ? 'live' : 'saved'} onClick={() => setDialog('give')}>
            Give
          </Btn>
        ) : null}
        {can.disconnect ? (
          <Btn wp="live" disabled={!p.online} onClick={() => setDialog('disconnect')}>
            Disconnect
          </Btn>
        ) : null}
        {can.ban ? (
          <Btn variant="danger" wp="saved" onClick={() => navigate(`/players/${p.id}/discipline#ban`)}>
            Ban
          </Btn>
        ) : null}
      </div>
      {dialog === 'alert' ? <AlertDialog player={p} onClose={() => setDialog(null)} /> : null}
      {dialog === 'give' ? <GiveDialog data={data} onClose={() => setDialog(null)} onDone={onChanged} /> : null}
      {dialog === 'disconnect' ? (
        <ActionDialog
          title={`Disconnect ${p.username}`}
          wp="live"
          cta="Disconnect"
          danger
          note="They can log straight back in. Sent through RCON and logged with your reason."
          onClose={() => setDialog(null)}
          onSubmit={async (reason) => {
            await post(`/players/${p.id}/disconnect`, { reason });
            onChanged();
          }}
        />
      ) : null}
    </section>
  );
}

function AlertDialog({ player, onClose }: { player: Player; onClose: () => void }) {
  const { toast } = useSession();
  const [message, setMessage] = useState('');
  return (
    <ActionDialog
      title={`Alert ${player.username}`}
      wp="live"
      cta="Send alert"
      reason={false}
      valid={message.trim().length >= 2}
      note="Shown in their client at once. Logged with your name."
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

function GiveDialog({ data, onClose, onDone }: { data: PlayerData; onClose: () => void; onDone: () => void }) {
  const { toast } = useSession();
  const { player: p, can } = data;
  const [what, setWhat] = useState<'coins' | 'badge'>(can.give_coins ? 'coins' : 'badge');
  const [amount, setAmount] = useState('');
  const [badge, setBadge] = useState('');
  const n = Number(amount);
  const valid = what === 'coins' ? Number.isInteger(n) && n !== 0 && Math.abs(n) <= 1000000 : /^[A-Za-z0-9_-]+$/.test(badge);
  return (
    <ActionDialog
      title={`Give to ${p.username}`}
      wp={p.online ? 'live' : 'saved'}
      cta={what === 'coins' ? (n < 0 ? `Take ${fmtNumber(Math.abs(n) || 0)} coins` : `Give ${fmtNumber(n || 0)} coins`) : `Give ${badge || 'badge'}`}
      valid={valid}
      note={p.online ? 'Sent to the hotel through RCON and logged with your name and reason.' : `${p.username} is offline: it is written to their account and they see it at their next login.`}
      onClose={onClose}
      onSubmit={async (reason) => {
        const r = what === 'coins' ? await post<{ message: string }>(`/players/${p.id}/coins`, { amount: n, reason }) : await post<{ message: string }>(`/players/${p.id}/badges`, { badge, reason });
        toast('ok', r.message);
        onDone();
      }}
    >
      {can.give_coins && can.give_badge ? (
        <Seg
          label="What to give"
          value={what}
          onChange={setWhat}
          options={[
            { value: 'coins', label: 'Coins' },
            { value: 'badge', label: 'Badge' },
          ]}
        />
      ) : null}
      {what === 'coins' ? (
        <Field label="Amount" hint={`Has ${fmtNumber(data.overview.money.coins)} coins${p.online ? ' as of last logout' : ''}. A negative amount takes coins.`}>
          <span className="input">
            <Coin />
            <input className="mono" inputMode="numeric" value={amount} onChange={(e) => setAmount(e.target.value.replace(/[^\d-]/g, ''))} aria-label="Amount" autoFocus />
          </span>
        </Field>
      ) : (
        <Field label="Badge code">
          <input className="input mono" value={badge} onChange={(e) => setBadge(e.target.value.trim())} maxLength={100} autoFocus placeholder="POL03" />
        </Field>
      )}
    </ActionDialog>
  );
}

function statTone(v: number, max: number): 'ok' | 'warn' | 'bad' {
  const r = v / Math.max(1, max);
  return r >= 0.6 ? 'ok' : r >= 0.3 ? 'warn' : 'bad';
}

function OverviewTab({ data }: { data: PlayerData }) {
  const { overview: o, player: p } = data;
  const s = o.stats;
  const bars = s
    ? [
        { label: 'Health', v: s.health, max: s.health_max },
        { label: 'Energy', v: s.energy, max: s.energy_max },
        { label: 'Strength', v: s.strength, max: 10 },
        { label: 'Stamina', v: s.stamina, max: 10 },
      ]
    : [];
  const birthday = o.relationships.birthday;

  return (
    <div className="cols3">
      <Card title="Roleplay stats" action={p.online ? <AsOf online /> : <span className="sub">As of last logout</span>}>
        {s ? (
          <div className="card-b stack16" style={{ gap: 14 }}>
            {bars.map((b) => (
              <div key={b.label} className="stack16" style={{ gap: 6 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span className="sub" style={{ color: 'var(--ink)' }}>
                    {b.label}
                    {b.max === 10 ? ' level' : ''}
                  </span>
                  <span className="num">
                    {b.v} / {b.max}
                  </span>
                </div>
                <Bar pct={(b.v / b.max) * 100} tone={b.max === 10 ? undefined : statTone(b.v, b.max)} width="100%" />
              </div>
            ))}
            <div style={{ paddingTop: 6, borderTop: '1px solid var(--line)' }}>
              <Kv rows={[['Online time', <span className="num" key="o">{fmtDuration(s.online_seconds)}</span>], ['Gym membership', s.gym_left > 0 ? <span key="g">{fmtDuration(s.gym_left)} of online time left</span> : <span className="sub" key="g">None</span>]]} />
            </div>
          </div>
        ) : (
          <Empty title="No roleplay stats yet" body="They are written the first time the player logs in to the city." />
        )}
      </Card>

      <Card title="Job" action={<span className="wp game">Edit in game</span>}>
        {o.job ? (
          <div className="card-b stack16" style={{ gap: 14 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <Kind icon="building" tone={o.job.domain === 'police' ? 'info' : o.job.domain === 'medical' ? 'ok' : 'acc'} />
              <div className="grow">
                <Link to={`/city/corporations/${o.job.corporation.id}`} style={{ fontWeight: 500, color: 'var(--ink)' }}>
                  {o.job.corporation.name}
                </Link>
                <p className="sub">
                  {o.job.rank ?? 'No rank'}
                  {o.job.tiers > 0 ? ` · tier ${o.job.tier} of ${o.job.tiers}` : ''}
                </p>
              </div>
              <Pill tone={o.job.on_duty ? 'ok' : ''}>{o.job.on_duty ? 'On duty' : 'Off duty'}</Pill>
            </div>
            <Kv
              rows={[
                ['Pay per shift', <span key="p"><span className="num">{fmtNumber(o.job.pay)}</span> <Coin /></span>],
                ['Shift this week', <span className="num" key="s">{fmtDuration(o.job.shift_week)}</span>],
                ['Hired', fmtDate(o.job.hired_at)],
                ['HQ rooms', o.job.hq.length ? o.job.hq.map((r) => r.name).join(', ') : '—'],
              ]}
            />
          </div>
        ) : (
          <Empty title="Unemployed" body="Hiring happens in game." />
        )}
      </Card>

      <Card title="Money" action={<Link to={`/players/${p.id}/money`} style={{ fontSize: 13 }}>Ledger</Link>}>
        <div className="card-b" style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 16 }}>
          <Big label="Coins" value={o.money.coins} coin="cash" />
          <Big label="Diamonds" value={o.money.diamonds} coin="diamonds" />
          <Big label="Current account" value={o.money.current} />
          <Big label="Savings" value={o.money.savings} />
          <p className="sub" style={{ gridColumn: '1 / -1', fontSize: 12, display: 'flex', alignItems: 'center', gap: 6 }}>
            {o.money.online ? (
              <>
                <AsOf online /> Coins are written when they log out; bank balances are live
              </>
            ) : (
              'All balances as stored'
            )}
          </p>
        </div>
      </Card>

      <Card title="Relationships">
        <div className="row">
          <span className="sub" style={{ width: 72 }}>Partner</span>
          {o.relationships.partner ? (
            <>
              <PlayerChip player={o.relationships.partner.player} />
              <span className="sub grow r">Since {fmtDay(o.relationships.partner.since)}</span>
            </>
          ) : (
            <span className="grow sub">None</span>
          )}
        </div>
        <div className="row">
          <span className="sub" style={{ width: 72 }}>Gang</span>
          {o.relationships.gang ? (
            <>
              <Link to={`/city/gangs/${o.relationships.gang.id}`} className="tag">
                <span className="dot gang" />
                {o.relationships.gang.name}
              </Link>
              <span className="sub grow r">{o.relationships.gang.role}</span>
            </>
          ) : (
            <span className="grow sub">Not in a gang</span>
          )}
        </div>
        <div className="row">
          <span className="sub" style={{ width: 72 }}>Birthday</span>
          <span className="grow">{birthday ? new Date(2000, birthday.month - 1, birthday.day).toLocaleDateString('en-US', { month: 'long', day: 'numeric' }) : <span className="sub">Not set</span>}</span>
          {birthday ? <span className="sub">Seen by {birthday.visibility.toLowerCase()}</span> : null}
        </div>
        <div className="row">
          <span className="sub" style={{ width: 72 }}>Region</span>
          <span className="grow">{p.region || <span className="sub">Not set</span>}</span>
          {o.relationships.region_visibility ? <span className="sub">Seen by {o.relationships.region_visibility.toLowerCase()}</span> : null}
        </div>
      </Card>

      <Card title="Justice" action={<Link to={`/players/${p.id}/justice`} style={{ fontSize: 13 }}>Justice tab</Link>}>
        <div className="card-b" style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 16 }}>
          <Big label="Open charges" value={o.justice.open_charges} tone={o.justice.open_charges > 0 ? 'neg' : undefined} />
          <Big label="Times jailed" value={o.justice.times_jailed} />
          <Big label="911 calls made" value={o.justice.calls_made} />
          <Big label="911 calls answered" value={o.justice.calls_answered} />
        </div>
      </Card>

      <Card title="Account flags" action={<span className="sub">Whole account</span>}>
        {o.flags.map((f, i) => (
          <div key={i} className="row">
            <Kind icon={f.kind === 'guard' ? 'shield-alert' : f.kind === 'ban' ? 'ban' : 'edit'} tone={f.tone} />
            <div className="grow">
              <p style={{ fontWeight: 500 }}>{f.title}</p>
              <p className="sub ell">
                {f.sub}
                {f.at ? ` · ${relTime(f.at)}` : ''}
              </p>
            </div>
            <Btn sm to={`/players/${p.id}/${f.to}`}>
              {f.kind === 'guard' ? 'Review' : 'Open'}
            </Btn>
          </div>
        ))}
        {!p.flags.banned && !p.flags.muted ? (
          <div className="row">
            <span className="sub">No active bans or mutes</span>
          </div>
        ) : null}
      </Card>
    </div>
  );
}

function Big({ label, value, coin, tone }: { label: string; value: number | null; coin?: 'cash' | 'diamonds'; tone?: 'neg' }) {
  return (
    <div>
      <p className="sub">{label}</p>
      <p style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
        <span className={`num ${tone ?? ''}`} style={{ fontSize: 20, lineHeight: '28px' }}>
          {value === null ? '—' : fmtNumber(value)}
        </span>
        {coin ? <Coin type={coin} /> : null}
      </p>
    </div>
  );
}

