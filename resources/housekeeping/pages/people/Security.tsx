import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCrumbs } from '../../Shell';
import { useSession } from '@hk/lib/session';
import { useFetch } from '@hk/lib/useFetch';
import { post, qs } from '@hk/lib/api';
import { fmtNumber, relTime } from '@hk/lib/format';
import { Btn, Card, Empty, Field, Icon, Kind, Loadable, Pill, PlayerChip, PlayerRef, Seg, Stat, Tone, Wp } from '@hk/ui';

type Show = 'review' | 'all' | 'household';
interface Group {
  key: string;
  names: string;
  via: string;
  status: 'flagged' | 'household' | 'dismissed';
  banned: number;
  last_seen: number;
  accounts: { player: PlayerRef | null; created: number | null; last_online: number | null; state: 'Banned' | 'Review' | 'New' | 'Clean' }[];
}
interface Data {
  stats: { open_flags: number; oldest_flag: number | null; groups: number; groups_banned: number; first_logins: number; first_known_ip: number; failed_hour: number };
  groups: Group[];
  flags: { id: number; player: PlayerRef | null; reason: string; detail: string; at: number }[];
}

const STATUS: Record<Group['status'], { label: string; tone: Tone }> = { flagged: { label: 'Flagged', tone: 'warn' }, household: { label: 'Household', tone: 'ok' }, dismissed: { label: 'Dismissed', tone: '' } };
const STATE: Record<string, Tone> = { Banned: 'bad', Review: 'warn', New: 'info', Clean: 'ok' };

