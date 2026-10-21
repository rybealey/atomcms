import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { del, put } from '@hk/lib/api';
import { fmtNumber } from '@hk/lib/format';
import { Btn, Empty, Field, Icon, Loadable, Pill, PlayerRef, Wp } from '@hk/ui';

interface View { id: number; name: string; description: string | null; filters: Record<string, string>; shared: boolean; on_today: boolean; mine: boolean; owner: PlayerRef | null; today: number }
interface Data { views: View[]; sources: string[]; kinds: Record<string, string> }

const FIELDS = [
  { key: 'kind', label: 'Kind', op: 'is' },
  { key: 'source', label: 'Source', op: 'is' },
  { key: 'player', label: 'Player ID', op: 'is' },
  { key: 'room', label: 'Room ID', op: 'is' },
  { key: 'q', label: 'Text', op: 'contains' },
  { key: 'when', label: 'When', op: 'within' },
] as const;
const WHEN: Record<string, string> = { '1h': 'Last hour', '24h': 'Last 24 hours', '7d': 'Last 7 days', '30d': 'Last 30 days', all: 'All time' };

function describe(filters: Record<string, string>, kinds: Record<string, string>): string[] {
  const out: string[] = [];
  if (filters.kind) out.push(kinds[filters.kind] ?? filters.kind);
  if (filters.source) out.push(filters.source);
  if (filters.player) out.push(`Player #${filters.player}`);
  if (filters.room) out.push(`Room #${filters.room}`);
  if (filters.q) out.push(`“${filters.q}”`);
  if (filters.when) out.push(WHEN[filters.when] ?? filters.when);
  if (filters.off) out.push(`${filters.off.split(',').length} sources off`);
  return out.length ? out : ['Everything'];
}

