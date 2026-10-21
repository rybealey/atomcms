import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put, qs } from '@hk/lib/api';
import { fmtDay, fmtNumber } from '@hk/lib/format';
import { Btn, Empty, Loadable, Pager, Pill, SearchInput, Tabs, useDebounced, Wp } from '@hk/ui';
import { BadgeArt, ListEditor, useSave } from './shared';

interface Level { id: number | null; level: number; progress: number; pixels: number; points: number; badge: string }
interface Achievement { code: string; name: string; category: string; players: number; levels: Level[] }
interface Text { id: number; key: string; value: string; changed: number | null }

export default function Achievements() {
  useCrumbs([{ label: 'Content', to: '/content/badges' }, { label: 'Achievements and texts' }]);
  const { can } = useSession();
  const [params, setParams] = useSearchParams();
  const tabs = [...(can('manage_achievements') ? ['ach'] : []), ...(can('manage_emulator_texts') ? ['texts'] : [])];
  const tab = tabs.includes(params.get('tab') ?? '') ? (params.get('tab') as string) : tabs[0] ?? 'ach';
  const [counts, setCounts] = useState<{ ach?: number; texts?: number }>({});
  const countAch = useCallback((n: number) => setCounts((c) => (c.ach === n ? c : { ...c, ach: n })), []);
  const countTexts = useCallback((n: number) => setCounts((c) => (c.texts === n ? c : { ...c, texts: n })), []);

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Achievements and texts</h1>
          <p className="sub">achievements and client_external_texts</p>
        </div>
      </div>
      <Tabs
        label="Views"
        active={tab}
        onChange={(t) => setParams(t === 'ach' ? {} : { tab: t }, { replace: true })}
        tabs={[
          ...(can('manage_achievements') ? [{ id: 'ach', label: 'Achievements', n: counts.ach ?? null }] : []),
          ...(can('manage_emulator_texts') ? [{ id: 'texts', label: 'Client texts', n: counts.texts ?? null }] : []),
        ]}
      />
      {tab === 'ach' ? <AchievementList onCount={countAch} /> : <Texts onCount={countTexts} />}
    </main>
  );
}

