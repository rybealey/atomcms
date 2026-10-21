import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put } from '@hk/lib/api';
import { fmtDay, fmtSecondsLong, UNIT_SECONDS } from '@hk/lib/format';
import { fullUrl } from '@hk/lib/avatar';
import { GROUPS, metaFor, SettingMeta } from '@hk/lib/settingsMeta';
import { Btn, Card, Empty, Field, Icon, Loadable, Pill, PlayerRef, SearchInput, Switch, Wp } from '@hk/ui';

interface SettingRow { key: string; value: string | null; comment: string; secret: boolean; has_value: boolean }
interface Change { id: number; actor: PlayerRef | null; key: string; old: string | null; new: string | null; reason: string | null; at: number }

/** The advanced group is paths the deploy also sets; it unlocks for a few minutes at a time. */
const UNLOCK_MS = 15 * 60 * 1000;

export default function WebsiteSettings() {
  const { group: groupParam } = useParams();
  const navigate = useNavigate();
  const { me, can, confirm, toast } = useSession();
  const state = useFetch<{ settings: SettingRow[] }>('/settings');
  const changes = useFetch<{ changes: Change[] }>('/settings/changes');
  const [vals, setVals] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<Record<string, string>>({});
  const [query, setQuery] = useState('');
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [unlockedUntil, setUnlockedUntil] = useState(0);
  const canEdit = can('manage_website_settings');

  useEffect(() => {
    if (!state.data) return;
    const next: Record<string, string> = {};
    for (const r of state.data.settings) next[r.key] = r.value ?? '';
    setVals(next);
    setSaved(next);
  }, [state.data]);

  const rows = state.data?.settings ?? [];
  const metas = useMemo(() => rows.map((r) => ({ row: r, meta: metaFor(r.key, r.comment) })).filter((x) => !x.meta.hidden), [rows]);
  const q = query.trim().toLowerCase();
  const groupId = q ? null : groupParam ?? 'general';
  const group = GROUPS.find((g) => g.id === groupId) ?? null;
  const shown = metas.filter((x) => (q ? x.meta.key.includes(q) || x.meta.label.toLowerCase().includes(q) || x.meta.desc.toLowerCase().includes(q) : x.meta.group === groupId));
  const dirty = Object.keys(vals).filter((k) => vals[k] !== saved[k]);
  const locked = group?.locked && unlockedUntil < Date.now();
  useCrumbs([{ label: 'Admin', to: '/admin/access' }, { label: 'Website settings', to: '/admin/website' }, ...(group && group.id !== 'general' ? [{ label: group.label }] : [])]);

  const save = async () => {
    for (const k of dirty) {
      const m = metaFor(k, '');
      if (m.danger && (vals[k] === '1' || vals[k] === 'true') && saved[k] !== vals[k]) {
        const ok = await confirm({ title: m.danger.title, body: `${m.danger.body} ${m.danger.impacts.join('. ')}.`, cta: m.danger.cta, danger: true });
        if (!ok) return;
      }
    }
    setBusy(true);
    try {
      await put('/settings', { settings: Object.fromEntries(dirty.map((k) => [k, vals[k]])), reason: reason || null });
      toast('ok', `${dirty.length} ${dirty.length === 1 ? 'setting' : 'settings'} saved`);
      setReason('');
      state.reload();
      changes.reload();
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
          <h1>Website settings</h1>
          <p className="sub">website_settings · applies to the public site straight away</p>
        </div>
        <SearchInput value={query} onChange={setQuery} placeholder="Find a setting" width={260} />
      </div>
      <div className="split">
        <nav className="card" style={{ flex: '1 1 200px', padding: 8, display: 'flex', flexDirection: 'column', gap: 2 }} aria-label="Setting groups">
          {GROUPS.map((g) => {
            const n = metas.filter((x) => x.meta.group === g.id).length;
            if (!n) return null;
            const on = g.id === groupId;
            return (
              <button key={g.id} type="button" className={`nav-s ${on ? 'on' : ''}`} aria-pressed={on} onClick={() => { setQuery(''); navigate(g.id === 'general' ? '/admin/website' : `/admin/website/${g.id}`); }} style={{ border: 0, font: 'inherit', fontSize: 13, cursor: 'pointer', height: 34, background: on ? 'var(--sunken)' : 'transparent' }}>
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                  <Icon name={g.icon} size={14} />
                  {g.label}
                </span>
                <span className="mono" style={{ fontSize: 11 }}>
                  {n}
                </span>
              </button>
            );
          })}
        </nav>
        <section className="card main-col" style={{ flexBasis: 520 }}>
          <div className="card-h">
            <div>
              <h3>{q ? `Matching “${query}”` : group?.label}</h3>
              {!q && group ? <p className="sub">{group.desc}</p> : null}
            </div>
            <Wp path="saved" />
          </div>
          {locked ? (
            <div className="card-b">
              <Empty
                bare
                title="These are file paths and client locations"
                body="A wrong value breaks the site for everyone. The deploy also sets several of them."
                action={<Btn onClick={() => setUnlockedUntil(Date.now() + UNLOCK_MS)}>Unlock for 15 minutes</Btn>}
              />
            </div>
          ) : (
            <Loadable state={state} rows={8} empty={() => (shown.length === 0 ? <Empty title="No settings match" /> : false)}>
              {() =>
                shown.map(({ row, meta }) => (
                  <SettingRowView
                    key={meta.key}
                    row={row}
                    meta={meta}
                    value={vals[meta.key] ?? ''}
                    changed={vals[meta.key] !== saved[meta.key]}
                    disabled={!canEdit || (meta.dependsOn !== undefined && !['1', 'true'].includes(vals[meta.dependsOn] ?? ''))}
                    ranks={me.ranks}
                    imager={me.config.imager}
                    onChange={(v) => setVals((x) => ({ ...x, [meta.key]: v }))}
                  />
                ))
              }
            </Loadable>
          )}
          {dirty.length > 0 ? (
            <div className="card-f" style={{ flexWrap: 'wrap' }}>
              <Pill tone="brand">{dirty.length} unsaved</Pill>
              <label className="input grow" style={{ minWidth: 200 }}>
                <input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Reason (optional, kept in the history)" aria-label="Reason" maxLength={500} />
              </label>
              <Btn onClick={() => setVals(saved)}>Discard</Btn>
              <Btn variant="primary" onClick={save} disabled={busy}>
                Save changes
              </Btn>
            </div>
          ) : null}
        </section>
        <Card className="side-col" title="Change history" sub="From activity_log" style={{ flexBasis: 300 }}>
          <Loadable state={changes} empty={(x) => (x.changes.length === 0 ? <Empty title="No changes yet" /> : false)}>
            {(x) =>
              x.changes.slice(0, 12).map((c) => (
                <div key={c.id} className="row" style={{ alignItems: 'flex-start' }}>
                  <span className="mono" style={{ width: 52 }}>
                    {fmtDay(c.at)}
                  </span>
                  <div className="grow" style={{ minWidth: 0 }}>
                    <p>
                      {c.actor?.username ?? 'Someone'} changed {metaFor(c.key, '').label.toLowerCase()}
                    </p>
                    <p className="sub" style={{ overflowWrap: 'anywhere' }}>
                      {c.reason ? `“${c.reason}”` : `${c.old ?? '—'} → ${c.new ?? '—'}`}
                    </p>
                  </div>
                </div>
              ))
            }
          </Loadable>
        </Card>
      </div>
    </main>
  );
}

function SettingRowView({ row, meta, value, changed, disabled, ranks, imager, onChange }: { row: SettingRow; meta: SettingMeta; value: string; changed: boolean; disabled: boolean; ranks: { id: number; name: string }[]; imager: string; onChange: (v: string) => void }) {
  const on = value === '1' || value === 'true';
  const num = Number(value) || 0;
  let control: React.ReactNode;
  switch (meta.type) {
    case 'bool':
      control = (
        <span className="check">
          <Switch checked={on} disabled={disabled} label={meta.label} onChange={(v) => onChange(v ? '1' : '0')} />
          {on ? 'On' : 'Off'}
        </span>
      );
      break;
    case 'int':
      control = (
        <span className="input" style={{ width: 200 }}>
          <input className="mono" type="number" value={value} min={meta.min} max={meta.max} step={meta.step} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label} />
          {meta.unit ? <span className="sub">{meta.unit}</span> : null}
        </span>
      );
      break;
    case 'duration': {
      const d = fmtSecondsLong(num);
      control = (
        <span className="actions">
          <input className="input mono" type="number" min={1} style={{ width: 90 }} value={d.n} disabled={disabled} onChange={(e) => onChange(String(Math.max(1, Number(e.target.value) || 1) * UNIT_SECONDS[d.unit]))} aria-label={`${meta.label} amount`} />
          <select className="input" value={d.unit} disabled={disabled} onChange={(e) => onChange(String(d.n * UNIT_SECONDS[e.target.value as keyof typeof UNIT_SECONDS]))} aria-label={`${meta.label} unit`}>
            <option value="days">days</option>
            <option value="months">months</option>
            <option value="years">years</option>
          </select>
        </span>
      );
      break;
    }
    case 'enum':
      control = (
        <select className="input" style={{ width: 200 }} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label}>
          {(meta.options ?? []).map((o) => (
            <option key={o} value={o}>
              {o}
            </option>
          ))}
        </select>
      );
      break;
    case 'rank':
      control = (
        <select className="input" style={{ width: 200 }} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label}>
          {ranks.map((r) => (
            <option key={r.id} value={String(r.id)}>
              Rank {r.id} · {r.name}
            </option>
          ))}
        </select>
      );
      break;
    case 'secret':
      control = <input className="input mono" type="password" style={{ width: 280 }} value={value} placeholder={row.has_value ? 'Stored · type to replace' : 'Not set'} autoComplete="new-password" disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label} />;
      break;
    case 'textarea':
      control = <textarea className="input" rows={4} style={{ width: 'min(100%, 420px)' }} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label} />;
      break;
    case 'image':
      control = (
        <span className="actions" style={{ flexWrap: 'nowrap' }}>
          <span style={{ width: 72, height: 40, borderRadius: 6, background: 'var(--tile)', display: 'grid', placeItems: 'center', overflow: 'hidden', flex: 'none' }}>
            {value ? <img src={value} alt="" style={{ maxWidth: '100%', maxHeight: '100%' }} /> : null}
          </span>
          <input className="input mono" style={{ width: 240 }} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label} />
        </span>
      );
      break;
    case 'look':
      control = (
        <span className="actions" style={{ flexWrap: 'nowrap', alignItems: 'flex-start' }}>
          <span style={{ width: 56, height: 96, borderRadius: 8, background: 'var(--tile)', display: 'grid', placeItems: 'center', overflow: 'hidden', flex: 'none' }}>
            {value ? <img src={fullUrl(imager, value, 's')} alt="Starting outfit" className="px" /> : null}
          </span>
          <input className="input mono" style={{ width: 240 }} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label} />
        </span>
      );
      break;
    default:
      control = <input className={`input ${meta.type === 'path' || meta.mono ? 'mono' : ''}`} style={{ width: 280 }} value={value} maxLength={meta.max} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-label={meta.label} />;
  }
  return (
    <div className={`row ${changed ? 'on' : ''}`} style={{ alignItems: 'flex-start', paddingTop: 14, paddingBottom: 14, flexWrap: 'wrap' }}>
      <div className="grow" style={{ minWidth: 220 }}>
        <p style={{ fontWeight: 500 }}>
          {meta.label} {meta.danger ? <Pill tone="warn">Asks to confirm</Pill> : null}
        </p>
        {meta.desc ? <p className="sub">{meta.desc}</p> : null}
        <p className="mono" style={{ fontSize: 11 }}>
          {meta.key}
        </p>
      </div>
      <Field label={<span className="sr-only">{meta.label}</span>} style={{ gap: 0 }}>
        {control}
      </Field>
    </div>
  );
}