export default function SavedViews() {
  useCrumbs([{ label: 'Logs', to: '/logs' }, { label: 'Saved views' }]);
  const state = useFetch<Data>('/views');
  const [sel, setSel] = useState<number | null>(null);
  const views = state.data?.views ?? [];
  const cur = views.find((v) => v.id === sel) ?? views[0] ?? null;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Saved views</h1>
          <p className="sub">Log searches you run often · shared ones are visible to every rank that can open the log explorer</p>
        </div>
        <Btn variant="primary" to="/logs">
          New view from log explorer
        </Btn>
      </div>
      <div className="split">
        <section className="card main-col">
          <Loadable state={state} empty={(d) => (d.views.length === 0 ? <Empty title="No saved views" body="Set filters in the log explorer, then Save view." /> : false)}>
            {(d) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>View</th>
                      <th>Filters</th>
                      <th>Owner</th>
                      <th className="r">Matches today</th>
                      <th>On Today</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.views.map((v) => (
                      <tr key={v.id} className={`click ${cur?.id === v.id ? 'sel' : ''}`} onClick={() => setSel(v.id)}>
                        <td style={{ fontWeight: 500 }}>{v.name}</td>
                        <td>
                          <span style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                            {describe(v.filters, d.kinds).map((f) => (
                              <Pill key={f}>{f}</Pill>
                            ))}
                          </span>
                        </td>
                        <td style={{ color: 'var(--muted)' }}>
                          {v.owner?.username ?? 'Unknown'}
                          {v.shared ? ' · shared' : ''}
                        </td>
                        <td className="r num">{fmtNumber(v.today)}</td>
                        <td>{v.on_today ? <Pill tone="brand">Alerts</Pill> : null}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
        {cur && state.data ? <Editor key={cur.id} view={cur} data={state.data} onChanged={state.reload} /> : null}
      </div>
    </main>
  );
}

function Editor({ view, data, onChanged }: { view: View; data: Data; onChanged: () => void }) {
  const { toast, confirm } = useSession();
  const navigate = useNavigate();
  const [name, setName] = useState(view.name);
  const [rules, setRules] = useState<[string, string][]>(Object.entries(view.filters).filter(([k]) => k !== 'off'));
  const [shared, setShared] = useState(view.shared);
  const [onToday, setOnToday] = useState(view.on_today);
  const [busy, setBusy] = useState(false);
  useEffect(() => setName(view.name), [view.name]);
  const editable = view.mine;
  const filters = Object.fromEntries([...rules.filter(([, v]) => v !== ''), ...(view.filters.off ? [['off', view.filters.off]] : [])]);
  const unused = FIELDS.filter((f) => !rules.some(([k]) => k === f.key));

  const save = async () => {
    setBusy(true);
    try {
      await put(`/views/${view.id}`, { name, filters, shared, on_today: onToday });
      toast('ok', 'View saved');
      onChanged();
    } catch (e) {
      toast('bad', 'Could not save', e instanceof Error ? e.message : undefined);
    } finally {
      setBusy(false);
    }
  };

  return (
    <aside className="peek side-col" aria-label="Edit view" style={{ flexBasis: 380 }}>
      <div className="card-h">
        <h3>{view.name}</h3>
        <Btn sm onClick={() => navigate(`/logs?${new URLSearchParams(view.filters).toString()}`)}>
          Open
        </Btn>
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <Field label="Name">
          <input className="input" value={name} onChange={(e) => setName(e.target.value)} disabled={!editable} maxLength={64} />
        </Field>
        <div className="field">
          <span>Filters</span>
          {rules.map(([key, value], i) => {
            const f = FIELDS.find((x) => x.key === key)!;
            return (
              <div key={key} style={{ display: 'grid', gridTemplateColumns: '96px 64px minmax(0, 1fr) 28px', gap: 6, alignItems: 'center' }}>
                <span className="select">{f.label}</span>
                <span className="sub">{f.op}</span>
                {key === 'kind' || key === 'source' || key === 'when' ? (
                  <select className="input" value={value} disabled={!editable} onChange={(e) => setRules((r) => r.map((x, j) => (j === i ? [key, e.target.value] : x)))} aria-label={`${f.label} value`}>
                    {(key === 'kind' ? Object.entries(data.kinds) : key === 'source' ? data.sources.map((s) => [s, s]) : Object.entries(WHEN)).map(([v, l]) => (
                      <option key={v} value={v}>
                        {l}
                      </option>
                    ))}
                  </select>
                ) : (
                  <input className={`input ${key === 'q' ? '' : 'mono'}`} value={value} disabled={!editable} aria-label={`${f.label} value`} onChange={(e) => setRules((r) => r.map((x, j) => (j === i ? [key, key === 'q' ? e.target.value : e.target.value.replace(/\D/g, '')] : x)))} />
                )}
                {editable ? <Btn variant="quiet" sm iconOnly icon="close" aria-label="Remove filter" onClick={() => setRules((r) => r.filter((_, j) => j !== i))} /> : <span />}
              </div>
            );
          })}
          {editable && unused.length > 0 ? (
            <select className="btn sm quiet" style={{ alignSelf: 'flex-start' }} value="" onChange={(e) => e.target.value && setRules((r) => [...r, [e.target.value, e.target.value === 'kind' ? Object.keys(data.kinds)[0] : e.target.value === 'when' ? '24h' : e.target.value === 'source' ? data.sources[0] : '']])} aria-label="Add filter">
              <option value="">Add filter</option>
              {unused.map((f) => (
                <option key={f.key} value={f.key}>
                  {f.label}
                </option>
              ))}
            </select>
          ) : null}
        </div>
        <label className="check">
          <input type="checkbox" checked={shared} disabled={!editable} onChange={(e) => setShared(e.target.checked)} />
          Share with all staff
        </label>
        <label className="check">
          <input type="checkbox" checked={onToday} disabled={!editable} onChange={(e) => setOnToday(e.target.checked)} />
          Add matches to Needs attention on Today
        </label>
        {!editable ? (
          <p className="sub" style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
            <Icon name="lock" /> Shared by {view.owner?.username ?? 'another staff member'}; only they can change it.
          </p>
        ) : null}
      </div>
      {editable ? (
        <div className="card-f" style={{ justifyContent: 'space-between' }}>
          <Btn
            variant="danger"
            onClick={async () => {
              if (!(await confirm({ title: `Delete “${view.name}”?`, body: 'The view goes for everyone it is shared with. The logs themselves stay.', cta: 'Delete view', danger: true }))) return;
              await del(`/views/${view.id}`);
              toast('ok', 'View deleted');
              onChanged();
            }}
          >
            Delete
          </Btn>
          <span className="actions">
            <Wp path="saved" />
            <Btn variant="primary" disabled={busy || name.trim().length < 2} onClick={save}>
              Save view
            </Btn>
          </span>
        </div>
      ) : null}
    </aside>
  );
}
