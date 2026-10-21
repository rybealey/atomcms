import React, { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { get, post, put, qs } from '@hk/lib/api';
import { fmtNumber } from '@hk/lib/format';
import { Btn, Coin, Dialog, Empty, Field, Icon, Loadable, Note, Pill, Wp, useDebounced } from '@hk/ui';
import { useSave } from './shared';

interface Page { id: number; parent: number; caption: string; icon: number; min_rank: number; order: number; layout: string; visible: boolean; enabled: boolean; items: number }
interface Furni { id: number; item_name: string; public_name: string; type: string; interaction: string; modes: number; vending_ids: string; trade: boolean; stack: boolean; rare: boolean; overrides: number }
interface Item { id: number; item_id: string; name: string; credits: number; pixels: number; diamonds: number; amount: number; limited_stack: number; limited_sells: number; active: boolean; sold: number; furni: Furni | null }
interface PageData { page: Page & { parent_caption: string | null }; icons_path: string; items: Item[] }
interface Tree { pages: Page[]; icons_path: string }

/** Interaction types that carry a roleplay function in this hotel, plus the furni's own. */
const FUNCTIONS = ['default', 'vendingmachine', 'gym_stamina', 'corp_gate', 'purchasable_clothing', 'pressure_pad', 'pressure_tile', 'gate', 'onewaygate', 'teleport', 'bed', 'effect', 'dimmer', 'roller', 'badge', 'trophy', 'exchange'];
const FUNCTION_HELP: Record<string, string> = {
  default: 'No behaviour beyond sitting, standing or stacking.',
  vendingmachine: 'Hands out the items whose ids are in Vending IDs.',
  gym_stamina: 'Trains Stamina while a member stands on it.',
  corp_gate: 'Only opens for employees of the room’s corporation.',
  purchasable_clothing: 'Opens the clothing store for the clothes it sells.',
  pressure_pad: 'Switches when someone steps on it.',
  exchange: 'Turns into coins when redeemed.',
};

export default function Catalog() {
  const tree = useFetch<Tree>('/catalog');
  const [params, setParams] = useSearchParams();
  const pageId = Number(params.get('page') ?? 0) || null;
  const page = useFetch<PageData>(pageId ? `/catalog/pages/${pageId}` : null);
  const [sel, setSel] = useState<number | null>(null);
  const [editing, setEditing] = useState<'item' | 'page' | 'newpage'>('item');
  const [adding, setAdding] = useState(false);
  const { busy, run } = useSave();

  useEffect(() => {
    if (!pageId && tree.data?.pages.length) {
      const first = tree.data.pages.find((p) => p.parent === -1);
      if (first) setParams({ page: String(first.id) }, { replace: true });
    }
  }, [tree.data, pageId, setParams]);
  useEffect(() => {
    setSel(null);
    setEditing('item');
  }, [pageId]);

  const pd = page.data;
  const item = pd?.items.find((i) => i.id === sel) ?? pd?.items[0] ?? null;
  const crumbsParent = pd?.page.parent_caption;
  useCrumbs([{ label: 'Content', to: '/content/badges' }, { label: 'Catalog', to: '/content/catalog' }, { label: pd ? `${crumbsParent ? crumbsParent + ' · ' : ''}${pd.page.caption}` : 'Catalog' }]);

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Catalog and furni</h1>
          <p className="sub">catalog_pages, catalog_items, furniture and rp_item_function in one place</p>
        </div>
        <div className="actions">
          <Btn variant="quiet" disabled={busy} onClick={() => run(() => post('/catalog/reload', {}))}>
            <Icon name="refresh" /> Reload catalog
          </Btn>
          <Btn onClick={() => setEditing('newpage')}>New page</Btn>
          <Btn variant="primary" disabled={!pageId} onClick={() => setAdding(true)}>
            Add item
          </Btn>
        </div>
      </div>
      <div className="split">
        <section className="card" style={{ flex: '1 1 240px', maxHeight: '80vh', overflowY: 'auto' }} aria-label="Catalog pages">
          <div className="card-h">
            <h3>Pages</h3>
            <span className="sub">Drag to reorder</span>
          </div>
          <Loadable state={tree} rows={10}>{(t) => <PageTree pages={t.pages} current={pageId} onPick={(id) => setParams({ page: String(id) })} onReordered={tree.reload} />}</Loadable>
        </section>
        <section className="card" style={{ flex: '999 1 460px', minWidth: 0 }} aria-label="Items on this page">
          {pd ? (
            <div className="card-h">
              <div>
                <h3>{pd.page.caption}</h3>
                <p className="sub">
                  Page <span className="mono">{pd.page.id}</span>
                  {pd.page.parent_caption ? ` · under ${pd.page.parent_caption}` : ' · top level'} · rank {pd.page.min_rank} and up
                </p>
              </div>
              <div className="actions">
                <Pill tone={pd.page.visible && pd.page.enabled ? 'ok' : ''}>{!pd.page.enabled ? 'Disabled' : pd.page.visible ? 'Visible' : 'Hidden'}</Pill>
                <Btn sm onClick={() => setEditing('page')}>
                  Edit page
                </Btn>
              </div>
            </div>
          ) : null}
          {!pageId ? (
            <Empty title="Pick a page" />
          ) : (
            <Loadable state={page} rows={6} empty={(x) => (x.items.length === 0 ? <Empty title="Nothing on sale on this page" body="Add an item, or use it as a folder for the pages under it." /> : false)}>
              {(x) => (
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th>RP function</th>
                        <th className="r">Price</th>
                        <th className="r">Sold 7 days</th>
                      </tr>
                    </thead>
                    <tbody>
                      {x.items.map((it) => (
                        <tr key={it.id} className={`click ${editing === 'item' && item?.id === it.id ? 'sel' : ''}`} onClick={() => { setSel(it.id); setEditing('item'); }}>
                          <td style={{ height: 48 }}>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 10 }}>
                              <FurniIcon path={x.icons_path} name={it.furni?.item_name} />
                              <span>
                                <span style={{ fontWeight: 500, display: 'block' }}>{it.name}</span>
                                {!it.active ? <span className="sub">Not on sale</span> : null}
                              </span>
                            </span>
                          </td>
                          <td>{it.furni && it.furni.interaction !== 'default' ? <span className="mono" style={{ color: 'var(--ink)' }}>{it.furni.interaction}</span> : null}</td>
                          <td className="r">
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                              <span className="num">{fmtNumber(it.credits)}</span>
                              <Coin />
                              {it.diamonds > 0 ? (
                                <>
                                  <span className="num">{fmtNumber(it.diamonds)}</span>
                                  <Coin type="diamonds" />
                                </>
                              ) : null}
                            </span>
                          </td>
                          <td className="r num">{fmtNumber(it.sold)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Loadable>
          )}
        </section>
        {editing === 'item' && item && pd ? <ItemEditor key={item.id} item={item} iconsPath={pd.icons_path} onSaved={page.reload} /> : null}
        {editing === 'page' && pd ? <PageEditor key={`p${pd.page.id}`} page={pd.page} pages={tree.data?.pages ?? []} onSaved={() => { tree.reload(); page.reload(); setEditing('item'); }} onCancel={() => setEditing('item')} /> : null}
        {editing === 'newpage' ? (
          <PageEditor
            page={null}
            parentId={pd?.page.parent ?? -1}
            pages={tree.data?.pages ?? []}
            onSaved={(id) => {
              tree.reload();
              setEditing('item');
              if (id) setParams({ page: String(id) });
            }}
            onCancel={() => setEditing('item')}
          />
        ) : null}
      </div>
      {adding && pageId ? <AddItem pageId={pageId} onClose={() => setAdding(false)} onAdded={page.reload} /> : null}
    </main>
  );
}

function FurniIcon({ path, name, size = 36 }: { path: string; name?: string; size?: number }) {
  const [broken, setBroken] = useState(false);
  return (
    <span style={{ display: 'inline-flex', width: size, height: size, borderRadius: 6, background: 'var(--tile)', alignItems: 'center', justifyContent: 'center', flex: 'none', overflow: 'hidden' }}>
      {name && path && !broken ? <img src={`${path}/${name}_icon.png`} alt="" className="px" onError={() => setBroken(true)} style={{ maxWidth: '100%', maxHeight: '100%' }} /> : <Icon name="sofa" />}
    </span>
  );
}

function PageTree({ pages, current, onPick, onReordered }: { pages: Page[]; current: number | null; onPick: (id: number) => void; onReordered: () => void }) {
  const children = useMemo(() => {
    const map = new Map<number, Page[]>();
    for (const p of pages) map.set(p.parent, [...(map.get(p.parent) ?? []), p]);
    return map;
  }, [pages]);
  const ancestors = useMemo(() => {
    const set = new Set<number>();
    let at = pages.find((p) => p.id === current);
    while (at && at.parent > 0) {
      set.add(at.parent);
      at = pages.find((p) => p.id === at!.parent);
    }
    return set;
  }, [pages, current]);
  const [open, setOpen] = useState<Set<number>>(new Set());
  const [drag, setDrag] = useState<Page | null>(null);
  const { run } = useSave();

  const drop = async (target: Page) => {
    if (!drag || drag.id === target.id || drag.parent !== target.parent) return;
    const siblings = (children.get(target.parent) ?? []).filter((p) => p.id !== drag.id);
    const at = siblings.findIndex((p) => p.id === target.id);
    siblings.splice(at, 0, drag);
    setDrag(null);
    if (await run(() => post('/catalog/pages/reorder', { parent: target.parent, ids: siblings.map((p) => p.id) }))) onReordered();
  };

  const render = (parent: number, depth: number): React.ReactNode =>
    (children.get(parent) ?? []).map((p) => {
      const kids = children.get(p.id)?.length ?? 0;
      const expanded = open.has(p.id) || ancestors.has(p.id) || p.id === current;
      return (
        <React.Fragment key={p.id}>
          <div
            draggable
            onDragStart={() => setDrag(p)}
            onDragOver={(e) => drag && drag.parent === p.parent && e.preventDefault()}
            onDrop={() => drop(p)}
            style={{ display: 'flex', alignItems: 'center' }}
          >
            {kids ? (
              <button
                type="button"
                aria-label={expanded ? `Collapse ${p.caption}` : `Expand ${p.caption}`}
                aria-expanded={expanded}
                onClick={() =>
                  setOpen((o) => {
                    const n = new Set(o);
                    if (n.has(p.id)) n.delete(p.id);
                    else n.add(p.id);
                    return n;
                  })
                }
                style={{ marginLeft: depth * 14, width: 22, padding: 0, justifyContent: 'center', flex: 'none' }}
              >
                <Icon name={expanded ? 'chevron-down' : 'chevron-right'} />
              </button>
            ) : (
              <span style={{ width: 22 + depth * 14, flex: 'none' }} />
            )}
            <button
              type="button"
              className={p.id === current ? 'on' : ''}
              onClick={() => {
                onPick(p.id);
                if (kids) setOpen((o) => new Set(o).add(p.id));
              }}
              aria-current={p.id === current ? 'page' : undefined}
              style={{ flex: 1, minWidth: 0, color: p.id === current ? undefined : depth === 0 ? 'var(--ink)' : undefined, fontWeight: p.id === current || depth === 0 ? 500 : 400 }}
            >
              <span className="grow ell">{p.caption}</span>
              {!p.visible || !p.enabled ? <span className="pill" style={{ height: 18, fontSize: 11 }}>{!p.enabled ? 'Off' : 'Hidden'}</span> : null}
              <span className="mono" style={{ fontSize: 11 }}>
                {p.items || ''}
              </span>
            </button>
          </div>
          {kids && expanded ? render(p.id, depth + 1) : null}
        </React.Fragment>
      );
    });

  return <div className="tree">{render(-1, 0)}</div>;
}

function ItemEditor({ item, iconsPath, onSaved }: { item: Item; iconsPath: string; onSaved: () => void }) {
  const [f, setF] = useState({
    name: item.name,
    credits: String(item.credits),
    pixels: String(item.pixels),
    diamonds: String(item.diamonds),
    amount: String(item.amount),
    limited_stack: String(item.limited_stack),
    active: item.active,
  });
  const [fu, setFu] = useState(item.furni ? { interaction: item.furni.interaction, modes: String(item.furni.modes), vending_ids: item.furni.vending_ids === '0' ? '' : item.furni.vending_ids, trade: item.furni.trade, stack: item.furni.stack } : null);
  const { busy, run } = useSave();
  const n = (v: string) => (v === '' ? 0 : Number(v));
  const num = (v: string) => v.replace(/\D/g, '');
  const options = fu && !FUNCTIONS.includes(fu.interaction) ? [fu.interaction, ...FUNCTIONS] : FUNCTIONS;

  return (
    <aside className="peek side-col" aria-label="Edit item" style={{ flexBasis: 360 }}>
      <div className="card-h">
        <div>
          <h3>{item.name}</h3>
          <p className="sub">
            {item.furni ? (
              <>
                Furni <span className="mono">{item.furni.item_name}</span> ·{' '}
              </>
            ) : null}
            catalog item <span className="mono">{item.id}</span>
          </p>
        </div>
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <div style={{ display: 'flex', gap: 16, alignItems: 'flex-end', padding: 12, borderRadius: 10, background: 'var(--sunken)' }}>
          <FurniIcon path={iconsPath} name={item.furni?.item_name} size={48} />
          <span className="sub" style={{ fontSize: 12 }}>
            {item.furni ? `${item.furni.public_name || item.furni.item_name} · definition ${item.furni.id}` : `Bundle or offer: ${item.item_id}`}
          </span>
        </div>
        <Field label="Name in the catalog">
          <input className="input" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} maxLength={100} />
        </Field>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gap: 12 }}>
          <Field label="Coins">
            <input className="input mono" inputMode="numeric" value={f.credits} onChange={(e) => setF({ ...f, credits: num(e.target.value) })} />
          </Field>
          <Field label="Duckets">
            <input className="input mono" inputMode="numeric" value={f.pixels} onChange={(e) => setF({ ...f, pixels: num(e.target.value) })} />
          </Field>
          <Field label="Diamonds">
            <input className="input mono" inputMode="numeric" value={f.diamonds} onChange={(e) => setF({ ...f, diamonds: num(e.target.value) })} />
          </Field>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 12 }}>
          <Field label="Amount per purchase">
            <input className="input mono" inputMode="numeric" value={f.amount} onChange={(e) => setF({ ...f, amount: num(e.target.value) })} />
          </Field>
          <Field label="Limited edition of" hint={item.limited_sells ? `${item.limited_sells} sold` : '0 means unlimited'}>
            <input className="input mono" inputMode="numeric" value={f.limited_stack} onChange={(e) => setF({ ...f, limited_stack: num(e.target.value) })} />
          </Field>
        </div>
        {fu ? (
          <fieldset style={{ border: '1px solid var(--line)', borderRadius: 10, padding: 12, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <legend style={{ fontSize: 13, fontWeight: 500, padding: '0 6px' }}>Roleplay function</legend>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gap: 10 }}>
              <label className="field" style={{ gap: 6 }}>
                <span className="sub">Function</span>
                <select className="input" value={fu.interaction} onChange={(e) => setFu({ ...fu, interaction: e.target.value })}>
                  {options.map((o) => (
                    <option key={o} value={o}>
                      {o}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field" style={{ gap: 6 }}>
                <span className="sub">States</span>
                <input className="input mono" inputMode="numeric" value={fu.modes} onChange={(e) => setFu({ ...fu, modes: num(e.target.value) })} />
              </label>
              <label className="field" style={{ gap: 6 }}>
                <span className="sub">Vending IDs</span>
                <input className="input mono" value={fu.vending_ids} onChange={(e) => setFu({ ...fu, vending_ids: e.target.value.replace(/[^\d,]/g, '') })} placeholder="None" />
              </label>
            </div>
            <span className="sub" style={{ fontSize: 12 }}>
              {FUNCTION_HELP[fu.interaction] ?? 'Set on the furni definition, so it applies to every copy in the hotel.'}
              {item.furni && item.furni.overrides > 0 ? ` ${item.furni.overrides} placed ${item.furni.overrides === 1 ? 'copy has' : 'copies have'} their own override from the in-game Function tool.` : ''}
            </span>
          </fieldset>
        ) : null}
        <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', fontSize: 13 }}>
          {fu ? (
            <>
              <label className="check">
                <input type="checkbox" checked={fu.trade} onChange={(e) => setFu({ ...fu, trade: e.target.checked })} />
                Can trade
              </label>
              <label className="check">
                <input type="checkbox" checked={fu.stack} onChange={(e) => setFu({ ...fu, stack: e.target.checked })} />
                Stacks in inventory
              </label>
            </>
          ) : null}
          <label className="check">
            <input type="checkbox" checked={f.active} onChange={(e) => setF({ ...f, active: e.target.checked })} />
            On sale
          </label>
        </div>
        {fu ? (
          <p className="sub" style={{ fontSize: 12 }}>
            Names, walking and sitting are set with the in-game Function tool: the client keeps its own copy of those.
          </p>
        ) : null}
      </div>
      <div className="card-f" style={{ alignItems: 'center' }}>
        <Wp path="saved" label="Saved, then reloaded" />
        <Btn
          variant="primary"
          disabled={busy || f.name.trim() === '' || n(f.amount) < 1}
          onClick={async () => {
            const ok = await run(() =>
              put(`/catalog/items/${item.id}`, {
                name: f.name,
                credits: n(f.credits),
                pixels: n(f.pixels),
                diamonds: n(f.diamonds),
                amount: n(f.amount),
                limited_stack: n(f.limited_stack),
                active: f.active,
                furni: fu ? { interaction: fu.interaction, modes: n(fu.modes), vending_ids: fu.vending_ids, trade: fu.trade, stack: fu.stack } : undefined,
              }),
            );
            if (ok) onSaved();
          }}
        >
          Save item
        </Btn>
      </div>
    </aside>
  );
}

function PageEditor({ page, parentId, pages, onSaved, onCancel }: { page: (Page & { parent_caption?: string | null }) | null; parentId?: number; pages: Page[]; onSaved: (id?: number) => void; onCancel: () => void }) {
  const { me } = useSession();
  const [f, setF] = useState({
    caption: page?.caption ?? '',
    parent: page?.parent ?? parentId ?? -1,
    min_rank: page?.min_rank ?? 1,
    visible: page?.visible ?? false,
    enabled: page?.enabled ?? true,
  });
  const { busy, run } = useSave();
  const parents = pages.filter((p) => p.id !== page?.id && (p.parent === -1 || pages.find((x) => x.id === p.parent)?.parent === -1));

  return (
    <aside className="peek side-col" aria-label={page ? 'Edit page' : 'New page'} style={{ flexBasis: 360 }}>
      <div className="card-h">
        <h3>{page ? `Edit ${page.caption}` : 'New page'}</h3>
        <Btn variant="quiet" sm iconOnly icon="close" aria-label="Close" onClick={onCancel} />
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <Field label="Caption" hint="Captions repeat across the tree; the page id is what names a page.">
          <input className="input" value={f.caption} onChange={(e) => setF({ ...f, caption: e.target.value })} maxLength={35} />
        </Field>
        <Field label="Under">
          <select className="input" value={f.parent} onChange={(e) => setF({ ...f, parent: Number(e.target.value) })}>
            <option value={-1}>Top level</option>
            {parents.map((p) => (
              <option key={p.id} value={p.id}>
                {p.parent === -1 ? '' : '— '}
                {p.caption} (#{p.id})
              </option>
            ))}
          </select>
        </Field>
        <Field label="Lowest rank that sees it">
          <select className="input" value={f.min_rank} onChange={(e) => setF({ ...f, min_rank: Number(e.target.value) })}>
            {me.ranks.map((r) => (
              <option key={r.id} value={r.id}>
                {r.id} · {r.name}
              </option>
            ))}
          </select>
        </Field>
        <label className="check">
          <input type="checkbox" checked={f.visible} onChange={(e) => setF({ ...f, visible: e.target.checked })} />
          Shown in the catalog tree
        </label>
        <label className="check">
          <input type="checkbox" checked={f.enabled} onChange={(e) => setF({ ...f, enabled: e.target.checked })} />
          Enabled
        </label>
        {!page ? (
          <Note icon="info" title="New pages start hidden">
            Fill it with items, then tick “Shown in the catalog tree” when it is ready.
          </Note>
        ) : null}
      </div>
      <div className="card-f" style={{ alignItems: 'center' }}>
        <Wp path="saved" label="Saved, then reloaded" />
        <Btn onClick={onCancel}>Cancel</Btn>
        <Btn
          variant="primary"
          disabled={busy || f.caption.trim() === ''}
          onClick={async () => {
            let created: number | undefined;
            const ok = await run(async () => {
              const r = page ? await put<{ message: string }>(`/catalog/pages/${page.id}`, f) : await post<{ message: string; id: number }>('/catalog/pages', f);
              created = 'id' in r ? (r as { id: number }).id : undefined;
              return r;
            });
            if (ok) onSaved(created);
          }}
        >
          {page ? 'Save page' : 'Create page'}
        </Btn>
      </div>
    </aside>
  );
}

function AddItem({ pageId, onClose, onAdded }: { pageId: number; onClose: () => void; onAdded: () => void }) {
  const [q, setQ] = useState('');
  const term = useDebounced(q.trim(), 250);
  const [found, setFound] = useState<{ id: number; item_name: string; public_name: string; interaction: string }[] | null>(null);
  const [pick, setPick] = useState<{ id: number; item_name: string; public_name: string } | null>(null);
  const [credits, setCredits] = useState('0');
  const [diamonds, setDiamonds] = useState('0');
  const [name, setName] = useState('');
  const { busy, run } = useSave();

  useEffect(() => {
    if (term.length < 2) {
      setFound(null);
      return;
    }
    let live = true;
    get<{ items: { id: number; item_name: string; public_name: string; interaction: string }[] }>(`/catalog/furni${qs({ q: term })}`).then((r) => live && setFound(r.items)).catch(() => live && setFound([]));
    return () => {
      live = false;
    };
  }, [term]);

  return (
    <Dialog
      title="Add item"
      wp="saved"
      onClose={onClose}
      footer={
        <>
          <Btn onClick={onClose}>Cancel</Btn>
          <Btn
            variant="primary"
            disabled={!pick || busy}
            onClick={async () => {
              const ok = await run(() => post('/catalog/items', { page_id: pageId, furni_id: pick!.id, name, credits: Number(credits || 0), diamonds: Number(diamonds || 0) }));
              if (ok) {
                onAdded();
                onClose();
              }
            }}
          >
            Add to page
          </Btn>
        </>
      }
    >
      <Field label="Furni">
        <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Class name, name or definition id" autoFocus />
      </Field>
      {found === null ? null : found.length === 0 ? (
        <p className="sub">Nothing by that name.</p>
      ) : (
        <div style={{ maxHeight: 200, overflowY: 'auto', border: '1px solid var(--line)', borderRadius: 10 }}>
          {found.map((f) => (
            <button key={f.id} type="button" className={`row ${pick?.id === f.id ? 'on' : ''}`} onClick={() => { setPick(f); setName(f.public_name || f.item_name); }}>
              <span className="grow">
                <span style={{ display: 'block', fontWeight: 500 }}>{f.public_name || f.item_name}</span>
                <span className="mono">{f.item_name}</span>
              </span>
              <span className="mono">#{f.id}</span>
            </button>
          ))}
        </div>
      )}
      {pick ? (
        <>
          <Field label="Name in the catalog">
            <input className="input" value={name} onChange={(e) => setName(e.target.value)} maxLength={100} />
          </Field>
          <div className="grid2">
            <Field label="Coins">
              <input className="input mono" inputMode="numeric" value={credits} onChange={(e) => setCredits(e.target.value.replace(/\D/g, ''))} />
            </Field>
            <Field label="Diamonds">
              <input className="input mono" inputMode="numeric" value={diamonds} onChange={(e) => setDiamonds(e.target.value.replace(/\D/g, ''))} />
            </Field>
          </div>
        </>
      ) : null}
      <p className="sub" style={{ fontSize: 12 }}>
        Saved to catalog_items, then the hotel reloads the catalog.
      </p>
    </Dialog>
  );
}

