import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { badgeUrl, fullUrl } from '@hk/lib/avatar';
import { fmtDate, fmtDateTime, fmtDuration, fmtNumber, plural, relTime } from '@hk/lib/format';
import { Avatar, Button, Card, ErrorBox, LegacyLink, OnlineDot, Pill, RankChip, Skeleton, Tabs } from '@hk/ui';
import { PlayerSummary, statusOf } from './Players';

interface PlayerDetailData {
  player: PlayerSummary & {
    register_ip: string | null; home_room: number; two_factor: boolean;
    currencies: { type: string; label: string; value: number }[];
    badges: string[];
    ban: { reason: string; expires_at: number } | null;
    ban_history: { reason: string; expires_at: number; added_by: string; added_at: number; active: boolean }[];
    // Null when the viewer may not see IPs.
    connections: Connection[] | null;
    logins: LoginEntry[] | null;
    linked: LinkedAccount[] | null;
    roleplay: {
      corporation: { id: number; name: string; badge: string; rank_name: string; rank_order: number; tier: number; hired_at: number; shift_seconds: number; shift_seconds_week: number; on_duty: boolean } | null;
      gang: { id: number; name: string; role_name: string; joined_at: number } | null;
    };
  };
  can: { edit: boolean; reset_password: boolean; ban: boolean; delete: boolean; chatlogs: boolean };
}

/** One IP and device the account entered the hotel from (ConnectionReport). */
interface Connection {
  ip: string; country: string | null; location: string | null; device: string;
  browser: string; os: string; type: string; screen: string | null; gpu: string | null; reported: boolean;
  first_seen: number | null; last_seen: number | null; visits: number;
  details: [string, string][];
}
interface LoginEntry { ip: string; device: string; ok: boolean; method: string; at: number | null }
type LinkedAccount = PlayerSummary & { same_device: boolean; same_hardware: boolean; shared_ips: string[]; seen_together: number | null };

const LOGIN_METHOD: Record<string, string> = {
  password: 'Password',
  'password-2fa': 'Password and two-factor',
  'wrong-password': 'Wrong password',
  'unknown-user': 'Unknown username',
};

const CURRENCY_ICON: Record<string, string> = {
  credits: '/assets/images/profile/credits.png',
  duckets: '/assets/images/profile/duckets.png',
  diamonds: '/assets/images/profile/diamonds.png',
};

const TABS = [
  { id: 'overview', label: 'Overview' },
  { id: 'roleplay', label: 'Roleplay' },
  { id: 'security', label: 'Security & Sessions' },
  { id: 'moderation', label: 'Moderation History' },
];

