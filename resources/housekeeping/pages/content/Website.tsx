import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { del, get, post, put, qs } from '@hk/lib/api';
import { fmtDay, fmtNumber } from '@hk/lib/format';
import { Btn, Coin, Empty, Field, Icon, Loadable, Note, Pill, PlayerChip, PlayerRef, SearchInput, Tabs, useDebounced, Wp } from '@hk/ui';
import { BadgeArt, ImagePick, ListEditor, useSave } from './shared';

type Type = 'teams' | 'ads' | 'homeitems' | 'tags' | 'betacodes';
interface Team { id: number; name: string; description: string; badge: string; visible: boolean; color: string; members: PlayerRef[] }
interface Ad { id: number; image: string; url: string; created: number }
interface HomeItem { id: number; type: 's' | 'w' | 'n' | 'b'; name: string; image: string; url: string; category_id: number | null; category: string | null; price: number; currency: number; enabled: boolean; limit: number | null; sold: number; order: number }
interface Tag { id: number; name: string; color: string }
interface BetaCode { id: number; code: string; player: PlayerRef | null; created: number }
interface Data { types: Type[]; counts: Record<Type, number>; type: Type; items: unknown[]; categories?: { value: string; label: string }[] }

const LABELS: Record<Type, string> = { teams: 'Teams', ads: 'Ads', homeitems: 'Home items', tags: 'Tags', betacodes: 'Beta codes' };
const KINDS: Record<HomeItem['type'], string> = { s: 'Sticker', w: 'Widget', n: 'Note', b: 'Background' };
const CURRENCIES: Record<number, string> = { [-1]: 'Coins', 0: 'Duckets', 5: 'Diamonds', 101: 'Points' };

export default function Website() {
  useCrumbs([{ label: 'Content', to: '/content/badges' }, { label: 'Website' }]);
  const [params, setParams] = useSearchParams();
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const want = params.get('type') ?? '';
  const state = useFetch<Data>(`/website${qs({ type: want, q })}`);
  const d = state.data;
  const type = d?.type ?? (want as Type);
  useEffect(() => setText(''), [want]);

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Website</h1>
          <p className="sub">Content shown on the public site</p>
        </div>
      </div>
      <Note tone="info" title="Website articles are retired with the site redesign and are no longer edited here." action={<Link to="/content/news" style={{ fontSize: 13, color: 'var(--info-ink)', textDecoration: 'underline' }}>Go to Phone news</Link>}>
        City news is written in Phone news.
      </Note>
      {d ? <Tabs label="Website sections" active={type} onChange={(t) => setParams({ type: t }, { replace: true })} tabs={d.types.map((t) => ({ id: t, label: LABELS[t], n: d.counts[t] }))} /> : null}
      <Loadable state={state} rows={6}>
        {(x) => (
          <>
            {x.type === 'teams' ? <Teams items={x.items as Team[]} onChanged={state.reload} /> : null}
            {x.type === 'ads' ? <Ads items={x.items as Ad[]} onChanged={state.reload} /> : null}
            {x.type === 'homeitems' ? <HomeItems items={x.items as HomeItem[]} categories={x.categories ?? []} text={text} setText={setText} onChanged={state.reload} /> : null}
            {x.type === 'tags' ? <Tags items={x.items as Tag[]} onChanged={state.reload} /> : null}
            {x.type === 'betacodes' ? <BetaCodes items={x.items as BetaCode[]} text={text} setText={setText} onChanged={state.reload} /> : null}
          </>
        )}
      </Loadable>
    </main>
  );
}

/* ---------- Teams ---------- */

