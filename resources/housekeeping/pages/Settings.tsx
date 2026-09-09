import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { get, put } from '@hk/lib/api';
import { fmtDateTime, fmtSecondsLong, titleCase, UNIT_SECONDS } from '@hk/lib/format';
import { fullUrl } from '@hk/lib/avatar';
import { GROUPS, metaFor, SettingMeta } from '@hk/lib/settingsMeta';
import { Avatar, Button, Card, ErrorBox, Icon, Segmented, Skeleton, StateText, Stepper, Switch, Tabs } from '@hk/ui';

interface SettingRow { key: string; value: string | null; comment: string; secret: boolean; has_value: boolean }
interface SettingsData { settings: SettingRow[] }
interface Change { id: number; who: string; look: string | null; key: string; old: string | null; new: string | null; at: number | null }

const UNLOCK_MINUTES = 15;

export default function Settings() {
  const { group: groupParam } = useParams();
  const navigate = useNavigate();
  const { me, can, confirm, toast } = useSession();
  const group = GROUPS.find((g) => g.id === (groupParam ?? 'general')) ?? GROUPS[0];
  useCrumbs([{ label: 'Settings' }, { label: titleCase(group.label) }]);

  const { data, error, loading, reload, setData } = useFetch<SettingsData>('/settings');
  const [vals, setVals] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<Record<string, string>>({});
  const [revealed, setRevealed] = useState<Record<string, boolean>>({});
  const [unlockedUntil, setUnlockedUntil] = useState<number | null>(null);
  const [tab, setTab] = useState<'form' | 'changes'>('form');
  const [changes, setChanges] = useState<Change[] | null>(null);
  const [query, setQuery] = useState('');
  const [highlight, setHighlight] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [now, setNow] = useState(Date.now());
  const canEdit = can('manage_website_settings');
  const secretEdits = useRef<Record<string, string>>({});

  useEffect(() => {
    if (!data) return;
    const next: Record<string, string> = {};
    for (const row of data.settings) next[row.key] = row.value ?? '';
    setVals(next);
    setSaved(next);
    secretEdits.current = {};
  }, [data]);

  useEffect(() => {
    if (unlockedUntil === null) return;
    const t = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(t);
  }, [unlockedUntil]);
  const unlocked = unlockedUntil !== null && unlockedUntil > now;

  useEffect(() => {
    if (tab !== 'changes' || changes !== null) return;
    get<{ changes: Change[] }>('/settings/changes')
      .then((r) => setChanges(r.changes))
      .catch(() => setChanges([]));
  }, [tab, changes]);

  const rows = data?.settings ?? [];
  const metas = useMemo(() => rows.map((r) => ({ row: r, meta: metaFor(r.key, r.comment) })), [rows]);
  const groupsWithItems = GROUPS.filter((g) => g.id !== 'other' || metas.some((m) => m.meta.group === 'other'));

  const dirtyKeys = Object.keys(vals).filter((k) => vals[k] !== saved[k]);
  const set = (key: string, value: string) => setVals((v) => ({ ...v, [key]: value }));
  const isOn = (key: string) => vals[key] === '1' || vals[key] === 'true';

  const toggleBool = async (meta: SettingMeta) => {
    const cur = isOn(meta.key);
    if (!cur && meta.danger) {
      const ok = await confirm(meta.danger);
      if (!ok) return;
    }
    set(meta.key, cur ? '0' : '1');
  };

  const save = async () => {
    if (dirtyKeys.length === 0) return;
    setSaving(true);
    try {
      const payload: Record<string, string> = {};
      for (const k of dirtyKeys) payload[k] = vals[k];
      const result = await put<{ changed: string[]; settings: SettingRow[] }>('/settings', { settings: payload });
      setData(() => ({ settings: result.settings }));
      setChanges(null);
      toast('success', 'Saved', `${result.changed.length} setting${result.changed.length === 1 ? '' : 's'} saved and cache refreshed.`);
    } catch (e) {
      toast('danger', 'Not saved', e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setSaving(false);
    }
  };

  const discard = () => setVals({ ...saved });

  const unlock = async () => {
    const ok = await confirm({ title: 'Unlock developer settings?', body: 'File paths, client locations and raw keys. A wrong value here takes the hotel offline.', impacts: [`Locks again in ${UNLOCK_MINUTES} minutes`, 'Every change is written to the audit trail'], cta: 'Unlock', tone: 'brand' });
    if (!ok) return;
    setUnlockedUntil(Date.now() + UNLOCK_MINUTES * 60 * 1000);
    setNow(Date.now());
    toast('warning', 'Developer settings unlocked', `Locks again in ${UNLOCK_MINUTES} minutes.`);
  };

  const items = metas
    .filter(({ meta }) => meta.group === group.id && !meta.hidden)
    .filter(({ meta }) => !meta.dependsOn || isOn(meta.dependsOn));

  const q = query.trim().toLowerCase();
  const results = q ? metas.filter(({ meta }) => `${meta.key} ${meta.label} ${meta.desc}`.toLowerCase().includes(q)).slice(0, 8) : [];

  const jumpTo = (meta: SettingMeta) => {
    setQuery('');
    setTab('form');
    navigate(`/settings/${meta.group}`);
    setHighlight(meta.key);
    window.setTimeout(() => document.getElementById(`anchor-${meta.key}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80);
    window.setTimeout(() => setHighlight(null), 2500);
  };

  const advLocked = group.locked === true && !unlocked;
  const maintOn = isOn('maintenance_enabled');
  const maintRank = Number(vals['min_maintenance_login_rank'] ?? 5) || 5;
  const maintRankName = me.ranks.find((r) => r.id === maintRank)?.name ?? `rank ${maintRank}`;
  const remaining = unlockedUntil ? Math.max(0, Math.round((unlockedUntil - now) / 1000)) : 0;

  if (error) return <ErrorBox message={error} retry={reload} />;

  return (
    <>
      <section className="hk-settings" aria-label="Settings">
        <aside className="hk-settings__side">
          <h1 className="hk-h1">Settings</h1>
          <label className="hk-search">
            <Icon name="search" size={16} />
            <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={`Search ${rows.length} settings`} aria-label="Search settings" />
          </label>
          {q ? (
            <div className="hk-results">
              <div className="hk-results__head">{results.length} results</div>
              {results.map(({ meta }) => (
                <button key={meta.key} type="button" onClick={() => jumpTo(meta)}>
                  <span style={{ display: 'block', fontWeight: 600, fontSize: 14 }}>{titleCase(meta.label)}</span>
                  <span className="small muted" style={{ display: 'block', fontSize: 12 }}>
                    {titleCase(GROUPS.find((g) => g.id === meta.group)?.label ?? '')} · <code>{meta.key}</code>
                  </span>
                </button>
              ))}
            </div>
          ) : null}
          <nav aria-label="Settings groups" className="hk-groupnav">
            {groupsWithItems.map((g) => (
              <button key={g.id} type="button" aria-current={g.id === group.id ? 'page' : undefined} onClick={() => navigate(`/settings/${g.id}`)}>
                <Icon name={g.icon} size={16} />
                <span className="label">{titleCase(g.label)}</span>
                {g.locked && !unlocked ? <Icon name="lock" size={14} /> : null}
              </button>
            ))}
            {can('manage_housekeeping_permissions') ? (
              <Link to="/staff" className="hk-nav__item" style={{ minHeight: 44, fontSize: 14, borderTop: '2px solid var(--line-soft)' }}>
                <Icon name="users" size={16} />
                <span className="label">Staff ranks & permissions</span>
              </Link>
            ) : null}
          </nav>
        </aside>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
          <div className="hk-pagehead" style={{ alignItems: 'flex-start' }}>
            <div>
              <h2 className="hk-h1" style={{ marginBottom: 6 }}>{titleCase(group.label)}</h2>
              <p className="hk-lede">{group.desc}</p>
            </div>
            <Tabs tabs={[{ id: 'form', label: 'Settings' }, { id: 'changes', label: 'Changes' }]} active={tab} onChange={(id) => setTab(id as 'form' | 'changes')} />
          </div>

          {tab === 'changes' ? (
            <Card flat>
              {changes === null ? (
                <Skeleton rows={4} />
              ) : changes.length === 0 ? (
                <div className="hk-card__body muted small">No settings have been changed from this panel yet.</div>
              ) : (
                changes.map((ch) => (
                  <div key={ch.id} className="hk-row" style={{ flexWrap: 'wrap', minHeight: 56 }}>
                    <Avatar look={ch.look ?? ''} imager={me.config.imager} size="sm" />
                    <span style={{ flex: 1, minWidth: 200 }}>
                      <strong>{ch.who}</strong> changed <strong>{titleCase(metaFor(ch.key, '').label)}</strong>
                    </span>
                    <span className="mono" style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                      <s className="muted">{ch.old ?? '—'}</s>
                      <span>→</span>
                      <span style={{ background: 'var(--sunken)', padding: '1px 4px' }}>{ch.new ?? '—'}</span>
                    </span>
                    <span className="small muted">{fmtDateTime(ch.at)}</span>
                  </div>
                ))
              )}
            </Card>
          ) : loading || !data ? (
            <Card>
              <Skeleton rows={6} />
            </Card>
          ) : (
            <>
              {group.id === 'maintenance' ? (
                <div className={`hk-hero ${maintOn ? 'hk-hero--danger' : ''}`}>
                  <div style={{ flex: 1, minWidth: 240 }}>
                    <h3>Maintenance Mode Is {maintOn ? 'On' : 'Off'}</h3>
                    <p>
                      {maintOn
                        ? `Players below ${maintRankName} (${maintRank}) see the message below instead of the website. Staff can still log in.`
                        : `The hotel website is open. Turn this on to show the maintenance page to everyone below ${maintRankName} (${maintRank}).`}
                    </p>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <span style={{ fontWeight: 600, fontSize: 13 }}>{maintOn ? 'On' : 'Off'}</span>
                    <Switch checked={maintOn} disabled={!canEdit} label="Maintenance mode" onChange={() => toggleBool(metaFor('maintenance_enabled', ''))} />
                  </div>
                </div>
              ) : null}

              {group.id === 'staff' && can('manage_housekeeping_permissions') ? (
                <Link to="/staff" className="hk-row--btn hk-card hk-card--flat" style={{ padding: '14px 16px', minHeight: 0 }}>
                  <Icon name="users" size={20} />
                  <span className="hk-row__main">
                    <strong>Open the rank ladder and permission matrix</strong>
                    <span className="hk-row__sub">See every rank, its members and which rank can do what in housekeeping.</span>
                  </span>
                  <span aria-hidden="true" style={{ fontWeight: 600 }}>▶</span>
                </Link>
              ) : null}

              {advLocked ? (
                <div className="hk-lockbox">
                  <Icon name="lock" size={40} />
                  <div style={{ fontWeight: 600, fontSize: 15 }}>Developer Settings Are Locked</div>
                  <p className="hk-lede" style={{ maxWidth: '46ch', textAlign: 'center' }}>
                    File paths, client locations and raw keys. A wrong value here takes the hotel offline. Unlocking is logged and lasts {UNLOCK_MINUTES} minutes.
                  </p>
                  <Button variant="secondary" onClick={unlock} disabled={!canEdit}>
                    Unlock Developer Settings
                  </Button>
                </div>
              ) : (
                <>
                  {group.locked && unlocked ? (
                    <div className="hk-unlocked">
                      <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Icon name="lock-open" size={16} />
                        Unlocked for {Math.floor(remaining / 60)}:{String(remaining % 60).padStart(2, '0')} · edits are logged to the audit trail
                      </span>
                      <button type="button" className="hk-linkbtn" onClick={() => setUnlockedUntil(null)}>
                        Lock
                      </button>
                    </div>
                  ) : null}
                  <Card>
                    {items.length === 0 ? <div className="hk-card__body muted small">Nothing in this group yet.</div> : null}
                    {items.map(({ row, meta }) => (
                      <SettingField
                        key={meta.key}
                        row={row}
                        meta={meta}
                        value={vals[meta.key] ?? ''}
                        savedValue={saved[meta.key] ?? ''}
                        disabled={!canEdit}
                        highlight={highlight === meta.key}
                        revealed={revealed[meta.key] === true}
                        onReveal={() => setRevealed((r) => ({ ...r, [meta.key]: !r[meta.key] }))}
                        onChange={(v) => set(meta.key, v)}
                        onToggle={() => toggleBool(meta)}
                        ranks={me.ranks}
                        imager={me.config.imager}
                        onCopy={() => toast('success', 'Copied', `${titleCase(meta.label)} copied to clipboard`)}
                      />
                    ))}
                  </Card>
                </>
              )}
            </>
          )}
        </div>
      </section>

      {dirtyKeys.length > 0 ? (
        <div role="status" className="hk-dirtybar" style={{ margin: '24px -24px -24px' }}>
          <span className="hk-dot hk-dot--warning hk-dot--blink" style={{ borderColor: 'var(--cream)' }} />
          <span style={{ flex: 1, fontSize: 14 }}>
            You have <strong>{dirtyKeys.length}</strong> unsaved {dirtyKeys.length === 1 ? 'change' : 'changes'}. Saving refreshes the cache automatically.
          </span>
          <button type="button" className="hk-linkbtn" onClick={discard}>
            Discard
          </button>
          <Button variant="primary" size="sm" onClick={save} disabled={saving}>
            {saving ? 'Saving…' : 'Save Changes'}
          </Button>
        </div>
      ) : null}
    </>
  );
}

function SettingField({ row, meta, value, savedValue, disabled, highlight, revealed, onReveal, onChange, onToggle, ranks, imager, onCopy }: {
  row: SettingRow; meta: SettingMeta; value: string; savedValue: string; disabled: boolean; highlight: boolean; revealed: boolean;
  onReveal: () => void; onChange: (v: string) => void; onToggle: () => void; ranks: { id: number; name: string }[]; imager: string; onCopy: () => void;
}) {
  const id = `set-${meta.key}`;
  const changed = value !== savedValue;
  const compact = meta.type === 'bool' || meta.type === 'int' || meta.type === 'enum' || meta.type === 'rank' || meta.type === 'duration';
  const on = value === '1' || value === 'true';
  const num = Number(value) || 0;

  let control: React.ReactNode;
  switch (meta.type) {
    case 'bool':
      control = (
        <div className="hk-switchrow">
          <StateText on={on} />
          <Switch checked={on} disabled={disabled} label={titleCase(meta.label)} onChange={onToggle} />
        </div>
      );
      break;
    case 'int':
      control = <Stepper id={id} value={num} min={meta.min} step={meta.step} unit={meta.unit} onChange={(v) => onChange(String(v))} />;
      break;
    case 'duration': {
      const d = fmtSecondsLong(num);
      control = (
        <>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
            <Stepper value={d.n} min={1} onChange={(n) => onChange(String(Math.max(1, n) * UNIT_SECONDS[d.unit]))} />
            <Segmented options={[{ value: 'days', label: 'days' }, { value: 'months', label: 'months' }, { value: 'years', label: 'years' }]} value={d.unit} onChange={(u) => onChange(String(d.n * UNIT_SECONDS[u]))} />
          </div>
          <span className="small muted mono">{num} seconds</span>
        </>
      );
      break;
    }
    case 'enum':
      control = <Segmented options={(meta.options ?? []).map((o) => ({ value: o, label: o }))} value={value} onChange={onChange} />;
      break;
    case 'rank':
      control = (
        <span className="hk-selectwrap">
          <select id={id} className="hk-select" value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
            {ranks.map((r) => (
              <option key={r.id} value={String(r.id)}>
                {r.name} ({r.id})
              </option>
            ))}
          </select>
        </span>
      );
      break;
    case 'secret':
      control = (
        <>
          <div className="hk-secret">
            <input id={id} type={revealed ? 'text' : 'password'} value={value} placeholder={row.has_value ? '•••••••••••• (stored — type to replace)' : 'Not set'} spellCheck={false} disabled={disabled} onChange={(e) => onChange(e.target.value)} style={{ letterSpacing: revealed ? 'normal' : 2 }} />
            <button type="button" aria-label={revealed ? 'Hide' : 'Reveal'} aria-pressed={revealed} onClick={onReveal}>
              <Icon name={revealed ? 'eye-closed' : 'eye'} size={16} />
            </button>
            <button
              type="button"
              aria-label="Copy"
              onClick={() => {
                if (value) void navigator.clipboard?.writeText(value).then(onCopy);
              }}
            >
              <Icon name="copy" size={16} />
            </button>
          </div>
          <span className="small muted">{row.has_value ? 'A value is stored. It is never sent to the browser; typing here replaces it.' : 'Nothing stored yet.'}</span>
        </>
      );
      break;
    case 'path':
      control = <input id={id} className="hk-input hk-input--mono" value={value} spellCheck={false} readOnly={disabled} onChange={(e) => onChange(e.target.value)} />;
      break;
    case 'textarea':
      control = (
        <>
          <textarea id={id} className="hk-textarea" value={value} rows={5} readOnly={disabled} onChange={(e) => onChange(e.target.value)} />
          {meta.preview ? (
            <div className="hk-preview">
              <div className="tag">Preview · what players see</div>
              <img src="/assets/images/pixelrp_logo.gif" alt="PixelRP" style={{ height: 40, imageRendering: 'pixelated', marginBottom: 8 }} />
              <div style={{ whiteSpace: 'pre-wrap', textWrap: 'pretty' }}>{value}</div>
            </div>
          ) : null}
        </>
      );
      break;
    case 'image':
      control = (
        <div style={{ display: 'flex', gap: 12, alignItems: 'center', width: '100%' }}>
          <span style={{ width: 96, height: 56, flex: 'none', border: '2px solid var(--line)', background: 'var(--sunken)', display: 'grid', placeItems: 'center', overflow: 'hidden' }}>
            {value ? <img src={value} alt="" style={{ maxWidth: '100%', maxHeight: '100%' }} onError={(e) => ((e.target as HTMLImageElement).style.display = 'none')} /> : null}
          </span>
          <input id={id} className="hk-input hk-input--mono" value={value} spellCheck={false} readOnly={disabled} onChange={(e) => onChange(e.target.value)} />
        </div>
      );
      break;
    case 'look':
      control = (
        <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start', width: '100%' }}>
          <span style={{ width: 72, height: 120, flex: 'none', border: '2px solid var(--line)', background: 'var(--orange-500)', display: 'grid', placeItems: 'center', overflow: 'hidden' }}>
            {value ? <img src={fullUrl(imager, value)} alt="Starting outfit" className="px-art" style={{ height: 110 }} /> : null}
          </span>
          <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 6 }}>
            <input id={id} className="hk-input hk-input--mono" value={value} spellCheck={false} readOnly={disabled} onChange={(e) => onChange(e.target.value)} />
            <span className="small muted">Figure string · preview renders from the hotel imager</span>
          </span>
        </div>
      );
      break;
    default:
      control = (
        <>
          <input id={id} className={`hk-input ${meta.mono ? 'hk-input--mono' : ''}`} value={value} maxLength={meta.max} readOnly={disabled} onChange={(e) => onChange(e.target.value)} />
          {meta.max ? (
            <span className="small muted">
              {value.length} / {meta.max}
            </span>
          ) : null}
        </>
      );
  }

  return (
    <div id={`anchor-${meta.key}`} className={`hk-field ${highlight ? 'hk-field--hl' : ''}`} style={{ padding: compact ? '14px 16px' : 16 }}>
      <div className="hk-field__meta">
        <label htmlFor={id}>{titleCase(meta.label)}</label>
        {meta.desc ? <div className="hk-field__desc">{meta.desc}</div> : null}
        <div className="hk-field__tags">
          <code>{meta.key}</code>
          {meta.danger ? <span className="hk-tag-danger">Asks to confirm</span> : null}
          {changed ? <span className="hk-tag-unsaved">Unsaved</span> : null}
        </div>
      </div>
      <div className={`hk-field__ctl ${compact ? 'hk-field__ctl--auto' : ''}`}>{control}</div>
    </div>
  );
}
