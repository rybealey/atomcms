import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { del, post, put } from '@hk/lib/api';
import { fmtNumber, fmtResponse, plural, relTime } from '@hk/lib/format';
import { Btn, Card, Countdown, Dialog, Empty, Field, FilterSelect, Kind, Loadable, Note, Pill, PlayerChip, PlayerRef, RoomChip, Switch, Tone, Wp, fmtLeft } from '@hk/ui';
import { severity } from '../people/PlayerTabs';

interface Custody { id: number; player: PlayerRef | null; officer: PlayerRef | null; room: { id: number; name: string }; charges: string[]; sentence: number; left: number; online: boolean; jailed_at: number; trial: boolean }
interface Call { id: number; kind: '911' | 'backup'; at: number; message: string; caller: PlayerRef | null; room: { id: number; name: string }; responders: (PlayerRef | null)[]; response: number | null; mark: 'helpful' | 'abuse' | null; open: boolean }
interface Sheet { player: PlayerRef | null; ticket_total: number; jail_only: boolean; charges: { id: number; crime: string; severity: number; ticket: number | null; jail: number; officer: PlayerRef | null; at: number; dropped_at: number }[] }
export interface Crime { id: number; key_name: string; name: string; description: string; jail_seconds: number; severity: number; stackable: boolean; ticketable: boolean; ticket_amount: number; active: boolean; sort_order: number }
interface JusticeData {
  now: number;
  summary: { custody: number; open_charges: number; charged_players: number; open_calls: number };
  custody: Custody[];
  calls: Call[];
  sheets: Sheet[];
  crimes: Crime[];
  can_edit_crimes: boolean;
}

const mins = (s: number) => {
  const h = Math.floor(s / 3600);
  const m = Math.round((s % 3600) / 60);
  return h > 0 ? (m ? `${h}h ${m}m` : `${h}h`) : `${m}m`;
};

function callStatus(c: Call): { label: string; tone: Tone } {
  if (c.kind === 'backup') return c.responders.length ? { label: `${c.responders.length} answered`, tone: '' } : { label: 'Backup needed', tone: 'warn' };
  if (c.open) return { label: 'No responder', tone: 'bad' };
  if (c.response !== null && Date.now() / 1000 - c.at < 900) return { label: 'Responding', tone: 'warn' };
  return { label: 'Closed', tone: '' };
}