export default function PlayerDetail() {
  const { id } = useParams();
  const { me } = useSession();
  const { data, error, loading, reload } = useFetch<PlayerDetailData>(id ? `/players/${id}` : null);
  const [tab, setTab] = useState('overview');
  const name = data?.player.username ?? '…';
  useCrumbs([{ label: 'Community' }, { label: 'Players', to: '/players' }, { label: name }]);

  if (error) return <ErrorBox message={error} retry={reload} />;
  if (loading || !data) {
    return (
      <div className="hk-card">
        <Skeleton rows={6} />
      </div>
    );
  }

  const p = data.player;
  const legacy = me.config.legacy_url;
  const rp = p.roleplay;

  return (
    <section className="hk-page" aria-label="Player detail" style={{ gap: 20 }}>
      <Link to="/players" className="small muted" style={{ alignSelf: 'flex-start', textDecoration: 'none', color: 'var(--muted)', minHeight: 32, display: 'inline-flex', alignItems: 'center', gap: 6 }}>
        ◀ All players
      </Link>

      <div className="hk-card hk-card--lg" style={{ display: 'flex', flexWrap: 'wrap', gap: 20, padding: 20, alignItems: 'center', position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', inset: 0, background: 'var(--grad-sunset)', opacity: 0.18, pointerEvents: 'none' }} />
        <span className="hk-figure" style={{ position: 'relative' }}>
          {p.look ? <img src={fullUrl(me.config.imager, p.look)} alt="" className="px-art" /> : null}
        </span>
        <div style={{ flex: 1, minWidth: 220, position: 'relative', display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
            <h1 className="hk-h1 hk-h1--lg">{p.username}</h1>
            <RankChip rank={p.rank} name={p.rank_name} minStaff={me.config.min_staff_rank} />
            <span className="hk-status">
              <OnlineDot online={p.online} banned={p.banned} />
              {statusOf(p)}
            </span>
            {p.ban ? <Pill tone="danger">Banned until {fmtDateTime(p.ban.expires_at)}</Pill> : null}
          </div>
          <p style={{ margin: 0, fontStyle: 'italic', color: 'var(--muted)' }}>“{p.motto || 'No motto'}”</p>
          <div className="small muted" style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
            <span>Joined {fmtDate(p.account_created)}</span>
            <span>ID #{p.id}</span>
            <span>Last seen {relTime(p.last_online)}</span>
            {rp.corporation ? (
              <span>
                {rp.corporation.rank_name || 'Employee'} at {rp.corporation.name}
              </span>
            ) : null}
            {rp.gang ? (
              <span>
                {rp.gang.role_name} of {rp.gang.name}
              </span>
            ) : null}
          </div>
        </div>
        <div className="hk-actions" style={{ position: 'relative' }}>
          {data.can.edit ? (
            <LegacyLink legacyUrl={legacy} slug={`user-management/users/${p.id}/edit`} variant="secondary">
              Edit
            </LegacyLink>
          ) : null}
          {data.can.chatlogs && me.config.features.includes('room-chatlogs') ? (
            <LegacyLink legacyUrl={legacy} slug={`hotel/plus-chatlogs?tableSearch=${encodeURIComponent(p.username)}`}>
              Chatlogs
            </LegacyLink>
          ) : null}
          {data.can.ban && me.config.features.includes('ban-management') ? (
            <LegacyLink legacyUrl={legacy} slug="user-management/plus-bans/create" variant="danger">
              Ban
            </LegacyLink>
          ) : null}
        </div>
      </div>

      <Tabs tabs={TABS} active={tab} onChange={setTab} />

      {tab === 'overview' ? (
        <div className="hk-grid hk-grid--cards">
          <Card title="Currencies">
            {p.currencies.map((c) => (
              <div key={c.type} className="hk-row">
                {CURRENCY_ICON[c.type] ? <img src={CURRENCY_ICON[c.type]} alt="" className="px-art" style={{ width: 20, height: 20 }} /> : <span style={{ width: 20 }} />}
                <span style={{ flex: 1 }}>{c.label}</span>
                <strong className="tabular">{fmtNumber(c.value)}</strong>
              </div>
            ))}
          </Card>
          <Card title="Badges" action={<span className="small muted">{p.badges.length}{p.badges.length >= 30 ? '+' : ''}</span>}>
            {p.badges.length === 0 ? (
              <div className="hk-card__body muted small">No badges yet.</div>
            ) : (
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, padding: 16 }}>
                {p.badges.map((code) => (
                  <span key={code} className="hk-badgebox" title={code}>
                    <img src={badgeUrl(me.config.badges_path, code)} alt={code} className="px-art" loading="lazy" onError={(e) => ((e.target as HTMLImageElement).style.visibility = 'hidden')} />
                  </span>
                ))}
              </div>
            )}
          </Card>
          <Card title="Moderation">
            <div className="hk-card__body" style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14 }}>
              <div className="hk-kv">
                <span>Bans on record</span>
                <strong>{p.ban_history.length}</strong>
              </div>
              <div className="hk-kv">
                <span>Currently banned</span>
                <strong style={{ color: p.ban ? 'var(--danger-fg)' : undefined }}>{p.ban ? 'Yes' : 'No'}</strong>
              </div>
              <div className="hk-kv">
                <span>Two-factor</span>
                <span style={{ color: p.two_factor ? 'var(--success-fg)' : 'var(--warning-fg)', fontWeight: 600 }}>{p.two_factor ? 'Enabled' : 'Not set up'}</span>
              </div>
              <div className="hk-kv">
                <span>Home room</span>
                <strong>{p.home_room || '—'}</strong>
              </div>
            </div>
          </Card>
        </div>
      ) : null}

      {tab === 'roleplay' ? (
        <div className="hk-grid hk-grid--cards">
          <Card title="Corporation">
            {rp.corporation ? (
              <div className="hk-card__body" style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14 }}>
                <div className="hk-kv">
                  <span>Corporation</span>
                  <Link to={`/roleplay/corporations/${rp.corporation.id}`} style={{ fontWeight: 600 }}>
                    {rp.corporation.name}
                  </Link>
                </div>
                <div className="hk-kv">
                  <span>Rank</span>
                  <strong>
                    {rp.corporation.rank_name || '—'} · tier {rp.corporation.tier}
                  </strong>
                </div>
                <div className="hk-kv">
                  <span>On duty</span>
                  <Pill tone={rp.corporation.on_duty ? 'success' : 'ink'}>{rp.corporation.on_duty ? 'Working now' : 'Off shift'}</Pill>
                </div>
                <div className="hk-kv">
                  <span>Hours this week</span>
                  <strong>{fmtDuration(rp.corporation.shift_seconds_week)}</strong>
                </div>
                <div className="hk-kv">
                  <span>Lifetime</span>
                  <strong>{fmtDuration(rp.corporation.shift_seconds)}</strong>
                </div>
                <div className="hk-kv">
                  <span>Hired</span>
                  <strong>{fmtDate(rp.corporation.hired_at)}</strong>
                </div>
              </div>
            ) : (
              <div className="hk-card__body muted small">Unemployed. Hiring happens in-game for now (hire / superhire commands).</div>
            )}
          </Card>
          <Card title="Gang">
            {rp.gang ? (
              <div className="hk-card__body" style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14 }}>
                <div className="hk-kv">
                  <span>Gang</span>
                  <Link to={`/roleplay/gangs/${rp.gang.id}`} style={{ fontWeight: 600 }}>
                    {rp.gang.name}
                  </Link>
                </div>
                <div className="hk-kv">
                  <span>Role</span>
                  <strong>{rp.gang.role_name}</strong>
                </div>
                <div className="hk-kv">
                  <span>Joined</span>
                  <strong>{fmtDate(rp.gang.joined_at)}</strong>
                </div>
              </div>
            ) : (
              <div className="hk-card__body muted small">Not in a gang.</div>
            )}
          </Card>
        </div>
      ) : null}

      {tab === 'security' ? (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {p.connections === null ? (
            <Card title="IPs & devices">
              <div className="hk-card__body muted small">Hidden — needs the edit players capability.</div>
            </Card>
          ) : (
            <>
              <ConnectionsCard rows={p.connections} lastIp={p.ip} registerIp={p.register_ip} />
              <LinkedCard rows={p.linked ?? []} imager={me.config.imager} />
              <LoginsCard rows={p.logins ?? []} />
            </>
          )}
          <Card title="Email">
            <div className="hk-card__body" style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
              <code style={{ fontSize: 14 }}>{p.mail ?? 'Hidden — needs the edit players capability'}</code>
              {data.can.edit ? (
                <LegacyLink legacyUrl={legacy} slug={`user-management/users/${p.id}/edit`}>
                  Change
                </LegacyLink>
              ) : null}
            </div>
          </Card>
          {data.can.delete ? (
            <div style={{ border: '2px solid var(--danger)', background: 'var(--card)' }}>
              <h2 style={{ fontWeight: 600, fontSize: 14, margin: 0, padding: '12px 16px', borderBottom: '2px solid var(--danger)', color: 'var(--danger-fg)' }}>Danger Zone</h2>
              <div className="hk-card__body" style={{ display: 'flex', gap: 16, alignItems: 'center', flexWrap: 'wrap', fontSize: 14 }}>
                <span style={{ flex: 1, minWidth: 220 }}>
                  <strong>Delete this player</strong>
                  <span className="hk-row__sub">Removes the account, home page and inventory. Handled in the classic panel until the confirmation flow lands here.</span>
                </span>
                <Button variant="danger" size="sm" href={`${legacy}/user-management/users/${p.id}/edit`} external>
                  Open in classic…
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      ) : null}

      {tab === 'moderation' ? (
        <Card title="Ban history">
          {p.ban_history.length === 0 ? (
            <div className="hk-card__body muted small">Clean record.</div>
          ) : (
            p.ban_history.map((b, i) => (
              <div key={i} className="hk-row" style={{ flexWrap: 'wrap' }}>
                <span className={`hk-dot ${b.active ? 'hk-dot--danger' : 'hk-dot--off'}`} />
                <span className="hk-row__main">
                  <span className="hk-row__title">{b.reason || 'No reason recorded'}</span>
                  <span className="hk-row__sub">
                    by {b.added_by || 'unknown'} · {fmtDateTime(b.added_at)}
                  </span>
                </span>
                <span className="small muted">{b.active ? `Expires ${fmtDateTime(b.expires_at)}` : `Expired ${fmtDate(b.expires_at)}`}</span>
              </div>
            ))
          )}
        </Card>
      ) : null}
    </section>
  );
}

