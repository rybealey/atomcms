import { useState } from 'react';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { api } from '@hk/lib/api';
import { fmtNumber } from '@hk/lib/format';
import { Button, Card, EmptyState, ErrorBox, IconButton, Pill, Skeleton, StateText, Switch } from '@hk/ui';

// Roleplay > Crimes. What an on-duty officer can put on someone's rap sheet
// with `:charge <player> <key>`.
//
// This is the one roleplay resource housekeeping writes directly. Corporations
// and gangs live in the emulator's memory and so have to round-trip through
// RCON; crimes are read from the database on every charge, so an edit here is
// live on the next one.

export interface Crime {
  id: number;
  key_name: string;
  name: string;
  description: string;
  jail_seconds: number;
  stackable: boolean;
  active: boolean;
  sort_order: number;
  charges: number;
}
interface CrimeList { available: boolean; items: Crime[] }

type Draft = Omit<Crime, 'id' | 'sort_order' | 'charges'>;

const BLANK: Draft = { key_name: '', name: '', description: '', jail_seconds: 0, stackable: true, active: true };

// The sentence reads in minutes because that is how a duty sergeant thinks
// about it; the column stores seconds because that is how the emulator will
// serve it.
function fmtJail(seconds: number): string {
  if (seconds <= 0) return 'None';
  if (seconds % 3600 === 0) return `${seconds / 3600}h`;
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.round(seconds / 60);
  return minutes >= 60 ? `${Math.floor(minutes / 60)}h ${minutes % 60}m` : `${minutes}m`;
}

function Field({ label, desc, children }: { label: string; desc?: string; children: React.ReactNode }) {
  return (
    <div className="hk-field">
      <div className="hk-field__meta">
        <label>{label}</label>
        {desc ? <div className="hk-field__desc">{desc}</div> : null}
      </div>
      <div className="hk-field__ctl">{children}</div>
    </div>
  );
}

function CrimeForm({ draft, setDraft, onSave, onCancel, saving, error }: {
  draft: Draft;
  setDraft: (next: Draft) => void;
  onSave: () => void;
  onCancel: () => void;
  saving: boolean;
  error: string | null;
}) {
  const set = <K extends keyof Draft>(key: K, value: Draft[K]) => setDraft({ ...draft, [key]: value });
  const minutes = Math.round(draft.jail_seconds / 60);

  return (
    <>
      {error ? <ErrorBox message={error} /> : null}
      <Field label="Name" desc="What appears on the rap sheet.">
        <input className="hk-input" value={draft.name} maxLength={64} placeholder="Grand Theft Auto"
          onChange={(e) => set('name', e.target.value)} />
      </Field>
      <Field label="Command key" desc={`What an officer types: :charge Yavn ${draft.key_name || 'gta'}`}>
        <input className="hk-input hk-input--mono" value={draft.key_name} maxLength={24} placeholder="gta" spellCheck={false}
          onChange={(e) => set('key_name', e.target.value.toLowerCase().replace(/[^a-z0-9]/g, ''))} />
      </Field>
      <Field label="Description" desc="Optional. Shown here, not in game.">
        <input className="hk-input" value={draft.description} maxLength={255} placeholder="Taking a vehicle that is not yours."
          onChange={(e) => set('description', e.target.value)} />
      </Field>
      <Field label="Jail time" desc="Minutes served if the charge sticks. 0 for no custodial time. Nothing jails anyone yet - this is the sentence waiting for it.">
        <input className="hk-input" type="number" min={0} max={1440} value={minutes}
          onChange={(e) => set('jail_seconds', Math.max(0, Math.min(1440, Number(e.target.value) || 0)) * 60)} />
      </Field>
      <Field label="Stackable" desc="Whether this can sit on one sheet more than once. Assault twice is two counts; driving unlicensed is a state, not a tally.">
        <div className="hk-switchrow">
          <Switch checked={draft.stackable} onChange={(v) => set('stackable', v)} label="Stackable" />
          <StateText on={draft.stackable} onText="Two counts allowed" offText="Once only" />
        </div>
      </Field>
      <Field label="Chargeable" desc="Turning this off retires the crime: existing sheets still read, but nobody can charge it again.">
        <div className="hk-switchrow">
          <Switch checked={draft.active} onChange={(v) => set('active', v)} label="Chargeable" />
          <StateText on={draft.active} onText="Chargeable" offText="Retired" />
        </div>
      </Field>
      <div style={{ display: 'flex', gap: 8, padding: 16 }}>
        <Button onClick={onSave} disabled={saving || !draft.name.trim() || !draft.key_name.trim()}>
          {saving ? 'Saving…' : 'Save'}
        </Button>
        <Button variant="secondary" onClick={onCancel} disabled={saving}>Cancel</Button>
      </div>
    </>
  );
}

