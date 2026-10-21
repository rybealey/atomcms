import { useState } from 'react';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { del, patch, post } from '@hk/lib/api';
import { fmtDateTime, relTime } from '@hk/lib/format';
import { Btn, Empty, Field, Loadable, Pill, PlayerRef, Seg, Wp } from '@hk/ui';
import { useSave } from './shared';

interface Post { id: number; title: string; body: string; category: string; image: string; pinned: boolean; anonymous: boolean; author: PlayerRef | null; created: number; updated: number }
interface Data { posts: Post[]; categories: string[]; byline: PlayerRef; images: string[] }

const IMAGE_ROOT = '/assets/images/articles/';

export default function PhoneNews() {
  useCrumbs([{ label: 'Content', to: '/content/badges' }, { label: 'Phone news' }]);
  const state = useFetch<Data>('/news');
  const [show, setShow] = useState<'all' | 'pinned'>('all');
  const [sel, setSel] = useState<number | 'new' | null>(null);
  const posts = (state.data?.posts ?? []).filter((p) => show === 'all' || p.pinned);
  const cur = sel === 'new' ? null : posts.find((p) => p.id === sel) ?? posts[0] ?? null;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Phone news</h1>
          <p className="sub">rp_news_posts · the phone's News app, the city's one news source</p>
        </div>
        <Btn variant="primary" onClick={() => setSel('new')}>
          New post
        </Btn>
      </div>
      <div className="split">
        <section className="card" style={{ flex: '1 1 300px' }} aria-label="Posts">
          <div className="card-h">
            <Seg label="Show" value={show} onChange={setShow} options={[{ value: 'all', label: 'All' }, { value: 'pinned', label: 'Pinned' }]} />
          </div>
          <Loadable state={state} rows={5} empty={() => (posts.length === 0 ? <Empty title={show === 'pinned' ? 'Nothing pinned' : 'No stories yet'} /> : false)}>
            {() =>
              posts.map((p) => (
                <button key={p.id} type="button" className={`row ${sel !== 'new' && cur?.id === p.id ? 'on' : ''}`} aria-pressed={sel !== 'new' && cur?.id === p.id} onClick={() => setSel(p.id)} style={{ alignItems: 'flex-start' }}>
                  <span className="grow stack16" style={{ gap: 4 }}>
                    <span style={{ fontWeight: 500 }}>{p.title}</span>
                    <span className="sub">
                      {p.category} · {relTime(p.created)}
                    </span>
                  </span>
                  <Pill tone={p.pinned ? 'brand' : 'ok'}>{p.pinned ? 'Pinned' : 'Published'}</Pill>
                </button>
              ))
            }
          </Loadable>
        </section>
        {state.data && (cur || sel === 'new') ? (
          <Editor
            key={sel === 'new' ? 'new' : cur?.id}
            post={sel === 'new' ? null : cur}
            data={state.data}
            onDone={(id) => {
              setSel(id);
              state.reload();
            }}
          />
        ) : null}
      </div>
    </main>
  );
}

