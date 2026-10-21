import { useState } from 'react';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, qs } from '@hk/lib/api';
import { relTime } from '@hk/lib/format';
import { ActionDialog, Btn, Card, Empty, Loadable, Pill, PlayerChip, PlayerRef, SearchInput, Seg, Skeleton, useDebounced, Wp } from '@hk/ui';

type View = 'flagged' | 'recent' | 'removed' | 'photos';
interface Item {
  key: string;
  kind: 'post' | 'reply' | 'photo_post' | 'photo';
  id: number;
  author: PlayerRef | null;
  at: number;
  body: string;
  photo: { url: string; room: string } | null;
  visible: boolean;
  likes: number | null;
  replies: number | null;
  tags: string[];
  flag: string | null;
  removed: { at: number; by: PlayerRef | null } | null;
}
interface Data { view: View; items: Item[]; counts: Record<View, number>; tags: { tag: string; posts: number; suppressed: boolean }[]; can: { posts: boolean; photos: boolean; moderate: boolean } }

const KIND: Record<Item['kind'], string> = { post: 'Sitch post', reply: 'Reply', photo_post: 'Sitch photo', photo: 'Camera photo' };
type Pending = { item: Item; action: 'remove' | 'restore' | 'hide' | 'show' };

export default function Sitch() {
  useCrumbs([{ label: 'Moderation', to: '/moderation/support' }, { label: 'Sitch and photos' }]);
  const { toast } = useSession();
  const [view, setView] = useState<View | ''>('');
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const state = useFetch<Data>(`/sitch${qs({ view, q })}`);
  const [pending, setPending] = useState<Pending | null>(null);
  const d = state.data;
  const current = (view || d?.view || 'flagged') as View;
  const views: { value: View; label: string }[] = [
    ...(d?.can.posts !== false ? ([['flagged', 'Flagged'], ['recent', 'Recent'], ['removed', 'Removed']] as [View, string][]) : []),
    ...(d?.can.photos ? ([['photos', 'Photos']] as [View, string][]) : []),
  ].map(([value, label]) => ({ value, label: `${label} ${d ? d.counts[value] : ''}` }));

  const toggleTag = async (tag: string, suppressed: boolean) => {
    try {
      const r = await post<{ message: string }>('/sitch/tags', { tag, suppressed });
      toast('ok', r.message);
      state.reload();
    } catch (e) {
      toast('bad', 'Could not change the tag', e instanceof Error ? e.message : undefined);
    }
  };

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Sitch and photos</h1>
          <p className="sub">Posts from the in-phone Sitch app and camera photos · rp_sitch_posts, camera_web</p>
        </div>
        <Seg label="View" value={current} onChange={setView} options={views} />
      </div>
      <div className="filters">
        <SearchInput value={text} onChange={setText} placeholder={current === 'photos' ? 'Room name' : 'Search posts'} width={280} />
        {current === 'flagged' ? <span className="sub">Posts that carry a link or a word-filter word · Sitch has no report button</span> : null}
      </div>
      {d && d.tags.length > 0 && current !== 'photos' ? (
        <Card title="Trending tags" sub="Last 7 days · a suppressed tag stays out of Sitch’s trending list" action={<Wp path="saved" />}>
          <div className="card-b" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {d.tags.map((t) => (
              <span key={t.tag} className="tag" style={{ opacity: t.suppressed ? 0.65 : 1 }}>
                #{t.tag} <span className="mono">{t.posts}</span>
                {d.can.moderate ? (
                  <button type="button" className="btn quiet sm" style={{ height: 18, padding: '0 4px', fontSize: 11 }} onClick={() => toggleTag(t.tag, !t.suppressed)}>
                    {t.suppressed ? 'Allow' : 'Suppress'}
                  </button>
                ) : t.suppressed ? (
                  <Pill>Suppressed</Pill>
                ) : null}
              </span>
            ))}
          </div>
        </Card>
      ) : null}
      {!d ? (
        <div className="card">
          <Skeleton rows={6} />
        </div>
      ) : (
        <Loadable state={state} empty={(x) => (x.items.length === 0 ? <section className="card"><Empty title={current === 'flagged' ? 'Nothing flagged' : 'Nothing here'} body={current === 'flagged' ? 'No post carries a link or a filtered word.' : undefined} /></section> : false)}>
          {(x) => (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(min(100%, 320px), 1fr))', gap: 24, alignItems: 'start' }}>
              {x.items.map((p) => (
                <article key={p.key} className="card" style={{ display: 'flex', flexDirection: 'column' }}>
                  <div className="row" style={{ borderBottom: 0, paddingBottom: 6 }}>
                    <PlayerChip player={p.author} />
                    <Pill>{KIND[p.kind]}</Pill>
                    <span className="grow" />
                    <span className="mono">{relTime(p.at)}</span>
                  </div>
                  {p.photo ? (
                    <div style={{ margin: '6px 20px 0', height: 150, borderRadius: 10, background: 'var(--tile)', display: 'flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' }}>
                      {p.photo.url ? <img src={p.photo.url} alt={`Photo${p.photo.room ? ` in ${p.photo.room}` : ''}`} style={{ width: '100%', height: '100%', objectFit: 'cover', imageRendering: 'pixelated' }} loading="lazy" /> : <span className="mono">Photo{p.photo.room ? ` · ${p.photo.room}` : ''}</span>}
                    </div>
                  ) : null}
                  {p.body ? <p style={{ padding: '10px 20px 0', overflowWrap: 'anywhere' }}>{p.body}</p> : null}
                  <div className="row" style={{ borderBottom: 0, paddingTop: 10, gap: 8, flexWrap: 'wrap' }}>
                    {p.likes !== null ? (
                      <span className="sub">
                        {p.likes} {p.likes === 1 ? 'like' : 'likes'}
                        {p.replies ? ` · ${p.replies} ${p.replies === 1 ? 'reply' : 'replies'}` : ''}
                      </span>
                    ) : p.photo?.room ? (
                      <span className="sub">{p.photo.room}</span>
                    ) : null}
                    {p.tags.map((t) => (
                      <span key={t} className="tag">
                        #{t}
                      </span>
                    ))}
                    {p.flag ? <Pill tone="bad">{p.flag}</Pill> : null}
                    {p.removed ? <Pill>Removed by {p.removed.by?.username ?? 'staff'} · {relTime(p.removed.at)}</Pill> : null}
                    {p.kind === 'photo' && !p.visible ? <Pill>Hidden</Pill> : null}
                  </div>
                  <div style={{ display: 'flex', gap: 8, padding: '12px 20px', borderTop: '1px solid var(--line)', marginTop: 'auto', flexWrap: 'wrap', alignItems: 'center' }}>
                    {p.kind === 'photo' ? (
                      <Btn sm variant={p.visible ? 'danger' : 'default'} onClick={() => setPending({ item: p, action: p.visible ? 'hide' : 'show' })}>
                        {p.visible ? 'Hide photo' : 'Show photo'}
                      </Btn>
                    ) : x.can.moderate ? (
                      p.removed ? (
                        <Btn sm onClick={() => setPending({ item: p, action: 'restore' })}>
                          Restore
                        </Btn>
                      ) : (
                        <Btn sm variant="danger" onClick={() => setPending({ item: p, action: 'remove' })}>
                          Remove
                        </Btn>
                      )
                    ) : null}
                    {p.author ? (
                      <Btn sm variant="quiet" to={`/players/${p.author.id}/discipline`}>
                        Discipline
                      </Btn>
                    ) : null}
                    <span className="grow" />
                    <Wp path="saved" />
                  </div>
                </article>
              ))}
            </div>
          )}
        </Loadable>
      )}
      {pending ? (
        <ActionDialog
          title={{ remove: 'Remove this post', restore: 'Restore this post', hide: 'Hide this photo', show: 'Show this photo' }[pending.action]}
          wp="saved"
          danger={pending.action === 'remove' || pending.action === 'hide'}
          cta={{ remove: 'Remove post', restore: 'Restore post', hide: 'Hide photo', show: 'Show photo' }[pending.action]}
          note={pending.item.kind === 'photo' ? 'Hidden photos leave the website and Sitch.' : 'Sitch reads its posts on every request, so this applies at once. Logged with your reason.'}
          onClose={() => setPending(null)}
          onSubmit={async (reason) => {
            const p = pending.item;
            const r =
              p.kind === 'photo'
                ? await post<{ message: string }>(`/sitch/photos/${p.id}/visibility`, { visible: pending.action === 'show', reason })
                : await post<{ message: string }>(`/sitch/posts/${p.id}/${pending.action === 'remove' ? 'hide' : 'restore'}`, { reason });
            toast('ok', r.message);
            state.reload();
          }}
        />
      ) : null}
    </main>
  );
}
