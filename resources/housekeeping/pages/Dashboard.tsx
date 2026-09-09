import { Link, useNavigate } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put } from '@hk/lib/api';
import { fmtNumber, greeting, plural, relTime } from '@hk/lib/format';
import { Avatar, Button, Card, ErrorBox, Icon, Pill, Skeleton, StateText, Switch } from '@hk/ui';
import { IconName } from '@hk/lib/icons';

interface DashboardData {
  stats: { online: number; total: number; registrations_today: number; registrations_week_avg: number; registrations_spark: number[]; active_bans: number | null; pending_applications: number; pending_badges: number };
  hotel: { maintenance_enabled: boolean; disable_registration: boolean; requires_beta_code: boolean; min_maintenance_login_rank: number; emulator_up: boolean };
  attention: { title: string; sub: string; tone: 'warning' | 'info' | 'success'; to: string }[];
  activity: { id: number; who: string; look: string | null; description: string; event: string; subject: string | null; properties: Record<string, unknown> | null; at: number | null }[];
}

function spark(values: number[]): string {
  const max = Math.max(1, ...values);
  const step = 70 / Math.max(1, values.length - 1);
  return values.map((v, i) => `${Math.round(i * step)},${Math.round(22 - (v / max) * 20)}`).join(' ');
}

function Stat({ label, value, delta, icon, to, sparkline }: { label: string; value: string; delta: string; icon: IconName; to: string; sparkline?: number[] }) {
  return (
    <Link to={to} className="hk-stat">
      <span className="hk-stat__label">
        {label} <Icon name={icon} size={16} />
      </span>
      <span className="hk-stat__row">
        <span className="hk-stat__value">{value}</span>
        {sparkline ? (
          <svg viewBox="0 0 70 24" width="70" height="24" aria-hidden="true" style={{ flex: 'none', overflow: 'visible' }}>
            <polyline points={spark(sparkline)} fill="none" stroke="var(--accent)" strokeWidth="2" shapeRendering="crispEdges" />
          </svg>
        ) : null}
      </span>
      <span className="hk-stat__delta">{delta}</span>
    </Link>
  );
}