export default function Security() {
  useCrumbs([{ label: 'People', to: '/players' }, { label: 'Security' }]);
  const { toast } = useSession();
  const navigate = useNavigate();
  const [show, setShow] = useState<Show>('review');
  const [lookup, setLookup] = useState('');
  const [sel, setSel] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const state = useFetch<Data>(`/security${qs({ show })}`);
  const d = state.data;
  const groups = d?.groups ?? [];
  const cur = groups.find((g) => g.key === sel) ?? groups[0] ?? null;
  const s = d?.stats;

  const review = async (status: 'household' | 'dismissed' | 'flagged') => {
    if (!cur) return;
    setBusy(true);
    try {
      const r = await post<{ message: string }>('/security/review', { accounts: cur.accounts.map((a) => a.player?.id).filter(Boolean), status, note });
      toast('ok', r.message);
      setNote('');
      setSel(null);
      state.reload();
    } catch (e) {
      toast('bad', 'Could not save', e instanceof Error ? e.message : undefined);
    } finally {
      setBusy(false);
    }
  };
  const firstToBan = cur?.accounts.find((a) => a.state !== 'Banned' && a.player)?.player ?? cur?.accounts[0]?.player ?? null;

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Security</h1>
          <p className="sub">Browser-guard flags and accounts linked by IP or device · edit_user and up</p>
        </div>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (lookup.trim()) navigate(`/players?q=${encodeURIComponent(lookup.trim())}`);
          }}
          style={{ flex: '0 1 300px' }}
        >
          <label className="input">
            <Icon name="search" />
            <input value={lookup} onChange={(e) => setLookup(e.target.value)} placeholder="Look up an IP or device ID" aria-label="Look up an IP or device ID" />
          </label>
        </form>
      </div>
      <section className="card stats">
        <Stat dot="warn" label="Open flags" value={s ? s.open_flags : '—'} sub={s?.oldest_flag ? `Oldest ${relTime(s.oldest_flag)}` : 'Browser guard, last 7 days'} />
        <Stat label="Linked groups" value={s ? fmtNumber(s.groups) : '—'} sub={s ? `${s.groups_banned} include a banned account` : undefined} />
        <Stat label="First logins today" value={s ? s.first_logins : '—'} sub={s ? `${s.first_known_ip} from an IP seen before` : undefined} />
        <Stat label="Failed logins, 1 hour" value={s ? s.failed_hour : '—'} sub={s ? (s.failed_hour > 20 ? 'Higher than usual' : 'Normal') : undefined} />
      </section>
      <div className="split">
        <section className="card main-col">
          <div className="card-h">
            <div>
              <h3>Linked groups</h3>
              <p className="sub">Accounts that share a public IP or a device in the last 30 days</p>
            </div>
            <Seg
              label="Show"
              value={show}
              onChange={(v) => {
                setShow(v);
                setSel(null);
              }}
              options={[
                { value: 'review', label: 'Needs review' },
                { value: 'all', label: 'All' },
                { value: 'household', label: 'Households' },
              ]}
            />
          </div>
          <Loadable state={state} rows={6} empty={(x) => (x.groups.length === 0 ? <Empty title={show === 'review' ? 'Nothing to review' : 'No linked groups'} body="Groups form when two accounts play from the same public IP or device." /> : false)}>
            {(x) =>
              x.groups.map((g) => (
                <button key={g.key} type="button" className={`row ${cur?.key === g.key ? 'on' : ''}`} aria-pressed={cur?.key === g.key} onClick={() => setSel(g.key)}>
                  <Kind icon={g.banned ? 'ban' : 'people'} tone={STATUS[g.status].tone} />
                  <span className="grow" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                    <span style={{ fontWeight: 500 }}>{g.names}</span>
                    <span className="sub">{g.via}</span>
                  </span>
                  {g.banned ? <Pill tone="bad">{g.banned} banned</Pill> : null}
                  <Pill tone={STATUS[g.status].tone}>{STATUS[g.status].label}</Pill>
                  <span className="mono">{relTime(g.last_seen)}</span>
                </button>
              ))
            }
          </Loadable>
        </section>
        <div className="side-col stack">
          {cur ? (
            <aside className="peek" aria-label="Group detail">
              <div className="card-h">
                <div>
                  <h3>{cur.names}</h3>
                  <p className="sub">{cur.via}</p>
                </div>
              </div>
              {cur.accounts.map((a, i) => (
                <div key={a.player?.id ?? i} className="row">
                  <PlayerChip player={a.player} />
                  <span className="grow sub ell">
                    {a.last_online ? `Last online ${relTime(a.last_online)}` : ''}
                    {a.created ? ` · since ${new Date(a.created * 1000).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}` : ''}
                  </span>
                  <Pill tone={STATE[a.state]}>{a.state}</Pill>
                </div>
              ))}
              <div className="card-b stack16" style={{ gap: 10, borderTop: '1px solid var(--line)' }}>
                <Field label="Note" optional="required">
                  <textarea className="input" rows={2} value={note} onChange={(e) => setNote(e.target.value)} maxLength={255} placeholder="Why you marked it this way" />
                </Field>
                <div className="actions" style={{ justifyContent: 'flex-end' }}>
                  {cur.status !== 'flagged' ? (
                    <Btn disabled={busy || note.trim().length < 3} onClick={() => review('flagged')}>
                      Flag again
                    </Btn>
                  ) : null}
                  {cur.status !== 'household' ? (
                    <Btn disabled={busy || note.trim().length < 3} onClick={() => review('household')}>
                      Mark as household
                    </Btn>
                  ) : null}
                  {cur.status !== 'dismissed' ? (
                    <Btn disabled={busy || note.trim().length < 3} onClick={() => review('dismissed')}>
                      Dismiss
                    </Btn>
                  ) : null}
                  {firstToBan ? (
                    <Btn variant="danger" to={`/players/${firstToBan.id}/discipline#ban`}>
                      Ban linked accounts
                    </Btn>
                  ) : null}
                </div>
                <span className="sub" style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12 }}>
                  <Wp path="saved" />
                  Decisions are logged with your note
                </span>
              </div>
            </aside>
          ) : null}
          <Card title="Browser-guard flags" sub="Last 7 days · rp_browser_guard_log">
            <Loadable state={state} empty={(x) => (x.flags.length === 0 ? <Empty title="No flags this week" /> : false)}>
              {(x) =>
                x.flags.slice(0, 10).map((f) => (
                  <div key={f.id} className="row">
                    <PlayerChip player={f.player} />
                    <span className="grow sub ell" title={f.detail}>
                      {f.reason}
                      {f.detail ? ` · ${f.detail}` : ''}
                    </span>
                    <span className="mono">{relTime(f.at)}</span>
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
