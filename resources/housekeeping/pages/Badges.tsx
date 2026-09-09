import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { badgeUrl } from '@hk/lib/avatar';
import { Card, ErrorBox, LegacyLink, Pager, SearchBox, Skeleton, Tabs } from '@hk/ui';

interface BadgeRow { code: string; name: string; description: string }
interface BadgeList { items: BadgeRow[]; total: number; page: number; last_page: number }

export default function Badges() {
  useCrumbs([{ label: 'Hotel' }, { label: 'Badges' }]);
  const { me } = useSession();
  const [params, setParams] = useSearchParams();
  const tab = params.get('tab') ?? 'library';
  const q = params.get('q') ?? '';
  const page = Number(params.get('page') ?? '1') || 1;
  const [search, setSearch] = useState(q);
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

  const path = useMemo(() => `/badges?page=${page}${q ? `&q=${encodeURIComponent(q)}` : ''}`, [page, q]);
  const { data, error, loading, reload } = useFetch<BadgeList>(tab === 'library' || tab === 'texts' ? path : null);
  const legacy = me.config.legacy_url;
  const setTab = (id: string) => {
    const next = new URLSearchParams(params);
    next.set('tab', id);
    next.delete('page');
    setParams(next);
  };

  return (
    <section className="hk-page" aria-label="Badges">
      <div className="hk-pagehead">
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
          <h1 className="hk-h1">Badges</h1>
          {data ? <span className="small muted">{data.total.toLocaleString('en-US')} in the library</span> : null}
        </div>
        <div className="hk-actions">
          <LegacyLink legacyUrl={legacy} slug="badge-page" variant="primary">
            Add Badge
          </LegacyLink>
        </div>
      </div>
      <Tabs
        tabs={[
          { id: 'library', label: 'Library' },
          { id: 'texts', label: 'Texts' },
          { id: 'uploads', label: 'Uploads' },
          { id: 'requests', label: me.counts.draw_badges > 0 ? `Requests · ${me.counts.draw_badges}` : 'Requests' },
        ]}
        active={tab}
        onChange={setTab}
      />

      {error ? <ErrorBox message={error} retry={reload} /> : null}

      {tab === 'library' || tab === 'texts' ? (
        <>
          <SearchBox value={search} onChange={setSearch} placeholder="Code, name or description" width={360} />
          {loading && !data ? (
            <Card>
              <Skeleton rows={6} />
            </Card>
          ) : data ? (
            tab === 'library' ? (
              <>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: 12 }}>
                  {data.items.map((b) => (
                    <a key={b.code} className="hk-card" href={`${legacy}/badge-page?code=${encodeURIComponent(b.code)}`} target="_blank" rel="noreferrer" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, padding: '14px 10px', color: 'var(--fg)', textAlign: 'center', textDecoration: 'none' }}>
                      <span className="hk-badgebox" style={{ width: 56, height: 56 }}>
                        <img src={badgeUrl(me.config.badges_path, b.code)} alt="" className="px-art" style={{ width: 48, height: 48 }} loading="lazy" onError={(e) => ((e.target as HTMLImageElement).style.visibility = 'hidden')} />
                      </span>
                      <code className="muted">{b.code}</code>
                      <span style={{ fontSize: 13, fontWeight: 600, lineHeight: 1.3 }}>{b.name}</span>
                    </a>
                  ))}
                </div>
                {data.items.length === 0 ? <div className="hk-empty hk-empty--dashed">No badges match.</div> : null}
                <div className="hk-card hk-card--flat">
                  <Pager page={data.page} lastPage={data.last_page} total={data.total} perPage={60} shown={data.items.length} onPage={(p) => setParams((prev) => { const n = new URLSearchParams(prev); n.set('page', String(p)); return n; })} />
                </div>
              </>
            ) : (
              <div className="hk-card hk-tablewrap">
                <table className="hk-table" style={{ minWidth: 600 }}>
                  <thead>
                    <tr>
                      <th>Badge</th>
                      <th>Name</th>
                      <th>Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((b) => (
                      <tr key={b.code}>
                        <td>
                          <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <img src={badgeUrl(me.config.badges_path, b.code)} alt="" className="px-art" style={{ width: 32, height: 32 }} loading="lazy" onError={(e) => ((e.target as HTMLImageElement).style.visibility = 'hidden')} />
                            <code>{b.code}</code>
                          </span>
                        </td>
                        <td style={{ fontWeight: 600 }}>{b.name}</td>
                        <td className="muted" style={{ maxWidth: 360 }}>
                          <span title={b.description} style={{ display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{b.description}</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <Pager page={data.page} lastPage={data.last_page} total={data.total} perPage={60} shown={data.items.length} onPage={(p) => setParams((prev) => { const n = new URLSearchParams(prev); n.set('page', String(p)); return n; })} />
              </div>
            )
          ) : null}
        </>
      ) : null}

      {tab === 'uploads' ? (
        <div className="hk-legacy">
          <iframe title="Badge uploads" src={`${legacy}/manage-badge-uploads`} />
        </div>
      ) : null}
      {tab === 'requests' ? (
        <div className="hk-legacy">
          <iframe title="Drawn badge requests" src={`${legacy}/draw-badges`} />
        </div>
      ) : null}
    </section>
  );
}