function Teams({ items, onChanged }: { items: Team[]; onChanged: () => void }) {
  const [sel, setSel] = useState<number | 'new' | null>(null);
  const cur = sel === 'new' ? null : items.find((t) => t.id === sel) ?? items[0] ?? null;
  return (
    <ListEditor
      list={
        <section className="card">
          <div className="card-h">
            <div>
              <h3>Teams</h3>
              <p className="sub">website_teams · the order they appear on the staff page</p>
            </div>
            <Btn sm onClick={() => setSel('new')}>
              Add team
            </Btn>
          </div>
          {items.length === 0 ? <Empty title="No teams yet" /> : null}
          {items.map((t) => (
            <button key={t.id} type="button" className={`row ${sel !== 'new' && cur?.id === t.id ? 'on' : ''}`} aria-pressed={sel !== 'new' && cur?.id === t.id} onClick={() => setSel(t.id)}>
              {t.badge ? <BadgeArt code={t.badge} size={30} /> : <Icon name="people" />}
              <span className="grow">
                <span style={{ display: 'block', fontWeight: 500 }}>{t.name}</span>
                <span className="sub">{t.description || 'No description'}</span>
              </span>
              <span className="sub">{t.members.length} members</span>
              <Pill tone={t.visible ? 'ok' : ''}>{t.visible ? 'Shown' : 'Hidden'}</Pill>
            </button>
          ))}
        </section>
      }
      editor={
        cur || sel === 'new' ? (
          <TeamEditor
            key={sel === 'new' ? 'new' : cur?.id}
            team={sel === 'new' ? null : cur}
            onDone={(id) => {
              setSel(id);
              onChanged();
            }}
          />
        ) : null
      }
    />
  );
}

function TeamEditor({ team, onDone }: { team: Team | null; onDone: (id: number | null) => void }) {
  const { confirm } = useSession();
  const [f, setF] = useState({ name: team?.name ?? '', description: team?.description ?? '', badge: team?.badge ?? '', visible: team?.visible ?? true });
  const [members, setMembers] = useState<PlayerRef[]>(team?.members ?? []);
  const [adding, setAdding] = useState(false);
  const { busy, run } = useSave();
  const body = { ...f, members: members.map((m) => m.id) };

  return (
    <aside className="peek side-col" aria-label="Edit team" style={{ flexBasis: 380 }}>
      <div className="card-h">
        <h3>{team ? team.name : 'New team'}</h3>
        <Wp path="saved" />
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <Field label="Name">
          <input className="input" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} maxLength={255} />
        </Field>
        <Field label="Description">
          <textarea className="input" rows={2} value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} maxLength={255} />
        </Field>
        <Field label="Badge" optional>
          <span className="input">
            {f.badge ? <BadgeArt code={f.badge} size={24} /> : null}
            <input className="mono" value={f.badge} onChange={(e) => setF({ ...f, badge: e.target.value.trim() })} maxLength={64} placeholder="ADM" aria-label="Badge code" />
          </span>
        </Field>
        <div className="field">
          <span>Members</span>
          <div className="actions" style={{ gap: 6 }}>
            {members.map((p) => (
              <span key={p.id} className="tag">
                <span className="dot staff" />
                {p.username}
                <button type="button" aria-label={`Remove ${p.username}`} onClick={() => setMembers((m) => m.filter((x) => x.id !== p.id))} style={{ border: 0, background: 'none', padding: 0, color: 'var(--muted)', cursor: 'pointer', display: 'inline-flex' }}>
                  <Icon name="close" size={12} />
                </button>
              </span>
            ))}
            {adding ? (
              <MemberSearch
                onPick={(p) => {
                  setMembers((m) => (m.some((x) => x.id === p.id) ? m : [...m, p]));
                  setAdding(false);
                }}
                onCancel={() => setAdding(false)}
              />
            ) : (
              <Btn sm variant="quiet" onClick={() => setAdding(true)}>
                Add member
              </Btn>
            )}
          </div>
          <span className="hint">A player is in one team at a time; adding them here moves them.</span>
        </div>
        <label className="check">
          <input type="checkbox" checked={f.visible} onChange={(e) => setF({ ...f, visible: e.target.checked })} />
          Show on the public staff page
        </label>
      </div>
      <div className="card-f" style={{ justifyContent: team ? 'space-between' : 'flex-end' }}>
        {team ? (
          <Btn
            variant="danger"
            disabled={busy}
            onClick={async () => {
              if (!(await confirm({ title: `Delete ${team.name}?`, body: 'Its members stay staff; they just leave this team.', cta: 'Delete team', danger: true }))) return;
              if (await run(() => del(`/website/teams/${team.id}`))) onDone(null);
            }}
          >
            Delete team
          </Btn>
        ) : null}
        <Btn
          variant="primary"
          disabled={busy || f.name.trim() === ''}
          onClick={async () => {
            let id: number | null = team?.id ?? null;
            const ok = await run(async () => {
              if (team) return put(`/website/teams/${team.id}`, body);
              const r = await post<{ id: number; message: string }>('/website/teams', body);
              id = r.id;
              return r;
            });
            if (ok) onDone(id);
          }}
        >
          Save team
        </Btn>
      </div>
    </aside>
  );
}

