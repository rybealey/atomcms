import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, put, qs } from '@hk/lib/api';
import { badgeUrl } from '@hk/lib/avatar';
import { fmtDateTime, fmtNumber } from '@hk/lib/format';
import { Btn, Card, Empty, Field, Kv, Loadable, Note, Pager, Pill, PlayerChip, PlayerRef, SearchInput, Tabs, useDebounced, Wp, WritePath } from '@hk/ui';
import { capital } from '../logs/StaffAudit';
import { BadgeArt, ImagePick, ListEditor, PixelScales, useSave } from './shared';

interface Badge { code: string; name: string; description: string; holders: number }
interface Drawing { id: number; code: string; image: string; name: string; description: string; player: PlayerRef | null; at: number; earlier: { approved: number; total: number } }
interface Given { id: number; at: number; code: string; summary: string; staff: PlayerRef | null; player: PlayerRef | null; reason: string | null; path: WritePath }
interface Data {
  counts: { all: number; queue: number };
  items?: Badge[];
  total?: number;
  page?: number;
  last_page?: number;
  texts_file?: { path: string; writable: boolean };
  queue?: Drawing[];
  given?: Given[];
}

type Tab = 'all' | 'queue' | 'given';

export default function Badges() {
  useCrumbs([{ label: 'Content', to: '/content/badges' }, { label: 'Badges' }]);
  const { refreshPulse } = useSession();
  const [params, setParams] = useSearchParams();
  const tab = (['all', 'queue', 'given'].includes(params.get('tab') ?? '') ? params.get('tab') : 'all') as Tab;
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const [page, setPage] = useState(1);
  const state = useFetch<Data>(`/badges${qs({ tab, q: tab === 'all' ? q : '', page: tab === 'all' ? page : null })}`);
  const [sel, setSel] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const d = state.data;

  useEffect(() => setPage(1), [q]);
  useEffect(() => {
    setSel(null);
    setCreating(false);
  }, [tab]);

  const setTab = (t: string) => setParams(t === 'all' ? {} : { tab: t }, { replace: true });
  const badge = d?.items?.find((b) => b.code === sel) ?? (creating ? null : d?.items?.[0]) ?? null;
  const drawing = d?.queue?.find((x) => String(x.id) === sel) ?? d?.queue?.[0] ?? null;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Badges</h1>
          <p className="sub">badge_definitions and player drawings from website_drawbadges</p>
        </div>
        <div className="actions">
          <Btn
            wp="saved"
            onClick={() => {
              setTab('all');
              setCreating(true);
              setSel(null);
            }}
          >
            New badge
          </Btn>
        </div>
      </div>
      <Tabs
        label="Badge views"
        active={tab}
        onChange={setTab}
        tabs={[
          { id: 'all', label: 'All badges', n: d?.counts.all ?? null },
          { id: 'queue', label: 'Approval queue', n: d?.counts.queue ?? null },
          { id: 'given', label: 'Recently given' },
        ]}
      />

      {tab === 'all' ? (
        <ListEditor
          list={
            <Card
              title={undefined}
              action={undefined}
              bodyless
            >
              <div className="card-h">
                <SearchInput value={text} onChange={setText} placeholder="Search codes or names" width={280} />
                <span className="sub">{d?.total !== undefined ? `${fmtNumber(d.total)} badges` : ''}</span>
              </div>
              <Loadable state={state} rows={10} empty={(x) => (!x.items?.length ? <Empty title="No badges match" /> : false)}>
                {(x) => (
                  <>
                    <div className="tw">
                      <table className="t">
                        <thead>
                          <tr>
                            <th>Badge</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th className="r">Players with it</th>
                          </tr>
                        </thead>
                        <tbody>
                          {x.items!.map((b) => (
                            <tr
                              key={b.code}
                              className={`click ${!creating && badge?.code === b.code ? 'sel' : ''}`}
                              onClick={() => {
                                setCreating(false);
                                setSel(b.code);
                              }}
                            >
                              <td style={{ height: 52 }}>
                                <span style={{ display: 'inline-flex', width: 44, height: 44, borderRadius: 6, background: 'var(--tile)', alignItems: 'center', justifyContent: 'center' }}>
                                  <BadgeArt code={b.code} />
                                </span>
                              </td>
                              <td className="mono" style={{ color: 'var(--ink)' }}>
                                {b.code}
                              </td>
                              <td className="wrap">{b.name || <span className="sub">No name in the client texts</span>}</td>
                              <td className="r num">{fmtNumber(b.holders)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <Pager page={x.page ?? 1} lastPage={x.last_page ?? 1} total={x.total} label="badges" onPage={setPage} />
                  </>
                )}
              </Loadable>
            </Card>
          }
          editor={d ? <BadgeEditor key={creating ? 'new' : badge?.code ?? 'none'} badge={creating ? null : badge} textsFile={d.texts_file} onSaved={(code) => { setCreating(false); setSel(code); state.reload(); }} /> : null}
        />
      ) : null}

      {tab === 'queue' ? (
        <ListEditor
          list={
            <section className="card">
              <Loadable state={state} rows={4} empty={(x) => (!x.queue?.length ? <Empty title="No drawings waiting" body="New drawings from the website's badge creator show up here." /> : false)}>
                {(x) => (
                  <>
                    <div className="tw">
                      <table className="t">
                        <thead>
                          <tr>
                            <th>Drawing</th>
                            <th>Proposed name</th>
                            <th>Drawn by</th>
                            <th>Description</th>
                            <th>Submitted</th>
                            <th>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          {x.queue!.map((q) => (
                            <tr key={q.id} className={`click ${drawing?.id === q.id ? 'sel' : ''}`} onClick={() => setSel(String(q.id))}>
                              <td style={{ height: 56 }}>
                                <span style={{ display: 'inline-flex', width: 40, height: 40, borderRadius: 6, background: 'var(--tile)', alignItems: 'center', justifyContent: 'center' }}>
                                  <img src={q.image} alt="" width={40} height={40} className="px" />
                                </span>
                              </td>
                              <td style={{ fontWeight: 500 }}>{q.name || <span className="sub">Untitled</span>}</td>
                              <td>
                                <PlayerChip player={q.player} />
                              </td>
                              <td className="wrap" style={{ color: 'var(--muted)', maxWidth: 260 }}>
                                {q.description || '—'}
                              </td>
                              <td className="mono">{fmtDateTime(q.at)}</td>
                              <td>
                                <Pill tone="warn">Waiting</Pill>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <div className="row" style={{ borderTop: '1px solid var(--line)' }}>
                      <span className="sub">Drawings are bought on the website's badge creator; approving publishes the badge and gives it to the player.</span>
                    </div>
                  </>
                )}
              </Loadable>
            </section>
          }
          editor={
            drawing ? (
              <Review
                key={drawing.id}
                drawing={drawing}
                onDone={() => {
                  setSel(null);
                  state.reload();
                  refreshPulse();
                }}
              />
            ) : null
          }
        />
      ) : null}

      {tab === 'given' ? (
        <section className="card">
          <Loadable state={state} rows={6} empty={(x) => (!x.given?.length ? <Empty title="No badges given from the panel yet" body="Badges given from Player 360 and approved drawings are listed here." /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>Badge</th>
                      <th>What happened</th>
                      <th>Player</th>
                      <th>Staff</th>
                      <th>Reason</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {x.given!.map((g) => (
                      <tr key={g.id}>
                        <td className="mono">{fmtDateTime(g.at)}</td>
                        <td>
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                            {g.code ? <BadgeArt code={g.code} size={32} /> : null}
                            <span className="mono">{g.code}</span>
                          </span>
                        </td>
                        <td>{capital(g.summary)}</td>
                        <td>{g.player ? <PlayerChip player={g.player} /> : '—'}</td>
                        <td>{g.staff ? <PlayerChip player={g.staff} /> : 'System'}</td>
                        <td className="wrap sub">{g.reason ?? ''}</td>
                        <td>
                          <Wp path={g.path} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
      ) : null}
    </main>
  );
}

function BadgeEditor({ badge, textsFile, onSaved }: { badge: Badge | null; textsFile?: { path: string; writable: boolean }; onSaved: (code: string) => void }) {
  const { me } = useSession();
  const [code, setCode] = useState(badge?.code ?? '');
  const [name, setName] = useState(badge?.name ?? '');
  const [description, setDescription] = useState(badge?.description ?? '');
  const [image, setImage] = useState<string | null>(null);
  const { busy, run } = useSave();
  const creating = badge === null;
  const validCode = /^[A-Za-z0-9_-]{1,64}$/.test(code);
  const src = image ?? (validCode ? badgeUrl(me.config.badges_path, code) : '');

  return (
    <aside className="peek side-col" aria-label="Edit badge" style={{ flexBasis: 380 }}>
      <div className="card-h">
        <h3>{creating ? 'New badge' : name || code}</h3>
        <span className="mono">{creating ? '' : code}</span>
      </div>
      <div className="card-b stack16">
        {src && (!creating || image) ? <PixelScales src={src} alt={code || 'New badge'} /> : null}
        <Field label="Badge code" hint={creating ? 'Letters, numbers, underscores and hyphens. It names the image file too.' : undefined}>
          <input className="input mono" value={code} onChange={(e) => setCode(e.target.value.trim())} readOnly={!creating} maxLength={64} />
        </Field>
        <Field label="Name">
          <input className="input" value={name} onChange={(e) => setName(e.target.value)} maxLength={64} />
        </Field>
        <Field label="Description" optional>
          <textarea className="input" rows={2} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={255} />
        </Field>
        <div className="actions" style={{ justifyContent: 'space-between' }}>
          <span className="sub">{image ? 'New image ready to save' : creating ? 'A 40×40 GIF, PNG or JPEG' : 'Replace the image'}</span>
          <ImagePick label={image ? 'Change image' : 'Choose image'} accept="image/gif,image/png,image/jpeg" maxBytes={64 * 1024} onPick={(url) => setImage(url)} />
        </div>
        {textsFile && !textsFile.writable ? (
          <Note tone="warn" icon="warning" title="The client texts file can't be written">
            Names are kept in {textsFile.path || 'the Nitro external texts file'}, which this server can't write. Saving will fail until it can.
          </Note>
        ) : null}
        <p className="sub" style={{ fontSize: 12 }}>
          Names and descriptions go in the client's external texts; players see them after the client reloads texts.
        </p>
      </div>
      <div className="card-f" style={{ alignItems: 'center' }}>
        <Wp path="saved" />
        <Btn
          variant="primary"
          disabled={busy || !validCode || name.trim() === '' || (creating && !image)}
          onClick={async () => {
            const ok = await run(() => put(`/badges/${encodeURIComponent(code)}`, { name, description, image }));
            if (ok) onSaved(code);
          }}
        >
          {creating ? 'Create badge' : 'Save badge'}
        </Btn>
      </div>
    </aside>
  );
}

function Review({ drawing, onDone }: { drawing: Drawing; onDone: () => void }) {
  const [name, setName] = useState(drawing.name);
  const [description, setDescription] = useState(drawing.description);
  const [checks, setChecks] = useState({ text: false, safe: false });
  const [reason, setReason] = useState('');
  const { busy, run } = useSave();

  return (
    <aside className="peek side-col" aria-label="Review drawing" style={{ flexBasis: 380 }}>
      <div className="card-h">
        <h3>Review drawing</h3>
        <span className="mono">{drawing.code}</span>
      </div>
      <div className="card-b stack16">
        <PixelScales src={drawing.image} alt={drawing.name || 'Drawing'} />
        <Field label="Badge code">
          <input className="input mono" value={drawing.code} readOnly />
        </Field>
        <Field label="Name">
          <input className="input" value={name} onChange={(e) => setName(e.target.value)} maxLength={24} />
        </Field>
        <Field label="Description" optional>
          <textarea className="input" rows={2} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={255} />
        </Field>
        <Kv
          rows={[
            ['Drawn by', drawing.player ? <PlayerChip key="p" player={drawing.player} /> : 'Unknown'],
            ['Submitted', fmtDateTime(drawing.at)],
            ['Earlier drawings', drawing.earlier.total === 0 ? 'First drawing' : `${drawing.earlier.approved} approved, ${drawing.earlier.total - drawing.earlier.approved} other`],
          ]}
        />
        <fieldset style={{ border: 0, padding: 0, margin: 0 }} className="stack16">
          <legend style={{ fontSize: 13, fontWeight: 500, padding: 0, marginBottom: 8 }}>Checks</legend>
          <label className="check">
            <input type="checkbox" checked={checks.text} onChange={(e) => setChecks((c) => ({ ...c, text: e.target.checked }))} />
            No text, logos or real brands
          </label>
          <label className="check">
            <input type="checkbox" checked={checks.safe} onChange={(e) => setChecks((c) => ({ ...c, safe: e.target.checked }))} />
            Nothing offensive or copied
          </label>
        </fieldset>
        <Field label="Reason" optional="required to reject">
          <textarea className="input" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} />
        </Field>
      </div>
      <div className="card-f" style={{ flexWrap: 'wrap' }}>
        <Btn
          variant="danger"
          disabled={busy || reason.trim().length < 3}
          title={reason.trim().length < 3 ? 'Give a reason to reject' : undefined}
          onClick={async () => {
            if (await run(() => post(`/badges/drawn/${drawing.id}`, { action: 'reject', reason }))) onDone();
          }}
        >
          Reject
        </Btn>
        <Btn
          variant="primary"
          disabled={busy || !checks.text || !checks.safe || name.trim() === ''}
          title={!checks.text || !checks.safe ? 'Tick both checks first' : undefined}
          onClick={async () => {
            if (await run(() => post(`/badges/drawn/${drawing.id}`, { action: 'approve', name, description }))) onDone();
          }}
        >
          Approve and give
        </Btn>
      </div>
      <div className="card-b stack16" style={{ borderTop: '1px solid var(--line)', gap: 6, paddingTop: 12, paddingBottom: 12 }}>
        <span className="actions" style={{ gap: 6 }}>
          <Wp path="saved" />
          <span className="sub">badge name and description</span>
        </span>
        <span className="actions" style={{ gap: 6 }}>
          <Wp path="saved" />
          <span className="sub">given to the player, who sees it at their next login</span>
        </span>
      </div>
    </aside>
  );
}