/** Every IP and device the account entered the hotel from; a row opens to show all its details. */
function ConnectionsCard({ rows, lastIp, registerIp }: { rows: Connection[]; lastIp: string | null; registerIp: string | null }) {
  const [open, setOpen] = useState<number | null>(null);

  return (
    <Card title="IPs & devices" action={<span className="small muted">{rows.length}</span>}>
      {rows.length === 0 ? (
        <div className="hk-card__body muted small">Nothing logged yet. This fills in the next time they enter the hotel.</div>
      ) : (
        rows.map((c, i) => (
          <div key={`${c.ip}-${c.device}`}>
            <button type="button" className="hk-row--btn" aria-expanded={open === i} onClick={() => setOpen(open === i ? null : i)}>
              <code>{c.ip}</code>
              {c.country ? <Pill tone="ink">{c.country}</Pill> : null}
              <span className="hk-row__main">
                <span className="hk-row__title">
                  {c.browser} on {c.os}
                </span>
                <span className="hk-row__sub">{[c.type, c.screen, c.gpu, `device ${c.device}`].filter(Boolean).join(' · ')}</span>
              </span>
              <span className="small muted" style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                {relTime(c.last_seen)}
                <br />
                {c.visits} {plural(c.visits, 'visit')}
              </span>
            </button>
            {open === i ? (
              <div className="hk-card__body" style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13, background: 'var(--hover)', borderBottom: '2px solid var(--line-soft)' }}>
                <div className="hk-kv">
                  <span className="muted">First seen</span>
                  <span>{fmtDateTime(c.first_seen)}</span>
                </div>
                <div className="hk-kv">
                  <span className="muted">Last seen</span>
                  <span>{fmtDateTime(c.last_seen)}</span>
                </div>
                {c.details.map(([label, value]) => (
                  <div key={label} className="hk-kv">
                    <span className="muted" style={{ flex: 'none' }}>{label}</span>
                    <span style={{ textAlign: 'right', wordBreak: 'break-word', minWidth: 0 }}>{value}</span>
                  </div>
                ))}
                {!c.reported ? <span className="muted small">The browser didn't report its specs on this visit.</span> : null}
              </div>
            ) : null}
          </div>
        ))
      )}
      <div className="hk-row" style={{ flexWrap: 'wrap' }}>
        <code>{lastIp || '—'}</code>
        <span style={{ flex: 1 }} className="small muted">
          Last hotel IP
        </span>
        <code>{registerIp || '—'}</code>
        <span className="small muted">Registration IP</span>
      </div>
    </Card>
  );
}