function Editor({ post: p, data, onDone }: { post: Post | null; data: Data; onDone: (id: number | null) => void }) {
  const { me, confirm } = useSession();
  const [f, setF] = useState({
    title: p?.title ?? '',
    body: p?.body ?? '',
    category: p?.category ?? data.categories[0],
    image: p?.image ?? '',
    pinned: p?.pinned ?? false,
    anonymous: p?.anonymous ?? true,
  });
  const { busy, run } = useSave();
  const writer = p?.author ?? { id: me.user.id, username: me.user.username, look: me.user.look };
  const byline = f.anonymous ? data.byline.username : writer.username;
  const when = p ? fmtDateTime(p.created) : 'Now';
  const valid = f.title.trim() !== '' && f.body.trim() !== '';

  return (
    <>
      <section className="card" style={{ flex: '999 1 420px', minWidth: 0 }} aria-label={p ? 'Edit post' : 'New post'}>
        <div className="card-h">
          <h3>{p ? 'Edit post' : 'New post'}</h3>
          <Wp path="saved" />
        </div>
        <div className="card-b stack16" style={{ gap: 14 }}>
          <Field label="Headline">
            <input className="input" value={f.title} onChange={(e) => setF({ ...f, title: e.target.value })} maxLength={120} />
          </Field>
          <Field label="Story">
            <textarea className="input" rows={6} value={f.body} onChange={(e) => setF({ ...f, body: e.target.value })} maxLength={4000} />
          </Field>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: 12 }}>
            <Field label="Category">
              <select className="input" value={f.category} onChange={(e) => setF({ ...f, category: e.target.value })}>
                {(data.categories.includes(f.category) ? data.categories : [f.category, ...data.categories]).map((c) => (
                  <option key={c}>{c}</option>
                ))}
              </select>
            </Field>
            <Field label="Byline">
              <select className="input" value={f.anonymous ? 'desk' : 'writer'} onChange={(e) => setF({ ...f, anonymous: e.target.value === 'desk' })}>
                <option value="desk">{data.byline.username} (newsroom)</option>
                <option value="writer">{writer.username}</option>
              </select>
            </Field>
            <Field label="Published">
              <input className="input" value={when} readOnly />
            </Field>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: 12, border: '1px dashed var(--line-strong)', borderRadius: 10 }}>
            <span style={{ width: 64, height: 40, borderRadius: 6, background: 'var(--tile)', flex: 'none', overflow: 'hidden' }} aria-hidden="true">
              {f.image ? <img src={IMAGE_ROOT + f.image} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} className="px" /> : null}
            </span>
            <label className="grow field" style={{ gap: 4 }}>
              <span className="sub">Header image · from the article image library</span>
              <select className="input" value={f.image} onChange={(e) => setF({ ...f, image: e.target.value })}>
                <option value="">No image</option>
                {data.images.map((i) => (
                  <option key={i}>{i}</option>
                ))}
              </select>
            </label>
          </div>
          <label className="check">
            <input type="checkbox" checked={f.pinned} onChange={(e) => setF({ ...f, pinned: e.target.checked })} />
            Pin to the top of the phone news app (unpins the story pinned now)
          </label>
          <p className="sub" style={{ fontSize: 12 }}>
            {p ? 'Players see the change the next time they open News.' : 'Players see it the next time they open News; the phone does not send a notification for stories posted from here.'}
          </p>
        </div>
        <div className="card-f" style={{ justifyContent: p ? 'space-between' : 'flex-end' }}>
          {p ? (
            <Btn
              variant="danger"
              disabled={busy}
              onClick={async () => {
                if (!(await confirm({ title: 'Delete this story?', body: 'It disappears from the phone and the website at once. It is kept in the staff audit trail.', cta: 'Delete story', danger: true }))) return;
                if (await run(() => del(`/news/${p.id}`))) onDone(null);
              }}
            >
              Delete
            </Btn>
          ) : null}
          <Btn
            variant="primary"
            disabled={busy || !valid}
            onClick={async () => {
              let id: number | null = p?.id ?? null;
              const ok = await run(async () => {
                if (p) return patch(`/news/${p.id}`, f);
                const r = await post<{ id: number; message: string }>('/news', f);
                id = r.id;
                return r;
              });
              if (ok) onDone(id);
            }}
          >
            {p ? 'Save' : 'Publish'}
          </Btn>
        </div>
      </section>
      <aside style={{ flex: '0 0 300px', display: 'flex', flexDirection: 'column', gap: 10, alignItems: 'center' }} aria-label="Phone preview">
        <span className="sub">How it looks in the phone</span>
        <div style={{ width: 280, height: 520, borderRadius: 36, border: '8px solid #1e0e18', background: '#fbf5ef', overflow: 'hidden', display: 'flex', flexDirection: 'column', color: '#1e0e18', boxShadow: 'var(--shadow-md)' }}>
          <div style={{ padding: '18px 16px 10px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid #e8d8cf' }}>
            <span style={{ fontWeight: 600, fontSize: 15 }}>City News</span>
            <span style={{ fontSize: 11, color: '#5f4655' }}>{f.category}</span>
          </div>
          <div style={{ height: 120, background: '#efe3da', flex: 'none' }}>{f.image ? <img src={IMAGE_ROOT + f.image} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : null}</div>
          <div style={{ padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 8, overflow: 'hidden' }}>
            <span style={{ fontWeight: 600, fontSize: 17, lineHeight: '22px' }}>{f.title || 'Headline'}</span>
            <span style={{ fontSize: 11, color: '#5f4655' }}>
              {byline} · {when}
            </span>
            <span style={{ fontSize: 13, lineHeight: '19px', whiteSpace: 'pre-line' }}>{f.body}</span>
          </div>
        </div>
      </aside>
    </>
  );
}
