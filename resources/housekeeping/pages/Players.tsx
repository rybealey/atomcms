import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { fmtDate, relTime } from '@hk/lib/format';
import { Avatar, ErrorBox, OnlineDot, Pager, RankChip, RowMenu, SearchBox, Skeleton } from '@hk/ui';

export interface PlayerSummary {
  id: number; username: string; motto: string; look: string; rank: number; rank_name: string; online: boolean; last_online: number; account_created: number; mail: string | null; ip: string | null; banned: boolean;
}
interface PlayerList { items: PlayerSummary[]; total: number; page: number; per_page: number; last_page: number; counts: Record<string, number> }

const FILTERS: { id: string; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'online', label: 'Online' },
  { id: 'banned', label: 'Banned' },
  { id: 'staff', label: 'Staff' },
  { id: 'new', label: 'New this week' },
];

export function statusOf(p: PlayerSummary): string {
  if (p.banned && !p.online) return 'Banned';
  if (p.online) return 'Online';
  return `Seen ${relTime(p.last_online)}`;
}

function useIsMobile(): boolean {
  const [m, setM] = useState(() => window.innerWidth < 768);
  useEffect(() => {
    const f = () => setM(window.innerWidth < 768);
    window.addEventListener('resize', f);
    return () => window.removeEventListener('resize', f);
  }, []);
  return m;
}

