import { useEffect, useMemo, useState } from 'react';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put } from '@hk/lib/api';
import { fmtDay } from '@hk/lib/format';
import { Btn, Card, Empty, Loadable, Pill, PlayerRef, SearchInput, Wp } from '@hk/ui';

interface Setting { key: string; value: string; description: string; group: string; path: 'saved' | 'game' }
interface Change { id: number; at: number; actor: PlayerRef | null; summary: string; before: string | null; after: string | null; reason: string | null }
interface Data { table: string; settings: Setting[]; changes: Change[] }

const ORDER = ['Hotel', 'City', 'Economy', 'Rooms and social', 'Other'];
const NOTES: Record<string, string> = { Hotel: 'Core hotel switches', City: 'Read by the city systems', Economy: 'Costs, rewards and trading', 'Rooms and social': 'Limits for rooms, groups and friends', Other: 'Everything else' };

export default function EmulatorSettings() {
  useCrumbs([{ label: 'Admin', to: '/admin/access' }, { label: 'Emulator settings' }]);
  const { toast } = useSession();
  const state = useFetch<Data>('/emulator-settings');
  const [group, setGroup] = useState('Hotel');
  const [query, setQuery] = useState('');
  const [vals, setVals] = useState<Record<string, string>>({});
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const d = state.data;
  useEffect(() => {
    if (d) setVals(Object.fromEntries(d.settings.map((s) => [s.key, s.value])));
  }, [d]);
  const groups = useMemo(() => ORDER.filter((g) => d?.settings.some((s) => s.group === g)), [d]);
  const q = query.trim().toLowerCase();
  const shown = (d?.settings ?? []).filter((s) => (q ? s.key.includes(q) || s.description.toLowerCase().includes(q) : s.group === group));
  const dirty = (d?.settings ?? []).filter((s) => s.path === 'saved' && vals[s.key] !== undefined && vals[s.key] !== s.value);

  const save = async () => {
    setBusy(true);
    try {
      const r = await put<{ message: string }>('/emulator-settings', { settings: Object.fromEntries(dirty.map((s) => [s.key, vals[s.key]])), reason: reason || null });
      toast('ok', r.message);
      setReason('');
      state.reload();
    } catch (e) {
      toast('bad', 'Could not save', e instanceof Error ? e.message : undefined);
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Emulator settings</h1>
          <p className="sub">{d?.table ?? 'server_settings'} · each setting says how it reaches the hotel</p>
        </div>
        <SearchInput value={query} onChange={setQuery} placeholder="Find a setting or key" width={260} />
      </div>
      <div className="split">
        <nav className="card" style={{ flex: '1 1 200px', padding: 8, display: 'flex', flexDirection: 'column', gap: 2 }} aria-label="Setting groups">
          {groups.map((g) => {
            const on = !q && g === group;
            return (
              <button key={g} type="button" className={`nav-s ${on ? 'on' : ''}`} aria-pressed={on} onClick={() => { setQuery(''); setGroup(g); }} style={{ border: 0, font: 'inherit', fontSize: 13, cursor: 'pointer', height: 34, background: on ? 'var(--sunken)' : 'transparent' }}>
                {g}{' '}
                <span className="mono" style={{ fontSize: 11 }}>
                  {d?.settings.filter((s) => s.group === g).length}
                </span>
              </button>
            );
          })}
        </nav>
        <section className="card main-col" style={{ flexBasis: 560 }}>
          <div className="card-h">
            <h3>{q ? `Matching “${query}”` : group}</h3>
            <span className="sub">{q ? '' : NOTES[group]}</span>
          </div>
          <Loadable state={state} rows={8} empty={() => (shown.length === 0 ? <Empty title="No settings match" /> : false)}>
            {() =>
              shown.map((s) => (
                <div key={s.key} className={`row ${vals[s.key] !== s.value ? 'on' : ''}`} style={{ alignItems: 'flex-start', paddingTop: 14, paddingBottom: 14, flexWrap: 'wrap' }}>
                  <div className="grow" style={{ minWidth: 220 }}>
                    <p style={{ fontWeight: 500 }} className="mono">
                      {s.key}
                    </p>
                    {s.description ? <p className="sub">{s.description.replace(/^pixelrp:\s*/i, '')}</p> : null}
                  </div>
                  <span style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 6 }}>
                    <input className="input mono" style={{ width: 200 }} value={vals[s.key] ?? ''} disabled={s.path === 'game'} onChange={(e) => setVals((v) => ({ ...v, [s.key]: e.target.value }))} aria-label={s.key} title={s.path === 'game' ? 'Changed in the City Panel, which tells everyone online' : undefined} />
                    <Wp path={s.path} label={s.path === 'game' ? 'In-game' : 'Saved, then reloaded'} />
                  </span>
                </div>
              ))
            }
          </Loadable>
          {dirty.length > 0 ? (
            <div className="card-f" style={{ flexWrap: 'wrap' }}>
              <Pill tone="brand">{dirty.length} unsaved</Pill>
              <label className="input grow" style={{ minWidth: 200 }}>
                <input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Reason (optional)" aria-label="Reason" maxLength={500} />
              </label>
              <Btn onClick={() => d && setVals(Object.fromEntries(d.settings.map((s) => [s.key, s.value])))}>Discard</Btn>
              <Btn variant="primary" disabled={busy} onClick={save}>
                Save changes
              </Btn>
            </div>
          ) : null}
        </section>
        <Card className="side-col" title="Change history" sub="From activity_log" style={{ flexBasis: 300 }}>
          <Loadable state={state} empty={(x) => (x.changes.length === 0 ? <Empty title="No changes from the panel yet" /> : false)}>
            {(x) =>
              x.changes.map((c) => (
                <div key={c.id} className="row" style={{ alignItems: 'flex-start' }}>
                  <span className="mono" style={{ width: 52 }}>
                    {fmtDay(c.at)}
                  </span>
                  <div className="grow" style={{ minWidth: 0 }}>
                    <p style={{ overflowWrap: 'anywhere' }}>
                      {c.actor?.username ?? 'Someone'} {c.summary}
                    </p>
                    <p className="sub">{c.reason ? `“${c.reason}”` : `was ${c.before ?? '—'}`}</p>
                  </div>
                </div>
              ))
            }
          </Loadable>
          <div className="row">
            <span className="sub">Settings marked In-game change in the City Panel.</span>
          </div>
        </Card>
      </div>
    </main>
  );
}