function MemberSearch({ onPick, onCancel }: { onPick: (p: PlayerRef) => void; onCancel: () => void }) {
  const [q, setQ] = useState('');
  const term = useDebounced(q.trim(), 200);
  const [found, setFound] = useState<PlayerRef[]>([]);
  useEffect(() => {
    if (term.length < 2) {
      setFound([]);
      return;
    }
    let live = true;
    get<{ players: PlayerRef[] }>(`/search${qs({ q: term })}`).then((r) => live && setFound(r.players));
    return () => {
      live = false;
    };
  }, [term]);
  return (
    <span className="rel" style={{ display: 'inline-flex' }}>
      <input className="input" autoFocus value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Escape' && onCancel()} placeholder="Find a player" aria-label="Find a player" style={{ width: 180, height: 28 }} />
      {found.length ? (
        <div className="menu" style={{ top: '100%', left: 0, marginTop: 4, width: 220 }}>
          {found.map((p) => (
            <button key={p.id} type="button" onClick={() => onPick(p)}>
              {p.username} <span className="mono" style={{ marginLeft: 'auto' }}>#{p.id}</span>
            </button>
          ))}
        </div>
      ) : null}
    </span>
  );
}

/* ---------- Ads ---------- */

function Ads({ items, onChanged }: { items: Ad[]; onChanged: () => void }) {
  const { confirm } = useSession();
  const { busy, run } = useSave();
  return (
    <section className="card">
      <div className="card-h">
        <div>
          <h3>Ads</h3>
          <p className="sub">website_ads · banner images on the site</p>
        </div>
        <span className="actions">
          <Wp path="saved" />
          <ImagePick
            label="Add ad"
            onPick={async (image) => {
              if (await run(() => post('/website/ads', { image }))) onChanged();
            }}
          />
        </span>
      </div>
      {items.length === 0 ? <Empty title="No ads" body="Add an image to show it on the site." /> : null}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: 16, padding: items.length ? 20 : 0 }}>
        {items.map((a) => (
          <div key={a.id} style={{ border: '1px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
            <div style={{ height: 120, background: 'var(--tile)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <img src={a.url} alt="" style={{ maxWidth: '100%', maxHeight: '100%' }} />
            </div>
            <div style={{ padding: 12, display: 'flex', alignItems: 'center', gap: 8 }}>
              <span className="grow" style={{ minWidth: 0 }}>
                <span className="mono ell" style={{ display: 'block', color: 'var(--ink)' }}>
                  {a.image}
                </span>
                <span className="sub">Added {fmtDay(a.created)}</span>
              </span>
              <Pill tone="ok">Live</Pill>
              <Btn
                variant="quiet"
                sm
                iconOnly
                icon="trash"
                aria-label={`Delete ${a.image}`}
                disabled={busy}
                onClick={async () => {
                  if (!(await confirm({ title: 'Delete this ad?', body: 'The image file is removed too.', cta: 'Delete ad', danger: true }))) return;
                  if (await run(() => del(`/website/ads/${a.id}`))) onChanged();
                }}
              />
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

/* ---------- Home items ---------- */

function HomeItems({ items, categories, text, setText, onChanged }: { items: HomeItem[]; categories: { value: string; label: string }[]; text: string; setText: (v: string) => void; onChanged: () => void }) {
  const [sel, setSel] = useState<number | 'new' | null>(null);
  const cur = sel === 'new' ? null : items.find((h) => h.id === sel) ?? items[0] ?? null;
  return (
    <ListEditor
      list={
        <section className="card">
          <div className="card-h">
            <SearchInput value={text} onChange={setText} placeholder="Search home items" width={240} />
            <Btn sm onClick={() => setSel('new')}>
              Add item
            </Btn>
          </div>
          {items.length === 0 ? <Empty title="No home items" /> : null}
          {items.length ? (
            <div className="tw" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
              <table className="t">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Kind</th>
                    <th>Category</th>
                    <th className="r">Price</th>
                    <th className="r">Sold</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((h) => (
                    <tr key={h.id} className={`click ${sel !== 'new' && cur?.id === h.id ? 'sel' : ''}`} onClick={() => setSel(h.id)}>
                      <td style={{ height: 44 }}>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 10 }}>
                          <span style={{ width: 36, height: 36, borderRadius: 6, background: 'var(--tile)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden', flex: 'none' }}>
                            <img src={h.url} alt="" style={{ maxWidth: '100%', maxHeight: '100%' }} className="px" loading="lazy" />
                          </span>
                          <span style={{ fontWeight: 500 }}>{h.name}</span>
                        </span>
                      </td>
                      <td>{KINDS[h.type]}</td>
                      <td style={{ color: 'var(--muted)' }}>{h.category ?? '—'}</td>
                      <td className="r num">
                        {fmtNumber(h.price)} <span className="sub">{CURRENCIES[h.currency] ?? ''}</span>
                      </td>
                      <td className="r num">{fmtNumber(h.sold)}</td>
                      <td>
                        <Pill tone={h.enabled ? 'ok' : ''}>{h.enabled ? 'On sale' : 'Off'}</Pill>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </section>
      }
      editor={
        cur || sel === 'new' ? (
          <HomeItemEditor
            key={sel === 'new' ? 'new' : cur?.id}
            item={sel === 'new' ? null : cur}
            categories={categories}
            onDone={(id) => {
              setSel(id);
              onChanged();
            }}
          />
        ) : null
      }
    />
  );
}

function HomeItemEditor({ item, categories, onDone }: { item: HomeItem | null; categories: { value: string; label: string }[]; onDone: (id: number | null) => void }) {
  const { confirm } = useSession();
  const [f, setF] = useState({ type: item?.type ?? 's', name: item?.name ?? '', category_id: item?.category_id ?? null, price: String(item?.price ?? 0), currency: item?.currency ?? -1, enabled: item?.enabled ?? true, limit: item?.limit ? String(item.limit) : '' });
  const [image, setImage] = useState<string | null>(null);
  const { busy, run } = useSave();
  const body = { ...f, price: Number(f.price || 0), limit: f.limit ? Number(f.limit) : null, category_id: f.type === 's' ? f.category_id : null, image };

  return (
    <aside className="peek side-col" aria-label="Edit home item" style={{ flexBasis: 380 }}>
      <div className="card-h">
        <h3>{item ? item.name : 'New home item'}</h3>
        <Wp path="saved" />
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: 12, borderRadius: 10, background: 'var(--sunken)' }}>
          <span style={{ width: 64, height: 64, borderRadius: 8, background: 'var(--surface)', display: 'flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden', flex: 'none' }}>
            {image || item ? <img src={image ?? item!.url} alt="" style={{ maxWidth: '100%', maxHeight: '100%' }} className="px" /> : null}
          </span>
          <span className="grow sub">{image ? 'New image ready to save' : 'PNG, GIF, JPEG or WebP up to 2 MB'}</span>
          <ImagePick label={item || image ? 'Replace' : 'Choose image'} onPick={(url) => setImage(url)} />
        </div>
        <Field label="Name">
          <input className="input" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} maxLength={255} />
        </Field>
        <div className="grid2">
          <Field label="Kind">
            <select className="input" value={f.type} onChange={(e) => setF({ ...f, type: e.target.value as HomeItem['type'] })}>
              {Object.entries(KINDS).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </select>
          </Field>
          {f.type === 's' ? (
            <Field label="Category">
              <select className="input" value={f.category_id ?? ''} onChange={(e) => setF({ ...f, category_id: e.target.value ? Number(e.target.value) : null })}>
                <option value="">None</option>
                {categories.map((c) => (
                  <option key={c.value} value={c.value}>
                    {c.label}
                  </option>
                ))}
              </select>
            </Field>
          ) : null}
        </div>
        <div className="grid2">
          <Field label="Price">
            <span className="input">
              {f.currency === -1 ? <Coin /> : null}
              <input className="mono" inputMode="numeric" value={f.price} onChange={(e) => setF({ ...f, price: e.target.value.replace(/\D/g, '') })} aria-label="Price" />
            </span>
          </Field>
          <Field label="Paid in">
            <select className="input" value={f.currency} onChange={(e) => setF({ ...f, currency: Number(e.target.value) })}>
              {Object.entries(CURRENCIES).map(([k, l]) => (
                <option key={k} value={k}>
                  {l}
                </option>
              ))}
            </select>
          </Field>
        </div>
        <Field label="Stock" optional hint="Leave empty for no limit.">
          <input className="input mono" inputMode="numeric" value={f.limit} onChange={(e) => setF({ ...f, limit: e.target.value.replace(/\D/g, '') })} />
        </Field>
        <label className="check">
          <input type="checkbox" checked={f.enabled} onChange={(e) => setF({ ...f, enabled: e.target.checked })} />
          On sale in the Me page shop
        </label>
      </div>
      <div className="card-f" style={{ justifyContent: item ? 'space-between' : 'flex-end' }}>
        {item ? (
          <Btn
            variant="danger"
            disabled={busy}
            onClick={async () => {
              if (!(await confirm({ title: `Delete ${item.name}?`, body: 'Only items nobody owns can be deleted; otherwise take it off sale.', cta: 'Delete item', danger: true }))) return;
              if (await run(() => del(`/website/homeitems/${item.id}`))) onDone(null);
            }}
          >
            Delete
          </Btn>
        ) : null}
        <Btn
          variant="primary"
          disabled={busy || f.name.trim() === '' || (!item && !image)}
          onClick={async () => {
            let id: number | null = item?.id ?? null;
            const ok = await run(async () => {
              if (item) return put(`/website/homeitems/${item.id}`, body);
              const r = await post<{ id: number; message: string }>('/website/homeitems', body);
              id = r.id;
              return r;
            });
            if (ok) onDone(id);
          }}
        >
          Save item
        </Btn>
      </div>
    </aside>
  );
}

/* ---------- Tags ---------- */

function Tags({ items, onChanged }: { items: Tag[]; onChanged: () => void }) {
  const { confirm } = useSession();
  const [sel, setSel] = useState<number | 'new' | null>(null);
  const cur = sel === 'new' ? null : items.find((t) => t.id === sel) ?? items[0] ?? null;
  const [f, setF] = useState({ name: '', color: '#d62a86' });
  const { busy, run } = useSave();
  useEffect(() => setF({ name: cur?.name ?? '', color: cur?.color || '#d62a86' }), [cur?.id, cur?.name, cur?.color, sel]);

  return (
    <ListEditor
      list={
        <section className="card">
          <div className="card-h">
            <div>
              <h3>Tags</h3>
              <p className="sub">tags · labels for website articles</p>
            </div>
            <Btn sm onClick={() => setSel('new')}>
              Add tag
            </Btn>
          </div>
          {items.length === 0 ? <Empty title="No tags" /> : null}
          {items.map((t) => (
            <button key={t.id} type="button" className={`row ${sel !== 'new' && cur?.id === t.id ? 'on' : ''}`} onClick={() => setSel(t.id)}>
              <span className="swatch" style={{ background: t.color }} />
              <span className="grow" style={{ fontWeight: 500 }}>
                {t.name}
              </span>
              <span className="mono">{t.color}</span>
            </button>
          ))}
        </section>
      }
      editor={
        cur || sel === 'new' ? (
          <aside className="peek side-col" aria-label="Edit tag" style={{ flexBasis: 340 }}>
            <div className="card-h">
              <h3>{cur ? cur.name : 'New tag'}</h3>
              <Wp path="saved" />
            </div>
            <div className="card-b stack16">
              <Field label="Name">
                <input className="input" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} maxLength={255} />
              </Field>
              <Field label="Color">
                <span className="input">
                  <input type="color" value={f.color} onChange={(e) => setF({ ...f, color: e.target.value })} aria-label="Pick a color" style={{ width: 28, height: 24, padding: 0, border: 0, background: 'none', flex: 'none' }} />
                  <input className="mono" value={f.color} onChange={(e) => setF({ ...f, color: e.target.value })} aria-label="Hex color" />
                </span>
              </Field>
            </div>
            <div className="card-f" style={{ justifyContent: cur ? 'space-between' : 'flex-end' }}>
              {cur ? (
                <Btn
                  variant="danger"
                  disabled={busy}
                  onClick={async () => {
                    if (!(await confirm({ title: `Delete ${cur.name}?`, body: 'Articles lose this tag.', cta: 'Delete tag', danger: true }))) return;
                    if (await run(() => del(`/website/tags/${cur.id}`))) {
                      setSel(null);
                      onChanged();
                    }
                  }}
                >
                  Delete
                </Btn>
              ) : null}
              <Btn
                variant="primary"
                disabled={busy || f.name.trim() === '' || !/^#[0-9a-fA-F]{6}$/.test(f.color)}
                onClick={async () => {
                  if (await run(() => (cur ? put(`/website/tags/${cur.id}`, f) : post('/website/tags', f)))) {
                    if (!cur) setSel(null);
                    onChanged();
                  }
                }}
              >
                Save tag
              </Btn>
            </div>
          </aside>
        ) : null
      }
    />
  );
}

/* ---------- Beta codes ---------- */

function BetaCodes({ items, text, setText, onChanged }: { items: BetaCode[]; text: string; setText: (v: string) => void; onChanged: () => void }) {
  const { confirm } = useSession();
  const [count, setCount] = useState('5');
  const [code, setCode] = useState('');
  const { busy, run } = useSave();
  const unused = items.filter((c) => !c.player).length;

  return (
    <ListEditor
      list={
        <section className="card">
          <div className="card-h">
            <SearchInput value={text} onChange={setText} placeholder="Search codes" width={220} />
            <span className="sub">{unused} unused</span>
          </div>
          {items.length === 0 ? <Empty title="No beta codes" /> : null}
          {items.length ? (
            <div className="tw" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
              <table className="t">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Claimed by</th>
                    <th>Created</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {items.map((c) => (
                    <tr key={c.id}>
                      <td className="mono" style={{ color: 'var(--ink)' }}>
                        {c.code}
                      </td>
                      <td>{c.player ? <PlayerChip player={c.player} /> : <Pill tone="ok">Unused</Pill>}</td>
                      <td style={{ color: 'var(--muted)' }}>{fmtDay(c.created)}</td>
                      <td className="r">
                        <Btn variant="quiet" sm iconOnly icon="copy" aria-label={`Copy ${c.code}`} onClick={() => navigator.clipboard?.writeText(c.code)} />
                        <Btn
                          variant="quiet"
                          sm
                          iconOnly
                          icon="trash"
                          aria-label={`Delete ${c.code}`}
                          disabled={busy}
                          onClick={async () => {
                            if (!(await confirm({ title: `Delete ${c.code}?`, body: c.player ? 'It was already used; deleting it only removes the record.' : 'Nobody can register with it after this.', cta: 'Delete code', danger: true }))) return;
                            if (await run(() => del(`/website/betacodes/${c.id}`))) onChanged();
                          }}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </section>
      }
      editor={
        <aside className="peek side-col" aria-label="New beta codes" style={{ flexBasis: 320 }}>
          <div className="card-h">
            <h3>New beta codes</h3>
            <Wp path="saved" />
          </div>
          <div className="card-b stack16">
            <Field label="How many" hint="Random 10-character codes.">
              <input className="input mono" inputMode="numeric" value={count} onChange={(e) => setCount(e.target.value.replace(/\D/g, '').slice(0, 3))} />
            </Field>
            <Btn variant="primary" disabled={busy || !Number(count) || Number(count) > 100} onClick={async () => { if (await run(() => post('/website/betacodes', { count: Number(count) }))) onChanged(); }}>
              Make {count || 0} codes
            </Btn>
            <Field label="Or one code of your own">
              <input className="input mono" value={code} onChange={(e) => setCode(e.target.value.trim())} maxLength={64} />
            </Field>
            <Btn disabled={busy || code === ''} onClick={async () => { if (await run(() => post('/website/betacodes', { code }))) { setCode(''); onChanged(); } }}>
              Add code
            </Btn>
            <p className="sub" style={{ fontSize: 12 }}>
              Codes are only asked for while “Require a beta code to register” is on in Website settings.
            </p>
          </div>
        </aside>
      }
    />
  );
}
