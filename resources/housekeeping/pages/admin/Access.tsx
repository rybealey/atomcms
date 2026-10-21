import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { put } from '@hk/lib/api';
import { fmtDay } from '@hk/lib/format';
import { Btn, Card, Empty, Icon, Loadable, Pill, PlayerRef } from '@hk/ui';

interface Row { key: string; label: string; mirror: string | null; min_rank: number; description?: string; screen?: string | null }
interface Data {
  ranks: { id: number; name: string }[];
  screens: Row[];
  unused: Row[];
  commands: Row[];
  staff: { player: PlayerRef; rank: number; online: boolean; team: string | null }[];
  changes: { id: number; at: number; actor: PlayerRef | null; summary: string; reason: string | null }[];
}

export default function Access() {
  useCrumbs([{ label: 'Admin', to: '/admin/access' }, { label: 'Access' }]);
  const { me, toast, refreshMe, rankName } = useSession();
  const state = useFetch<Data>('/access');
  const [screens, setScreens] = useState<Record<string, number>>({});
  const [commands, setCommands] = useState<Record<string, number>>({});
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [showUnused, setShowUnused] = useState(false);
  const d = state.data;
  const dirty = Object.keys(screens).length + Object.keys(commands).length;
  const mine = me.user.rank;

  const groups = useMemo(() => {
    if (!d) return [];
    return [
      { id: 'screens', name: 'Panel screens', src: 'website_housekeeping_permissions.min_rank', rows: d.screens, edits: screens, set: setScreens, mono: false },
      { id: 'commands', name: 'In-game commands', src: 'permissions_commands.group_id', rows: d.commands, edits: commands, set: setCommands, mono: true },
      ...(showUnused && d.unused.length ? [{ id: 'unused', name: 'Not read by the panel', src: 'website_housekeeping_permissions · kept for the website', rows: d.unused, edits: screens, set: setScreens, mono: false }] : []),
    ];
  }, [d, screens, commands, showUnused]);

  const save = async () => {
    setBusy(true);
    try {
      const r = await put<{ message: string }>('/access', { screens, commands, reason });
      toast('ok', r.message);
      setScreens({});
      setCommands({});
      setReason('');
      state.reload();
      refreshMe();
    } catch (e) {
      toast('bad', 'Could not save', e instanceof Error ? e.message : undefined);
    } finally {
      setBusy(false);
    }
  };

  const ranks = d?.ranks ?? [];
  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Access</h1>
          <p className="sub">Who can see each panel screen and use each in-game command · click a cell to set the lowest rank</p>
        </div>
        <span className="actions">
          <Pill>You can change ranks up to {rankName(mine)}</Pill>
          {d?.unused.length ? (
            <label className="check">
              <input type="checkbox" checked={showUnused} onChange={(e) => setShowUnused(e.target.checked)} />
              Show {d.unused.length} the panel no longer reads
            </label>
          ) : null}
        </span>
      </div>
      {dirty > 0 ? (
        <section className="peek" style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12, padding: '12px 16px', borderColor: 'var(--brand)' }}>
          <Pill tone="brand">{dirty} unsaved</Pill>
          <label className="input grow" style={{ minWidth: 240 }}>
            <input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Reason for this change (required)" aria-label="Reason" maxLength={500} />
          </label>
          <Btn
            onClick={() => {
              setScreens({});
              setCommands({});
            }}
          >
            Discard
          </Btn>
          <Btn variant="primary" disabled={reason.trim().length < 3 || busy} onClick={save}>
            Save changes
          </Btn>
          <span className="sub" style={{ flexBasis: '100%', fontSize: 12 }}>
            Screens save to website_housekeeping_permissions. Commands save to permissions_commands, then the hotel reloads ranks over RCON.
          </span>
        </section>
      ) : null}
      <div className="split">
        <section className="card main-col">
          <Loadable state={state} rows={12}>
            {() => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Permission · mirrors</th>
                      {ranks.map((r) => (
                        <th key={r.id} style={{ textAlign: 'center' }} title={r.name}>
                          {r.id}
                          <span className="sub" style={{ display: 'block', fontSize: 11, lineHeight: '14px', fontWeight: 400 }}>
                            {r.name.length > 10 ? r.name.slice(0, 9) + '…' : r.name}
                          </span>
                        </th>
                      ))}
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {groups.map((g) => (
                      <React.Fragment key={g.id}>
                        <tr className="group">
                          <td colSpan={ranks.length + 2}>
                            <span className="overline">{g.name}</span>{' '}
                            <span className="mono" style={{ fontSize: 11, marginLeft: 6 }}>
                              {g.src}
                            </span>
                          </td>
                        </tr>
                        {g.rows.map((p) => {
                          const min = g.edits[p.key] ?? p.min_rank;
                          const changed = g.edits[p.key] !== undefined && g.edits[p.key] !== p.min_rank;
                          const locked = p.min_rank > mine;
                          return (
                            <tr key={p.key} className={changed ? 'sel' : ''}>
                              <td title={p.description} className="wrap" style={{ minWidth: 200 }}>
                                <span style={{ fontWeight: 500, fontFamily: g.mono ? 'var(--mono)' : undefined }}>{p.label}</span>
                                {locked ? <Icon name="lock" size={12} style={{ marginLeft: 6, color: 'var(--subtle)' }} /> : null}
                                {p.mirror ? (
                                  <span className={g.id === 'commands' ? 'sub' : 'mono'} style={{ display: 'block', fontSize: 11, lineHeight: '16px' }}>
                                    {p.mirror}
                                  </span>
                                ) : null}
                              </td>
                              {ranks.map((r) => {
                                const on = r.id >= min;
                                const disabled = locked || r.id > mine;
                                return (
                                  <td key={r.id} style={{ textAlign: 'center', padding: '0 6px' }}>
                                    <button
                                      type="button"
                                      aria-label={`${p.label}, rank ${r.id} ${on ? 'allowed' : 'not allowed'}`}
                                      aria-pressed={on}
                                      disabled={disabled}
                                      onClick={() => g.set((e) => {
                                        const next = { ...e };
                                        if (r.id === p.min_rank) delete next[p.key];
                                        else next[p.key] = r.id;
                                        return next;
                                      })}
                                      style={{ width: 32, height: 28, borderRadius: 6, border: `1px solid ${on ? 'transparent' : 'var(--line)'}`, background: on ? 'var(--ok-soft)' : 'var(--surface)', color: 'var(--ok-ink)', cursor: disabled ? 'not-allowed' : 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', opacity: disabled && !on ? 0.5 : 1 }}
                                    >
                                      {on ? <Icon name="check" size={14} /> : null}
                                    </button>
                                  </td>
                                );
                              })}
                              <td>{changed ? <Pill tone="brand">Changed</Pill> : null}</td>
                            </tr>
                          );
                        })}
                      </React.Fragment>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
        <div className="side-col stack">
          <Card title="Staff" sub={d ? `${d.staff.length} staff · ${d.staff.filter((s) => s.online).length} online` : undefined} action={<Link to="/admin/staff" style={{ fontSize: 13 }}>Roster</Link>}>
            <Loadable state={state} empty={(x) => (x.staff.length === 0 ? <Empty title="No staff yet" /> : false)}>
              {(x) =>
                x.staff.map((s) => (
                  <div key={s.player.id} className="row">
                    <span className={`dot ${s.online ? 'ok' : ''}`} />
                    <Link to={`/players/${s.player.id}`} className="grow ell" style={{ fontWeight: 500, color: 'var(--ink)' }}>
                      {s.player.username}
                    </Link>
                    <span className="sub">{s.team ?? ''}</span>
                    <Pill>Rank {s.rank}</Pill>
                  </div>
                ))
              }
            </Loadable>
          </Card>
          <Card title="Recent changes" sub="From activity_log">
            <Loadable state={state} empty={(x) => (x.changes.length === 0 ? <Empty title="No changes yet" /> : false)}>
              {(x) =>
                x.changes.map((c) => (
                  <div key={c.id} className="row" style={{ alignItems: 'flex-start' }}>
                    <span className="mono" style={{ width: 52 }}>
                      {fmtDay(c.at)}
                    </span>
                    <div className="grow">
                      <p>
                        {c.actor?.username ?? 'Someone'} {c.summary}
                      </p>
                      {c.reason ? <p className="sub">“{c.reason}”</p> : null}
                    </div>
                  </div>
                ))
              }
            </Loadable>
          </Card>
        </div>
      </div>
    </main>
  );
}