function AchievementList({ onCount }: { onCount: (n: number) => void }) {
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const state = useFetch<{ items: Achievement[] }>(`/achievements${qs({ q })}`);
  const [sel, setSel] = useState<string | null>(null);
  const cur = state.data?.items.find((a) => a.code === sel) ?? state.data?.items[0] ?? null;
  useEffect(() => {
    if (state.data) onCount(state.data.items.length);
  }, [state.data, onCount]);

  return (
    <ListEditor
      list={
        <section className="card">
          <div className="card-h">
            <SearchInput value={text} onChange={setText} placeholder="Search codes" width={260} />
          </div>
          <Loadable state={state} rows={8} empty={(d) => (d.items.length === 0 ? <Empty title="No achievements match" /> : false)}>
            {(d) => (
              <div className="tw" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
                <table className="t">
                  <thead>
                    <tr>
                      <th>Code</th>
                      <th>Name</th>
                      <th>Category</th>
                      <th className="r">Levels</th>
                      <th className="r">Players with it</th>
                    </tr>
                  </thead>
                  <tbody>
                    {d.items.map((a) => (
                      <tr key={a.code} className={`click ${cur?.code === a.code ? 'sel' : ''}`} onClick={() => setSel(a.code)}>
                        <td className="mono" style={{ color: 'var(--ink)' }}>
                          {a.code}
                        </td>
                        <td style={{ fontWeight: 500 }}>{a.name || <span className="sub">—</span>}</td>
                        <td>
                          <Pill>{a.category}</Pill>
                        </td>
                        <td className="r num">{a.levels.length}</td>
                        <td className="r num">{fmtNumber(a.players)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
      }
      editor={cur ? <LevelEditor key={cur.code} achievement={cur} onSaved={state.reload} /> : null}
    />
  );
}

function LevelEditor({ achievement, onSaved }: { achievement: Achievement; onSaved: () => void }) {
  const [levels, setLevels] = useState<Level[]>(achievement.levels);
  const { busy, run } = useSave();
  const set = (i: number, k: 'progress' | 'pixels' | 'points', v: string) => setLevels((ls) => ls.map((l, j) => (j === i ? { ...l, [k]: Number(v.replace(/\D/g, '') || 0) } : l)));
  const last = levels[levels.length - 1];

  return (
    <aside className="peek side-col" aria-label="Edit achievement" style={{ flexBasis: 520 }}>
      <div className="card-h">
        <div>
          <h3>{achievement.name || achievement.code}</h3>
          <p className="sub mono">{achievement.code}</p>
        </div>
        <Wp path="saved" />
      </div>
      <div className="card-b stack16" style={{ gap: 12 }}>
        <span style={{ fontSize: 13, fontWeight: 500 }}>Levels</span>
        <div className="tw" style={{ border: '1px solid var(--line)', borderRadius: 10, maxHeight: '55vh', overflowY: 'auto' }}>
          <table className="t">
            <thead>
              <tr>
                <th>Level</th>
                <th className="r">Needs</th>
                <th>Badge</th>
                <th className="r">Duckets</th>
                <th className="r">Points</th>
              </tr>
            </thead>
            <tbody>
              {levels.map((l, i) => (
                <tr key={l.id ?? `new${l.level}`}>
                  <td className="num">{l.level}</td>
                  <td className="r">
                    <input className="input mono" value={l.progress} onChange={(e) => set(i, 'progress', e.target.value)} aria-label={`Progress needed for level ${l.level}`} style={{ width: 72, height: 28, textAlign: 'right', display: 'inline-flex' }} />
                  </td>
                  <td>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }} title={l.badge}>
                      <BadgeArt code={l.badge} size={28} />
                      <span className="mono">{l.badge.length > 12 ? `…${l.badge.slice(-9)}` : l.badge}</span>
                    </span>
                  </td>
                  <td className="r">
                    <input className="input mono" value={l.pixels} onChange={(e) => set(i, 'pixels', e.target.value)} aria-label={`Duckets for level ${l.level}`} style={{ width: 56, height: 28, textAlign: 'right', display: 'inline-flex' }} />
                  </td>
                  <td className="r">
                    <input className="input mono" value={l.points} onChange={(e) => set(i, 'points', e.target.value)} aria-label={`Points for level ${l.level}`} style={{ width: 56, height: 28, textAlign: 'right', display: 'inline-flex' }} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="sub" style={{ fontSize: 12 }}>
          Rewards are paid in duckets and achievement points. The hotel reads changes after :update achievements or a restart.
        </p>
      </div>
      <div className="card-f">
        <Btn onClick={() => setLevels((ls) => [...ls, { id: null, level: last.level + 1, progress: last.progress * 2, pixels: last.pixels, points: last.points, badge: `${achievement.code}${last.level + 1}` }])}>Add level</Btn>
        <Btn
          variant="primary"
          disabled={busy}
          onClick={async () => {
            const firstId = achievement.levels[0]?.id;
            if (!firstId) return;
            if (await run(() => put(`/achievements/${firstId}`, { levels: levels.map((l) => ({ id: l.id, level: l.level, progress: l.progress, pixels: l.pixels, points: l.points })) }))) onSaved();
          }}
        >
          Save
        </Btn>
      </div>
    </aside>
  );
}

function Texts({ onCount }: { onCount: (n: number) => void }) {
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const [page, setPage] = useState(1);
  const state = useFetch<{ items: Text[]; total: number; page: number; last_page: number }>(`/texts${qs({ q, page })}`);
  const [edits, setEdits] = useState<Record<number, string>>({});
  const { busy, run } = useSave();
  useEffect(() => setPage(1), [q]);
  useEffect(() => {
    if (state.data) onCount(state.data.total);
  }, [state.data, onCount]);
  const dirty = Object.keys(edits).length;

  return (
    <section className="card">
      <div className="card-h">
        <SearchInput value={text} onChange={setText} placeholder="Search keys or text" width={320} />
        <div className="actions">
          <Wp path="saved" label="Saved · players see it after the client reloads texts" />
          <Btn
            variant="primary"
            sm
            disabled={!dirty || busy}
            onClick={async () => {
              let ok = true;
              for (const [id, value] of Object.entries(edits)) ok = (await run(() => put(`/texts/${id}`, { value }), 'Saved')) && ok;
              if (ok) {
                setEdits({});
                state.reload();
              }
            }}
          >
            {dirty ? `Save ${dirty} ${dirty === 1 ? 'change' : 'changes'}` : 'Save'}
          </Btn>
        </div>
      </div>
      <Loadable state={state} rows={8} empty={(d) => (d.items.length === 0 ? <Empty title="No texts match" /> : false)}>
        {(d) => (
          <>
            <div className="tw">
              <table className="t">
                <thead>
                  <tr>
                    <th>Key</th>
                    <th>Text</th>
                    <th>Changed</th>
                  </tr>
                </thead>
                <tbody>
                  {d.items.map((x) => (
                    <tr key={x.id} className={edits[x.id] !== undefined ? 'sel' : ''}>
                      <td className="mono" style={{ color: 'var(--ink)' }}>
                        {x.key}
                      </td>
                      <td style={{ width: '100%' }}>
                        <input
                          className="input"
                          value={edits[x.id] ?? x.value}
                          aria-label={x.key}
                          onChange={(e) => setEdits((ed) => (e.target.value === x.value ? Object.fromEntries(Object.entries(ed).filter(([k]) => Number(k) !== x.id)) : { ...ed, [x.id]: e.target.value }))}
                          style={{ width: '100%', minWidth: 320 }}
                        />
                      </td>
                      <td style={{ color: 'var(--muted)' }}>{x.changed ? fmtDay(x.changed) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pager page={d.page} lastPage={d.last_page} total={d.total} label="texts" onPage={setPage} />
          </>
        )}
      </Loadable>
    </section>
  );
}