export default function Players() {
  useCrumbs([{ label: 'Community' }, { label: 'Players' }]);
  const { me, can } = useSession();
  const navigate = useNavigate();
  const mobile = useIsMobile();
  const [params, setParams] = useSearchParams();
  const filter = params.get('filter') ?? 'all';
  const q = params.get('q') ?? '';
  const page = Number(params.get('page') ?? '1') || 1;
  const perPage = Number(params.get('per_page') ?? '25') || 25;
  const [search, setSearch] = useState(q);
  const [revealed, setRevealed] = useState<Record<number, boolean>>({});

  useEffect(() => setSearch(q), [q]);
  useEffect(() => {
    const t = window.setTimeout(() => {
      if (search !== q) {
        const next = new URLSearchParams(params);
        if (search) next.set('q', search);
        else next.delete('q');
        next.delete('page');
        setParams(next, { replace: true });
      }
    }, 300);
    return () => window.clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const path = useMemo(() => `/players?filter=${filter}&page=${page}&per_page=${perPage}${q ? `&q=${encodeURIComponent(q)}` : ''}`, [filter, page, perPage, q]);
  const { data, error, loading, reload } = useFetch<PlayerList>(path);

  const update = (patch: Record<string, string | null>) => {
    const next = new URLSearchParams(params);
    for (const [k, v] of Object.entries(patch)) {
      if (v === null) next.delete(k);
      else next.set(k, v);
    }
    setParams(next);
  };

  const banFilterAvailable = me.config.features.includes('ban-management');
  const filters = FILTERS.filter((f) => f.id !== 'banned' || banFilterAvailable);
  const canSeeEmail = can('edit_user');

  return (
    <section className="hk-page" aria-label="Players">
      <div className="hk-pagehead" style={{ alignItems: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
          <h1 className="hk-h1">Players</h1>
          {data ? (
            <span className="small muted">
              {data.items.length} shown · {data.total.toLocaleString('en-US')} {q || filter !== 'all' ? 'matching' : 'total'}
            </span>
          ) : null}
        </div>
        <div className="hk-actions">
          <SearchBox value={search} onChange={setSearch} placeholder={canSeeEmail ? 'Username, email or IP' : 'Username'} width={240} />
        </div>
      </div>

      <div role="radiogroup" aria-label="Filter" style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
        {filters.map((f) => (
          <button key={f.id} type="button" className="hk-chip" aria-pressed={filter === f.id} onClick={() => update({ filter: f.id === 'all' ? null : f.id, page: null })}>
            {f.label}
            {data?.counts?.[f.id] !== undefined ? <span>{data.counts[f.id].toLocaleString('en-US')}</span> : null}
          </button>
        ))}
      </div>

      {error ? <ErrorBox message={error} retry={reload} /> : null}

      {!mobile ? (
        <div className="hk-card hk-tablewrap">
          {loading && !data ? (
            <Skeleton rows={6} />
          ) : data ? (
            <>
              <table className="hk-table">
                <thead>
                  <tr>
                    <th scope="col" className="sticky">Player</th>
                    <th scope="col">Rank</th>
                    <th scope="col">Status</th>
                    <th scope="col">Joined</th>
                    {canSeeEmail ? <th scope="col">Email</th> : null}
                    <th scope="col" style={{ width: 56 }}>
                      <span className="sr-only">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.items.map((p) => (
                    <tr key={p.id}>
                      <td className="sticky">
                        <Link to={`/players/${p.id}`} className="hk-cell-btn">
                          <Avatar look={p.look} imager={me.config.imager} />
                          <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.25 }}>
                            <span className="name" style={{ fontWeight: 600 }}>{p.username}</span>
                            <span className="small muted" style={{ maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.motto}</span>
                          </span>
                        </Link>
                      </td>
                      <td>
                        <RankChip rank={p.rank} name={p.rank_name} minStaff={me.config.min_staff_rank} />
                      </td>
                      <td>
                        <span className="hk-status">
                          <OnlineDot online={p.online} banned={p.banned} />
                          {statusOf(p)}
                        </span>
                      </td>
                      <td className="muted">{fmtDate(p.account_created)}</td>
                      {canSeeEmail ? (
                        <td>
                          {revealed[p.id] ? (
                            <span className="mono" style={{ fontSize: 13 }}>{p.mail ?? '—'}</span>
                          ) : (
                            <button type="button" className="hk-linkbtn" style={{ padding: 0, fontSize: 13 }} onClick={() => setRevealed((r) => ({ ...r, [p.id]: true }))}>
                              Reveal email
                            </button>
                          )}
                        </td>
                      ) : null}
                      <td style={{ padding: '0 8px' }}>
                        <RowMenu label={`Actions for ${p.username}`}>
                          <button type="button" role="menuitem" onClick={() => navigate(`/players/${p.id}`)}>
                            View
                          </button>
                          {can('edit_user') && p.rank < me.user.rank ? (
                            <a role="menuitem" href={`${me.config.legacy_url}/user-management/users/${p.id}/edit`} target="_blank" rel="noreferrer">
                              Edit in classic
                            </a>
                          ) : null}
                          {can('manage_room_chatlogs') && me.config.features.includes('room-chatlogs') ? (
                            <a role="menuitem" href={`${me.config.legacy_url}/hotel/plus-chatlogs?tableSearch=${encodeURIComponent(p.username)}`} target="_blank" rel="noreferrer">
                              Chatlogs
                            </a>
                          ) : null}
                        </RowMenu>
                      </td>
                    </tr>
                  ))}
                  {data.items.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="muted" style={{ textAlign: 'center', height: 96 }}>
                        No players match.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
              <Pager page={data.page} lastPage={data.last_page} total={data.total} perPage={data.per_page} shown={data.items.length} onPage={(p) => update({ page: String(p) })} onPerPage={(n) => update({ per_page: String(n), page: null })} />
            </>
          ) : null}
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {data?.items.map((p) => (
            <Link key={p.id} to={`/players/${p.id}`} className="hk-mobilecard">
              <Avatar look={p.look} imager={me.config.imager} size="lg" />
              <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
                <span style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                  <strong>{p.username}</strong>
                  <span className="small muted">{fmtDate(p.account_created)}</span>
                </span>
                <span className="hk-status" style={{ fontSize: 13 }}>
                  <OnlineDot online={p.online} banned={p.banned} />
                  {statusOf(p)} · {p.rank_name}
                </span>
              </span>
              <span aria-hidden="true" style={{ fontWeight: 600, color: 'var(--muted)' }}>▶</span>
            </Link>
          ))}
          {data ? <Pager page={data.page} lastPage={data.last_page} total={data.total} perPage={data.per_page} shown={data.items.length} onPage={(p) => update({ page: String(p) })} /> : null}
        </div>
      )}
    </section>
  );
}