export default function Crimes() {
  useCrumbs([{ label: 'Roleplay' }, { label: 'Crimes' }]);
  const { data, error, loading, reload } = useFetch<CrimeList>('/roleplay/crimes');
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const [draft, setDraft] = useState<Draft>(BLANK);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  useSession();

  const startNew = () => { setDraft(BLANK); setFormError(null); setEditing('new'); };
  const startEdit = (crime: Crime) => {
    setDraft({
      key_name: crime.key_name, name: crime.name, description: crime.description,
      jail_seconds: crime.jail_seconds, stackable: crime.stackable, active: crime.active,
    });
    setFormError(null);
    setEditing(crime.id);
  };

  const save = async () => {
    setSaving(true);
    setFormError(null);
    try {
      if (editing === 'new') await api('/roleplay/crimes', { method: 'POST', body: JSON.stringify(draft) });
      else await api(`/roleplay/crimes/${editing}`, { method: 'PUT', body: JSON.stringify(draft) });
      setEditing(null);
      reload();
    } catch (e) {
      // A duplicate key is the one a person actually hits, and Laravel words
      // it about a column - say what it means instead.
      const message = e instanceof Error ? e.message : 'Could not save';
      setFormError(/key_name/i.test(message) ? 'Another crime already uses that command key.' : message);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (crime: Crime) => {
    const warning = crime.charges > 0
      ? `${crime.name} is on ${fmtNumber(crime.charges)} open ${crime.charges === 1 ? 'sheet' : 'sheets'}. It will be retired rather than deleted, so the history still reads. Continue?`
      : `Delete ${crime.name}?`;
    if (!window.confirm(warning)) return;
    await api(`/roleplay/crimes/${crime.id}`, { method: 'DELETE' });
    reload();
  };

  if (error) return <ErrorBox message={error} retry={reload} />;

  return (
    <section className="hk-page" aria-label="Crimes">
      <div className="hk-pagehead">
        <div>
          <h1 className="hk-h1" style={{ marginBottom: 6 }}>Crimes</h1>
          <p className="hk-lede">
            What an on-duty officer can charge somebody with using <code>:charge &lt;player&gt; &lt;key&gt;</code>.
            Edits are live on the next charge — nothing caches these.
          </p>
        </div>
        {data?.available && editing === null ? <Button onClick={startNew}>New crime</Button> : null}
      </div>

      {editing !== null ? (
        <Card title={editing === 'new' ? 'New crime' : 'Edit crime'} flat>
          <CrimeForm draft={draft} setDraft={setDraft} onSave={save} onCancel={() => setEditing(null)}
            saving={saving} error={formError} />
        </Card>
      ) : null}

      {loading || !data ? (
        <div className="hk-card"><Skeleton rows={6} /></div>
      ) : !data.available ? (
        <EmptyState title="The crimes table is not installed" body="97_PoliceCharges has not run on this database yet." />
      ) : data.items.length === 0 ? (
        <EmptyState title="No crimes yet" body="Add one and officers can charge it straight away."
          action={<Button onClick={startNew}>New crime</Button>} />
      ) : (
        <div className="hk-card hk-tablewrap">
          <table className="hk-table" style={{ minWidth: 720 }}>
            <thead>
              <tr>
                <th>Crime</th>
                <th>Key</th>
                <th>Jail</th>
                <th>Stackable</th>
                <th>On sheets</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.items.map((crime) => (
                <tr key={crime.id}>
                  <td>
                    <strong>{crime.name}</strong>
                    {crime.description ? <div className="small muted">{crime.description}</div> : null}
                  </td>
                  <td><code>{crime.key_name}</code></td>
                  <td>{fmtJail(crime.jail_seconds)}</td>
                  <td><StateText on={crime.stackable} onText="Yes" offText="Once only" /></td>
                  <td>{crime.charges > 0 ? fmtNumber(crime.charges) : <span className="muted">—</span>}</td>
                  <td>{crime.active ? <Pill tone="success">Chargeable</Pill> : <Pill tone="warning">Retired</Pill>}</td>
                  <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                    <IconButton name="sliders" label={`Edit ${crime.name}`} plain onClick={() => startEdit(crime)} />
                    <IconButton name="close" label={`Delete ${crime.name}`} plain onClick={() => remove(crime)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
