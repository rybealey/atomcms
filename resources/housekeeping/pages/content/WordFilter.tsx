import { useMemo, useState } from 'react';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { del, post, put, qs } from '@hk/lib/api';
import { fmtNumber } from '@hk/lib/format';
import { Btn, Empty, Field, Loadable, Pill, SearchInput, useDebounced, Wp } from '@hk/ui';
import { ListEditor, useSave } from './shared';

interface Entry { word: string; replacement: string; anywhere: boolean; bannable: boolean; by: string; hits: number | null }

/**
 * What a player would see, the way WordFilterManager does it: an "anywhere"
 * entry is replaced inside words too; a whole-word entry only where it
 * stands alone between spaces.
 */
function filtered(message: string, e: { word: string; replacement: string; anywhere: boolean }): string {
  const word = e.word.toLowerCase();
  if (!word) return message;
  if (e.anywhere) return message.replace(new RegExp(word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), e.replacement);
  return message
    .split(' ')
    .map((w) => (w.toLowerCase() === word ? e.replacement : w))
    .join(' ');
}

export default function WordFilter() {
  useCrumbs([{ label: 'Content', to: '/content/badges' }, { label: 'Word filter' }]);
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 300);
  const state = useFetch<{ total: number; items: Entry[] }>(`/wordfilter${qs({ q })}`);
  const [sel, setSel] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const cur = adding ? null : state.data?.items.find((w) => w.word === sel) ?? state.data?.items[0] ?? null;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Word filter</h1>
          <p className="sub">{state.data ? `${fmtNumber(state.data.total)} entries` : 'Loading'} · wordfilter · hits counted from room chat over 7 days</p>
        </div>
        <div className="actions">
          <SearchInput value={text} onChange={setText} placeholder="Search entries" width={220} />
          <Btn variant="primary" onClick={() => setAdding(true)}>
            Add entry
          </Btn>
        </div>
      </div>
      <ListEditor
        list={
          <section className="card">
            <Loadable state={state} rows={8} empty={(d) => (d.items.length === 0 ? <Empty title={q ? 'No entries match' : 'The filter is empty'} /> : false)}>
              {(d) => (
                <div className="tw">
                  <table className="t">
                    <thead>
                      <tr>
                        <th>Entry</th>
                        <th>Replaced with</th>
                        <th>Match</th>
                        <th>Bannable</th>
                        <th className="r">Hits, 7 days</th>
                        <th>Added by</th>
                      </tr>
                    </thead>
                    <tbody>
                      {d.items.map((w) => (
                        <tr key={w.word} className={`click ${cur?.word === w.word ? 'sel' : ''}`} onClick={() => { setAdding(false); setSel(w.word); }}>
                          <td className="mono" style={{ color: 'var(--ink)', fontSize: 13 }}>
                            {w.word}
                          </td>
                          <td className="mono">{w.replacement}</td>
                          <td>
                            <Pill>{w.anywhere ? 'Anywhere' : 'Whole word'}</Pill>
                          </td>
                          <td>{w.bannable ? <Pill tone="bad">Bannable</Pill> : null}</td>
                          <td className="r num">{w.hits === null ? '—' : fmtNumber(w.hits)}</td>
                          <td style={{ color: 'var(--muted)' }}>{w.by || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Loadable>
          </section>
        }
        editor={
          state.data && (cur || adding) ? (
            <Editor
              key={cur?.word ?? 'new'}
              entry={cur}
              onDone={(word) => {
                setAdding(false);
                setSel(word);
                state.reload();
              }}
            />
          ) : null
        }
      />
    </main>
  );
}

function Editor({ entry, onDone }: { entry: Entry | null; onDone: (word: string | null) => void }) {
  const { confirm } = useSession();
  const [f, setF] = useState({ word: entry?.word ?? '', replacement: entry?.replacement ?? '****', anywhere: entry?.anywhere ?? true, bannable: entry?.bannable ?? false });
  const [test, setTest] = useState(entry ? `this has ${entry.word} in it` : '');
  const { busy, run } = useSave();
  const preview = useMemo(() => filtered(test, f), [test, f]);
  const valid = f.word.trim().length >= 2 && f.replacement !== '';

  return (
    <aside className="peek side-col" aria-label={entry ? 'Edit entry' : 'New entry'} style={{ flexBasis: 380 }}>
      <div className="card-h">
        <h3>{entry ? 'Edit entry' : 'New entry'}</h3>
        <Wp path="saved" label="Saved, then reloaded" />
      </div>
      <div className="card-b stack16" style={{ gap: 14 }}>
        <div className="grid2">
          <Field label="Entry">
            <input className="input mono" value={f.word} onChange={(e) => setF({ ...f, word: e.target.value.toLowerCase() })} maxLength={100} autoFocus={!entry} />
          </Field>
          <Field label="Replace with">
            <input className="input mono" value={f.replacement} onChange={(e) => setF({ ...f, replacement: e.target.value })} maxLength={255} />
          </Field>
        </div>
        <fieldset style={{ border: 0, padding: 0, margin: 0 }} className="stack16">
          <legend style={{ fontSize: 13, fontWeight: 500, padding: 0, marginBottom: 8 }}>Match</legend>
          <label className="check">
            <input type="radio" name="match" checked={!f.anywhere} onChange={() => setF({ ...f, anywhere: false })} />
            Whole word only
          </label>
          <label className="check">
            <input type="radio" name="match" checked={f.anywhere} onChange={() => setF({ ...f, anywhere: true })} />
            Anywhere in a message
          </label>
        </fieldset>
        <label className="check">
          <input type="checkbox" checked={f.bannable} onChange={(e) => setF({ ...f, bannable: e.target.checked })} />
          Bannable: saying it in a room counts toward an automatic ban
        </label>
        <p className="sub" style={{ fontSize: 12 }}>
          The hotel keeps one filter for everything it checks, so there is no per-place setting. Chat logs keep the original line for staff.
        </p>
        <div className="stack16" style={{ gap: 8, padding: 12, borderRadius: 10, background: 'var(--sunken)' }}>
          <Field label="Try it">
            <input className="input" value={test} onChange={(e) => setTest(e.target.value)} />
          </Field>
          <p className="sub" style={{ fontSize: 12 }}>
            Players see
          </p>
          <p style={{ fontSize: 13 }}>{preview || '—'}</p>
          {preview !== test ? (
            <span style={{ alignSelf: 'flex-start' }}>
              <Pill tone="warn">Filtered</Pill>
            </span>
          ) : null}
        </div>
      </div>
      <div className="card-f" style={{ justifyContent: entry ? 'space-between' : 'flex-end' }}>
        {entry ? (
          <Btn
            variant="danger"
            disabled={busy}
            onClick={async () => {
              if (!(await confirm({ title: `Delete “${entry.word}”?`, body: 'The hotel stops filtering it as soon as it reloads the filter.', cta: 'Delete entry', danger: true }))) return;
              if (await run(() => del('/wordfilter', { word: entry.word }))) onDone(null);
            }}
          >
            Delete
          </Btn>
        ) : null}
        <span className="actions">
          <Btn
            variant="primary"
            disabled={busy || !valid}
            onClick={async () => {
              const body = { ...f, word: f.word.trim() };
              if (await run(() => (entry ? put('/wordfilter', { ...body, original: entry.word }) : post('/wordfilter', body)))) onDone(body.word);
            }}
          >
            {entry ? 'Save entry' : 'Add entry'}
          </Btn>
        </span>
      </div>
    </aside>
  );
}
