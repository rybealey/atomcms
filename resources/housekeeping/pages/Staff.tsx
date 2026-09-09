import { useState } from 'react';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put } from '@hk/lib/api';
import { badgeUrl } from '@hk/lib/avatar';
import { fmtNumber } from '@hk/lib/format';
import { Card, ErrorBox, IconButton, LegacyLink, rankTone, Skeleton, Tabs } from '@hk/ui';

interface Perm { permission: string; min_rank: number; description: string }
interface StaffData { ranks: { id: number; name: string; badge: string; members: number }[]; permissions: Perm[] }

const LABELS: Record<string, string> = {
  can_access_housekeeping: 'Access housekeeping',
  edit_user: 'Edit players',
  reset_user_password: 'Reset passwords',
  delete_user: 'Delete players',
  manage_bans: 'Ban and unban',
  manage_room_chatlogs: 'Read room chatlogs',
  manage_private_chatlogs: 'Read private chats',
  manage_commandlogs: 'Read command logs',
  write_article: 'Write articles',
  edit_article: 'Edit articles',
  delete_article: 'Delete articles',
  delete_article_comments: 'Delete comments',
  manage_article_tags: 'Manage tags',
  manage_badges: 'Manage badges',
  manage_camera_web: 'Moderate photos',
  manage_shop: 'Manage the shop',
  manage_website_settings: 'Change settings',
  delete_website_settings: 'Delete settings',
  manage_staff_applications: 'Review applications',
  manage_permissions: 'Edit rank permissions',
  manage_housekeeping_permissions: 'Edit this matrix',
  delete_permissions: 'Delete ranks',
  view_activity_logs: 'View audit log',
  view_server_logs: 'View server logs',
  manage_achievements: 'Manage achievements',
  manage_catalog_pages: 'Manage catalog',
  delete_catalog_pages: 'Delete catalog pages',
  manage_emulator_settings: 'Emulator settings',
  manage_emulator_texts: 'Emulator texts',
  manage_home_items: 'Home page items',
  manage_teams: 'Manage teams',
  manage_website_ads: 'Manage ads',
  manage_website_blacklists: 'IP blacklist',
  manage_website_whitelists: 'IP whitelist',
  manage_wordfilter: 'Word filter',
  manage_website_tickets: 'Help tickets',
  delete_website_tickets: 'Delete tickets',
  delete_website_ticket_replies: 'Delete ticket replies',
  generate_logo: 'Logo generator',
  housekeeping_access: 'Housekeeping link on site',
  bypass_vpn: 'Bypass VPN block',
};

function shortRank(name: string): string {
  return name.replace(/Trial Moderators?/i, 'Trial mod').replace(/Senior Moderators?/i, 'Senior mod').replace(/Community Leaders?/i, 'Comm. lead').replace(/Administrators?/i, 'Admin').replace(/Moderators?/i, 'Mod').replace(/Developers?/i, 'Dev').replace(/Managers?/i, 'Mgr');
}