export default function Justice() {
  useCrumbs([{ label: 'City', to: '/city/justice' }, { label: 'Justice desk' }]);
  const [params] = useSearchParams();
  const highlight = Number(params.get('call') ?? 0);
  const [all, setAll] = useState(false);
  const state = useFetch<JusticeData>(`/justice${all ? '?charges=all' : ''}`);
  const [editing, setEditing] = useState<Crime | 'new' | null>(null);
  const d = state.data;

  useEffect(() => {
    if (highlight && d) document.getElementById(`call-${highlight}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, [highlight, d]);

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Justice desk</h1>
          <p className="sub">
            {d
              ? `${d.summary.custody} in custody · ${plural(d.summary.open_charges, 'open charge')} on ${plural(d.summary.charged_players, 'player')} · ${plural(d.summary.open_calls, 'open 911 call')}`
              : 'Loading'}
          </p>
        </div>
        <p className="sub" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <Wp path="game" icon /> Releases and charge changes still go through the City Panel
        </p>
      </div>

      <div className="cols">
        <Card title="In custody" sub="Counts down while the prisoner is in the hotel" action={d ? <Pill tone={d.custody.length ? 'warn' : 'ok'}>{d.custody.length} jailed</Pill> : null}>
          <Loadable state={state} empty={(x) => (x.custody.length === 0 ? <Empty title="Nobody is in custody" body="Arrests made in game show up here." /> : false)}>
            {(x) => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Prisoner</th>
                      <th>Charge</th>
                      <th className="hide-phone">Officer</th>
                      <th>Time left</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {x.custody.map((c) => (
                      <tr key={c.id}>
                        <td>
                          <PlayerChip player={c.player} />
                        </td>
                        <td className="wrap">
                          {c.charges.join(', ') || <span className="sub">No charge sheet</span>}
                          {c.trial ? (
                            <span style={{ marginLeft: 6 }}>
                              <Pill tone="info">Trial requested</Pill>
                            </span>
                          ) : null}
                        </td>
                        <td className="hide-phone">{c.officer ? <PlayerChip player={c.officer} /> : null}</td>
                        <td>
                          {c.online ? (
                            <Countdown until={x.now + c.left} total={c.sentence} width={72} />
                          ) : (
                            <div style={{ width: 72, display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 }} title="The clock only runs while the prisoner is in the hotel">
                              <span className="num">{fmtLeft(c.left)}</span>
                              <span className="sub" style={{ fontSize: 11 }}>
                                Paused · offline
                              </span>
                            </div>
                          )}
                        </td>
                        <td className="r">
                          <Btn variant="quiet" sm wp="game" disabled title="Release from the City Panel for now">
                            Release
                          </Btn>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </Card>

        <Card title="911 feed" sub="Calls from the last 2 hours" action={d ? <Pill tone={d.summary.open_calls ? 'bad' : 'ok'}>{d.summary.open_calls ? `${d.summary.open_calls} open` : 'All answered'}</Pill> : null}>
          <Loadable state={state} empty={(x) => (x.calls.length === 0 ? <Empty title="No calls in the last 2 hours" /> : false)}>
            {(x) =>
              x.calls.map((c) => {
                const st = callStatus(c);
                return (
                  <div key={c.id} id={`call-${c.id}`} className={`row ${highlight === c.id ? 'on' : ''}`} style={{ alignItems: 'flex-start' }}>
                    <Kind icon="siren" tone={st.tone} />
                    <div className="grow stack16" style={{ gap: 6 }}>
                      <div className="actions" style={{ gap: 8 }}>
                        <span style={{ fontWeight: 500 }}>{c.kind === 'backup' ? 'Backup requested' : c.message || 'No message'}</span>
                        <Pill tone={st.tone}>{st.label}</Pill>
                        {c.mark ? <Pill tone={c.mark === 'abuse' ? 'bad' : 'ok'}>{c.mark === 'abuse' ? 'Marked abuse' : 'Marked helpful'}</Pill> : null}
                      </div>
                      <div className="actions sub" style={{ gap: 6 }}>
                        <PlayerChip player={c.caller} />
                        <RoomChip id={c.room.id} name={c.room.name} />
                        <span>
                          {c.responders.length
                            ? `${c.responders.map((r) => r?.username).filter(Boolean).join(', ')} ${c.response !== null ? `answered in ${fmtResponse(c.response)}` : 'answered'}`
                            : 'Waiting'}
                        </span>
                      </div>
                    </div>
                    <span className="mono">{relTime(c.at)}</span>
                  </div>
                );
              })
            }
          </Loadable>
        </Card>
      </div>

      <Card
        title="Charge sheets"
        sub={all ? 'Every charge, grouped by player' : 'Open charges grouped by player'}
        action={<FilterSelect label="Show" value={all ? 'all' : 'open'} anyLabel="Open only" options={[{ value: 'open', label: 'Open only' }, { value: 'all', label: 'Open and cleared' }]} onChange={(v) => setAll(v === 'all')} />}
      >
        <Loadable state={state} empty={(x) => (x.sheets.length === 0 ? <Empty title="No open charges" body="New charges filed in game show up here." /> : false)}>
          {(x) => (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 340px), 1fr))' }}>
              {x.sheets.map((s, i) => (
                <div key={s.player?.id ?? i} style={{ borderRight: '1px solid var(--line)', borderBottom: '1px solid var(--line)', minWidth: 0 }}>
                  <div className="row" style={{ justifyContent: 'space-between' }}>
                    <PlayerChip player={s.player} />
                    <span className="sub">
                      Ticket total <span className="num" style={{ color: 'var(--ink)' }}>{s.jail_only ? 'Jail only' : fmtNumber(s.ticket_total)}</span>
                    </span>
                  </div>
                  {s.charges.map((c) => {
                    const sev = severity(c.severity);
                    return (
                      <div key={c.id} className="row" style={{ paddingTop: 8, paddingBottom: 8, opacity: c.dropped_at ? 0.6 : 1 }}>
                        <span style={{ minWidth: 110, display: 'inline-flex' }}>
                          <Pill tone={sev.tone}>{sev.label}</Pill>
                        </span>
                        <div className="grow">
                          <p>
                            {c.crime}
                            {c.dropped_at ? <span className="sub"> · cleared</span> : null}
                          </p>
                          <p className="sub">
                            {c.officer ? c.officer.username : 'Automatic'} · {relTime(c.at)}
                          </p>
                        </div>
                        <span className="num">{c.ticket !== null ? fmtNumber(c.ticket) : 'Jail only'}</span>
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          )}
        </Loadable>
      </Card>

      <Card
        title="Crime catalogue"
        sub="The charges officers can file · read by the emulator on next use"
        action={
          <>
            <Wp path="saved" icon />
            {d?.can_edit_crimes ? (
              <Btn variant="primary" sm onClick={() => setEditing('new')}>
                Add crime
              </Btn>
            ) : null}
          </>
        }
      >
        <Loadable state={state} empty={(x) => (x.crimes.length === 0 ? <Empty title="No crimes yet" /> : false)}>
          {(x) => (
            <>
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>Key</th>
                      <th>Name</th>
                      <th>Severity</th>
                      <th className="r">Jail time</th>
                      <th>Ticketable</th>
                      <th className="r">Ticket</th>
                      <th>Stackable</th>
                      <th>Active</th>
                      <th className="r">Order</th>
                      {x.can_edit_crimes ? <th /> : null}
                    </tr>
                  </thead>
                  <tbody>
                    {x.crimes.map((c) => {
                      const sev = severity(c.severity);
                      return (
                        <tr key={c.id} className={x.can_edit_crimes ? 'click' : ''} onClick={() => x.can_edit_crimes && setEditing(c)}>
                          <td className="mono">{c.key_name}</td>
                          <td style={{ fontWeight: 500 }}>{c.name}</td>
                          <td>
                            <Pill tone={sev.tone}>{sev.label}</Pill>
                          </td>
                          <td className="r num">{mins(c.jail_seconds)}</td>
                          <td>{c.ticketable ? 'Yes' : 'No'}</td>
                          <td className="r num">{c.ticketable ? fmtNumber(c.ticket_amount) : 'Jail only'}</td>
                          <td>{c.stackable ? 'Yes' : 'No'}</td>
                          <td>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                              <span className={`dot ${c.active ? 'ok' : ''}`} />
                              {c.active ? 'Active' : 'Off'}
                            </span>
                          </td>
                          <td className="r num" style={{ color: 'var(--muted)' }}>
                            {c.sort_order}
                          </td>
                          {x.can_edit_crimes ? (
                            <td className="r">
                              <Btn variant="quiet" sm onClick={(e) => { e.stopPropagation(); setEditing(c); }}>
                                Edit
                              </Btn>
                            </td>
                          ) : null}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              {!x.can_edit_crimes ? (
                <div className="row">
                  <span className="sub">Editing crimes needs the rank that can use the City Panel’s Justice tab (rp_city_justice).</span>
                </div>
              ) : null}
            </>
          )}
        </Loadable>
      </Card>

      {editing ? <CrimeEditor crime={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onSaved={state.reload} /> : null}
    </main>
  );
}

function CrimeEditor({ crime, onClose, onSaved }: { crime: Crime | null; onClose: () => void; onSaved: () => void }) {
  const { toast, confirm } = useSession();
  const [f, setF] = useState({
    key_name: crime?.key_name ?? '',
    name: crime?.name ?? '',
    description: crime?.description ?? '',
    jail_minutes: crime ? String(Math.round(crime.jail_seconds / 60)) : '3',
    severity: String(crime?.severity ?? 1),
    stackable: crime?.stackable ?? false,
    ticketable: crime?.ticketable ?? true,
    ticket_amount: String(crime?.ticket_amount || 8),
    active: crime?.active ?? true,
    sort_order: String(crime?.sort_order ?? ''),
  });
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((x) => ({ ...x, [k]: v }));
  const valid = /^[a-z0-9]+$/.test(f.key_name) && f.name.trim().length > 0 && /^\d+$/.test(f.jail_minutes) && (!f.ticketable || Number(f.ticket_amount) >= 1);

  const save = async () => {
    setBusy(true);
    setErrors({});
    const body = {
      key_name: f.key_name,
      name: f.name.trim(),
      description: f.description,
      jail_seconds: Number(f.jail_minutes) * 60,
      severity: Number(f.severity),
      stackable: f.stackable,
      ticketable: f.ticketable,
      ticket_amount: f.ticketable ? Number(f.ticket_amount) : undefined,
      active: f.active,
      sort_order: f.sort_order === '' ? undefined : Number(f.sort_order),
    };
    try {
      const r = crime ? await put<{ message: string }>(`/justice/crimes/${crime.id}`, body) : await post<{ message: string }>('/justice/crimes', body);
      toast('ok', r.message);
      onSaved();
      onClose();
    } catch (e) {
      const errs = (e as { body?: { errors?: Record<string, string[]> } }).body?.errors;
      if (errs) setErrors(Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]])));
      else toast('bad', 'Could not save', e instanceof Error ? e.message : undefined);
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    if (!crime) return;
    const ok = await confirm({ title: `Remove ${crime.name}?`, body: 'A crime with charges on record is turned off instead, so old charge sheets still read.', cta: 'Remove crime', danger: true });
    if (!ok) return;
    const r = await del<{ message: string }>(`/justice/crimes/${crime.id}`);
    toast('ok', r.message);
    onSaved();
    onClose();
  };

  return (
    <Dialog
      title={crime ? `Edit ${crime.name}` : 'Add crime'}
      wp="saved"
      wide
      onClose={onClose}
      footer={
        <>
          {crime ? (
            <Btn variant="danger" onClick={remove} style={{ marginRight: 'auto' }}>
              Remove
            </Btn>
          ) : null}
          <Btn onClick={onClose}>Cancel</Btn>
          <Btn variant="primary" disabled={!valid || busy} onClick={save}>
            {crime ? 'Save crime' : 'Add crime'}
          </Btn>
        </>
      }
    >
      <div className="grid2">
        <Field label="Key" hint="Lowercase letters and digits; typed after :charge in game." error={errors.key_name}>
          <input className="input mono" value={f.key_name} maxLength={24} onChange={(e) => set('key_name', e.target.value.toLowerCase().replace(/[^a-z0-9]/g, ''))} autoFocus={!crime} />
        </Field>
        <Field label="Name" error={errors.name}>
          <input className="input" value={f.name} maxLength={64} onChange={(e) => set('name', e.target.value)} />
        </Field>
      </div>
      <Field label="Description" optional error={errors.description}>
        <input className="input" value={f.description} maxLength={255} onChange={(e) => set('description', e.target.value)} />
      </Field>
      <div className="grid2">
        <Field label="Severity" hint="The wanted stars it gives (the highest open charge counts)." error={errors.severity}>
          <select className="input" value={f.severity} onChange={(e) => set('severity', e.target.value)}>
            {[1, 2, 3, 4, 5].map((n) => (
              <option key={n} value={n}>
                {severity(n).label}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Jail time" hint="Minutes, up to a day." error={errors.jail_seconds}>
          <span className="input">
            <input className="mono" inputMode="numeric" value={f.jail_minutes} onChange={(e) => set('jail_minutes', e.target.value.replace(/\D/g, ''))} aria-label="Jail minutes" />
            <span className="sub">min</span>
          </span>
        </Field>
        <Field label="Ticket amount" hint={f.ticketable ? 'Coins paid instead of jail.' : 'Only for ticketable crimes.'} error={errors.ticket_amount}>
          <input className="input mono" inputMode="numeric" disabled={!f.ticketable} value={f.ticketable ? f.ticket_amount : ''} onChange={(e) => set('ticket_amount', e.target.value.replace(/\D/g, ''))} />
        </Field>
        <Field label="Order" optional error={errors.sort_order}>
          <input className="input mono" inputMode="numeric" value={f.sort_order} onChange={(e) => set('sort_order', e.target.value.replace(/\D/g, ''))} />
        </Field>
      </div>
      <div className="actions" style={{ gap: 20 }}>
        <label className="check">
          <Switch checked={f.ticketable} onChange={(v) => set('ticketable', v)} label="Ticketable" /> Ticketable
        </label>
        <label className="check">
          <Switch checked={f.stackable} onChange={(v) => set('stackable', v)} label="Stackable" /> Stackable
        </label>
        <label className="check">
          <Switch checked={f.active} onChange={(v) => set('active', v)} label="Active" /> Active
        </label>
      </div>
      <Note icon="info" title="Saved, then read on next use">
        Nothing in the emulator caches crimes: the next :charge uses this.
      </Note>
    </Dialog>
  );
}