/** Other accounts that played from the same device, hardware or IP. */
function LinkedCard({ rows, imager }: { rows: LinkedAccount[]; imager: string }) {
  return (
    <Card title="Accounts on the same IP or device" action={<span className="small muted">{rows.length}</span>}>
      {rows.length === 0 ? (
        <div className="hk-card__body muted small">No other account shares an IP or device with this one.</div>
      ) : (
        rows.map((o) => (
          <div key={o.id} className="hk-row" style={{ flexWrap: 'wrap' }}>
            <Link to={`/players/${o.id}`} className="hk-cell-btn" style={{ flex: 1, minWidth: 200 }}>
              <Avatar look={o.look} imager={imager} />
              <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.25, minWidth: 0 }}>
                <span className="name" style={{ fontWeight: 600 }}>
                  {o.username}
                </span>
                <span className="small muted">
                  {o.shared_ips.length === 0
                    ? 'No shared IP'
                    : `${plural(o.shared_ips.length, 'Shared IP')}: ${o.shared_ips.slice(0, 3).join(', ')}${o.shared_ips.length > 3 ? ` and ${o.shared_ips.length - 3} more` : ''}`}
                </span>
              </span>
            </Link>
            {o.same_device ? <Pill tone="danger">Same device</Pill> : null}
            {o.same_hardware && !o.same_device ? <Pill tone="warning">Same hardware</Pill> : null}
            {o.banned ? <Pill tone="danger">Banned</Pill> : null}
            <span className="small muted">{relTime(o.seen_together ?? o.last_online)}</span>
          </div>
        ))
      )}
    </Card>
  );
}

/** The account's website log in attempts, both outcomes. */
function LoginsCard({ rows }: { rows: LoginEntry[] }) {
  return (
    <Card title="Website log ins" action={<span className="small muted">{rows.length}</span>}>
      {rows.length === 0 ? (
        <div className="hk-card__body muted small">No log ins recorded.</div>
      ) : (
        rows.map((l, i) => (
          <div key={i} className="hk-row" style={{ flexWrap: 'wrap' }}>
            <Pill tone={l.ok ? 'success' : 'danger'}>{l.ok ? 'OK' : 'Failed'}</Pill>
            <code>{l.ip || '—'}</code>
            <span className="hk-row__main">
              <span className="hk-row__title">{l.device}</span>
              <span className="hk-row__sub">{LOGIN_METHOD[l.method] ?? l.method}</span>
            </span>
            <span className="small muted">{fmtDateTime(l.at)}</span>
          </div>
        ))
      )}
    </Card>
  );
}