export default function Staff() {
  useCrumbs([{ label: 'Settings' }, { label: 'Staff' }]);
  const { me, can, toast } = useSession();
  const { data, error, loading, reload, setData } = useFetch<StaffData>('/staff');
  const [tab, setTab] = useState('ranks');
  const [hint, setHint] = useState<string | null>(null);
  const canEdit = can('manage_housekeeping_permissions');

  const setThreshold = async (perm: Perm, rank: number) => {
    if (!canEdit || perm.min_rank === rank) return;
    try {
      const result = await put<{ permissions: Perm[] }>(`/staff/permissions/${perm.permission}`, { min_rank: rank });
      setData((d) => (d ? { ...d, permissions: result.permissions } : d));
      toast('success', 'Saved', `${LABELS[perm.permission] ?? perm.permission} now starts at rank ${rank}.`);
    } catch (e) {
      toast('danger', 'Not saved', e instanceof Error ? e.message : 'Unknown error');
    }
  };

  if (error) return <ErrorBox message={error} retry={reload} />;
  const ranks = data?.ranks ?? [];
  const matrixRanks = ranks.filter((r) => r.id > 1);

  return (
    <section className="hk-page" aria-label="Staff ranks and permissions">
      <div className="hk-pagehead">
        <div>
          <h1 className="hk-h1" style={{ marginBottom: 6 }}>Staff</h1>
          <p className="hk-lede">
            {ranks.length} ranks. A capability is granted from a minimum rank upward — click the rank where it should start.
          </p>
        </div>
        <Tabs tabs={[{ id: 'ranks', label: 'Ranks' }, { id: 'perms', label: 'Permissions' }]} active={tab} onChange={setTab} />
      </div>

      {loading || !data ? (
        <Card>
          <Skeleton rows={8} />
        </Card>
      ) : tab === 'ranks' ? (
        <Card>
          {ranks.map((r) => (
            <div key={r.id} className="hk-row" style={{ minHeight: 60, gap: 14, flexWrap: 'wrap' }}>
              <span className={`hk-rank ${rankTone(r.id, me.config.min_staff_rank)}`} style={{ width: 36, height: 36, justifyContent: 'center', padding: 0, fontWeight: 700, fontSize: 14 }}>
                {r.id}
              </span>
              <span className="hk-row__main" style={{ minWidth: 160 }}>
                <span className="hk-row__title">{r.name}</span>
                <span className="hk-row__sub">{fmtNumber(r.members)} members{r.badge ? ` · badge ${r.badge}` : ''}</span>
              </span>
              {r.badge ? (
                <img src={badgeUrl(me.config.badges_path, r.badge)} alt="" className="px-art" style={{ width: 32, height: 32, border: '2px solid var(--line)', background: 'var(--sunken)' }} onError={(e) => ((e.target as HTMLImageElement).style.visibility = 'hidden')} />
              ) : null}
              {can('manage_permissions') ? (
                <LegacyLink legacyUrl={me.config.legacy_url} slug={`website/permissions/${r.id}/edit`}>
                  Edit rank
                </LegacyLink>
              ) : null}
              <IconButton name="more-horizontal" label={`Actions for ${r.name}`} plain disabled />
            </div>
          ))}
        </Card>
      ) : (
        <>
          <div className="hk-card hk-tablewrap">
            <table className="hk-table hk-matrix" style={{ minWidth: 760 }}>
              <thead>
                <tr>
                  <th scope="col" className="sticky">Capability</th>
                  {matrixRanks.map((r) => (
                    <th key={r.id} scope="col" className="rank" title={r.name}>
                      <b>{r.id}</b>
                      <span>{shortRank(r.name)}</span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.permissions.map((perm) => {
                  const startName = ranks.find((r) => r.id === perm.min_rank)?.name ?? `rank ${perm.min_rank}`;
                  return (
                    <tr key={perm.permission} style={{ height: 52 }}>
                      <th scope="row" className="sticky" title={perm.description}>
                        <span style={{ display: 'block' }}>{LABELS[perm.permission] ?? perm.permission.replace(/_/g, ' ')}</span>
                        <span className="small muted" style={{ display: 'block', fontSize: 12 }}>
                          from {startName} ({perm.min_rank})
                        </span>
                      </th>
                      {matrixRanks.map((r) => {
                        const on = r.id >= perm.min_rank;
                        const start = r.id === perm.min_rank;
                        const affected = on
                          ? ranks.filter((x) => x.id >= perm.min_rank && x.id < r.id).reduce((a, x) => a + x.members, 0)
                          : ranks.filter((x) => x.id >= r.id && x.id < perm.min_rank).reduce((a, x) => a + x.members, 0);
                        const label = LABELS[perm.permission] ?? perm.permission;
                        return (
                          <td key={r.id}>
                            <button
                              type="button"
                              className={`hk-cell ${on ? 'hk-cell--on' : ''} ${start ? 'hk-cell--start' : ''}`}
                              aria-pressed={on}
                              aria-label={`${label}: ${on ? 'allowed' : 'not allowed'} for ${r.name}`}
                              disabled={!canEdit}
                              onClick={() => setThreshold(perm, r.id)}
                              onMouseEnter={() => setHint(start ? `${label} starts at ${r.name} (${r.id}).` : r.id < perm.min_rank ? `Set ${label} to start at ${r.name}: ${affected} staff would gain it.` : `Set ${label} to start at ${r.name}: ${affected} staff would lose it.`)}
                              onMouseLeave={() => setHint(null)}
                            >
                              <span>{on ? '✓' : '–'}</span>
                            </button>
                          </td>
                        );
                      })}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <div role="status" className="small muted" style={{ minHeight: 24 }}>
            {hint ?? (canEdit ? 'Hover a cell to see who would be affected. Click to move the threshold.' : 'You can view this matrix but not change it.')}
          </div>
        </>
      )}
    </section>
  );
}