export default function Dashboard() {
  useCrumbs([{ label: 'Home' }]);
  const { me, can, rankName, confirm, toast } = useSession();
  const navigate = useNavigate();
  const { data, error, loading, reload, setData } = useFetch<DashboardData>('/dashboard');
  const canSettings = can('manage_website_settings');

  const setSetting = async (key: 'maintenance_enabled' | 'disable_registration' | 'requires_beta_code', on: boolean, danger?: { title: string; body: string; impacts: string[]; cta: string }) => {
    if (!data) return;
    if (on && danger) {
      const ok = await confirm(danger);
      if (!ok) return;
    }
    try {
      await put('/settings', { settings: { [key]: on ? '1' : '0' } });
      setData((d) => (d ? { ...d, hotel: { ...d.hotel, [key]: on } } : d));
      toast('success', 'Saved', `${key.replace(/_/g, ' ')} is now ${on ? 'on' : 'off'}.`);
    } catch (e) {
      toast('danger', 'Not saved', e instanceof Error ? e.message : 'Unknown error');
    }
  };

  return (
    <section className="hk-page hk-page--loose" aria-label="Dashboard">
      <div className="hk-pagehead">
        <div>
          <h1 className="hk-h1 hk-h1--lg" style={{ marginBottom: 6 }}>
            {greeting()}, {me.user.username}
          </h1>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: 'var(--muted)', fontSize: 14, flexWrap: 'wrap' }}>
            <Pill tone="orange">
              {rankName(me.user.rank)} · {me.user.rank}
            </Pill>
            <span>Last in the hotel {relTime(me.user.last_online)}</span>
          </div>
        </div>
        <div className="hk-actions">
          {can('write_article') ? (
            <Button variant="secondary" size="sm" to="/articles">
              Write Article
            </Button>
          ) : null}
          <Button variant="primary" size="sm" to="/players">
            Find Player
          </Button>
        </div>
      </div>

      {error ? <ErrorBox message={error} retry={reload} /> : null}

      <div className="hk-grid hk-grid--stats">
        {loading || !data ? (
          Array.from({ length: 4 }, (_, i) => (
            <div key={i} className="hk-card">
              <Skeleton rows={3} />
            </div>
          ))
        ) : (
          <>
            <Stat label="Players Online" value={fmtNumber(data.stats.online)} delta={`${fmtNumber(data.stats.total)} accounts in total`} icon="users" to="/players?filter=online" />
            <Stat label="Registrations Today" value={fmtNumber(data.stats.registrations_today)} delta={`7-day average ${data.stats.registrations_week_avg}`} icon="user-plus" to="/players?filter=new" sparkline={data.stats.registrations_spark} />
            {data.stats.active_bans !== null ? <Stat label="Active Bans" value={fmtNumber(data.stats.active_bans)} delta="Across accounts, IPs and machines" icon="lock" to="/bans" /> : null}
            <Stat label="Waiting On Staff" value={fmtNumber(data.stats.pending_applications + data.stats.pending_badges)} delta={`${data.stats.pending_applications} ${plural(data.stats.pending_applications, 'application')} · ${data.stats.pending_badges} drawn ${plural(data.stats.pending_badges, 'badge')}`} icon="flag" to="/applications" />
          </>
        )}
      </div>

      <div className="hk-grid hk-grid--2">
        <Card title="Needs Attention" action={data ? <span className="hk-count">{data.attention.length}</span> : null}>
          {!data ? (
            <Skeleton rows={4} />
          ) : data.attention.length === 0 ? (
            <div className="hk-card__body muted small">Nothing is waiting on you right now.</div>
          ) : (
            data.attention.map((a) => (
              <button key={a.title} type="button" className="hk-row--btn" onClick={() => navigate(a.to)}>
                <span className={`hk-dot hk-dot--${a.tone}`} />
                <span className="hk-row__main">
                  <span className="hk-row__title">{a.title}</span>
                  <span className="hk-row__sub">{a.sub}</span>
                </span>
                <span aria-hidden="true" style={{ fontWeight: 600, color: 'var(--muted)' }}>
                  ▶
                </span>
              </button>
            ))
          )}
        </Card>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <Card
            title="Hotel Status"
            action={
              data ? (
                <span className="hk-status" style={{ fontSize: 13, fontWeight: 600, color: data.hotel.emulator_up ? 'var(--success)' : 'var(--danger)' }}>
                  <span className={`hk-dot ${data.hotel.emulator_up ? 'hk-dot--on hk-dot--blink' : 'hk-dot--danger'}`} style={{ width: 8, height: 8 }} />
                  {data.hotel.emulator_up ? 'Emulator up' : 'Emulator unreachable'}
                </span>
              ) : null
            }
          >
            {!data ? (
              <Skeleton rows={3} />
            ) : (
              <>
                <div className="hk-row" style={{ minHeight: 56 }}>
                  <span className="hk-row__main">
                    <span className="hk-row__title">Maintenance Mode</span>
                    <span className="hk-row__sub">Players below {rankName(data.hotel.min_maintenance_login_rank)} see the maintenance page</span>
                  </span>
                  <StateText on={data.hotel.maintenance_enabled} danger />
                  <Switch
                    checked={data.hotel.maintenance_enabled}
                    disabled={!canSettings}
                    label="Maintenance mode"
                    onChange={(on) =>
                      setSetting('maintenance_enabled', on, {
                        title: 'Turn on maintenance mode?',
                        body: `Everyone below ${rankName(data.hotel.min_maintenance_login_rank)} (${data.hotel.min_maintenance_login_rank}) is logged out of the website and sees the maintenance message.`,
                        impacts: [`${data.stats.online} players are online right now`, 'The hotel client stays reachable for staff'],
                        cta: 'Turn Maintenance On',
                      })
                    }
                  />
                </div>
                <div className="hk-row" style={{ minHeight: 56 }}>
                  <span className="hk-row__main">
                    <span className="hk-row__title">Registration</span>
                    <span className="hk-row__sub">New players can create an account</span>
                  </span>
                  <StateText on={!data.hotel.disable_registration} onText="Open" offText="Closed" />
                  <Switch
                    checked={!data.hotel.disable_registration}
                    disabled={!canSettings}
                    label="Registration open"
                    onChange={(open) =>
                      setSetting('disable_registration', !open, open ? undefined : {
                        title: 'Pause registration?',
                        body: 'New players will see "Registration is closed" on the signup page until you turn this back on.',
                        impacts: ['Beta codes stop working too', 'Staff can still log in'],
                        cta: 'Pause Registration',
                      })
                    }
                  />
                </div>
                <div className="hk-row" style={{ minHeight: 56 }}>
                  <span className="hk-row__main">
                    <span className="hk-row__title">Beta Code Required</span>
                    <span className="hk-row__sub">Signup asks for an invite code</span>
                  </span>
                  <StateText on={data.hotel.requires_beta_code} />
                  <Switch checked={data.hotel.requires_beta_code} disabled={!canSettings} label="Beta code required" onChange={(on) => setSetting('requires_beta_code', on)} />
                </div>
              </>
            )}
          </Card>

          <Card title="Recent Staff Activity" action={<span className="small muted">Audit log</span>}>
            {!data ? (
              <Skeleton rows={5} />
            ) : data.activity.length === 0 ? (
              <div className="hk-card__body muted small">No staff activity has been logged yet.</div>
            ) : (
              data.activity.map((ev) => (
                <div key={ev.id} className="hk-row" style={{ minHeight: 48, padding: '6px 16px' }}>
                  <Avatar look={ev.look ?? ''} imager={me.config.imager} size="sm" />
                  <span className="hk-row__main">
                    <strong>{ev.who}</strong> {ev.description}
                    {ev.subject ? <code style={{ marginLeft: 6 }}>{ev.subject}</code> : null}
                  </span>
                  <span className="small muted" style={{ whiteSpace: 'nowrap' }}>
                    {relTime(ev.at)}
                  </span>
                </div>
              ))
            )}
          </Card>
        </div>
      </div>
    </section>
  );
}
