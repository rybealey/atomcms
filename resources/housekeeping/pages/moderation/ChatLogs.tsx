import React, { useState } from 'react';
import { useCrumbs } from '../../Shell';
import { useFetch } from '@hk/lib/useFetch';
import { qs } from '@hk/lib/api';
import { fmtClock, fmtWhen } from '@hk/lib/format';
import { Btn, Empty, FilterSelect, Icon, Loadable, Pill, PlayerRef, SearchInput, Seg, useDebounced } from '@hk/ui';
import { PlayerPicker } from '../logs/LogExplorer';

interface Hit { key: string; kind: 'room' | 'private'; at: number; speaker: PlayerRef; other: PlayerRef | null; room: { id: number; name: string } | null; text: string }
interface Context { title: string; source: string; room: number | null; lines: { id: number; at: number; player: PlayerRef | null; text: string; hit: boolean }[] }

const WHEN = [
  { value: '1h', label: 'Last hour' },
  { value: '24h', label: 'Last 24 hours' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
  { value: 'all', label: 'All time' },
];

/** The message with every match of the search term marked. */
export function Highlight({ text, term }: { text: string; term: string }) {
  if (!term) return <>{text}</>;
  const parts = text.split(new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig'));
  return (
    <>
      {parts.map((p, i) =>
        p.toLowerCase() === term.toLowerCase() ? (
          <mark key={i} style={{ background: 'var(--warn-soft)', color: 'var(--warn-ink)', padding: '0 2px', borderRadius: 3 }}>
            {p}
          </mark>
        ) : (
          <React.Fragment key={i}>{p}</React.Fragment>
        ),
      )}
    </>
  );
}

function where(h: Hit): string {
  if (h.kind === 'room') return h.room?.name ?? 'Room';
  return `Private · ${h.speaker.username} to ${h.other?.username ?? 'unknown'}`;
}

export default function ChatLogs() {
  useCrumbs([{ label: 'Moderation', to: '/moderation/support' }, { label: 'Chat logs' }]);
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 350);
  const [type, setType] = useState<'all' | 'room' | 'private'>('all');
  const [player, setPlayer] = useState<number | null>(null);
  const [room, setRoom] = useState('');
  const [when, setWhen] = useState('24h');
  const [filtered, setFiltered] = useState(false);
  const [sel, setSel] = useState<string | null>(null);
  const hits = useFetch<{ hits: Hit[]; capped: boolean; can: { room: boolean; private: boolean } }>(`/chatlogs${qs({ q, type, player, room, when, filtered })}`);
  const list = hits.data?.hits ?? [];
  const current = list.find((h) => h.key === sel) ?? list[0] ?? null;
  const context = useFetch<Context>(current ? `/chatlogs${qs({ context: current.key })}` : null);
  const playerName = useFetch<{ username: string }>(player ? `/players/${player}/card` : null);
  const can = hits.data?.can;

  const exportCsv = () => {
    const rows = [['time', 'where', 'speaker', 'message'], ...list.map((h) => [new Date(h.at * 1000).toISOString(), where(h), h.speaker.username, h.text])];
    const blob = new Blob([rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `chat-logs-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  };

  return (
    <main className="page">
      <div className="ph">
        <div>
          <h1>Chat logs</h1>
          <p className="sub">Room chat and private chat in one search · chatlogs and chatlogs_console</p>
        </div>
      </div>
      <div className="filters">
        <SearchInput value={text} onChange={setText} placeholder="Search messages" width="min(100%, 300px)" />
        {can?.room && can?.private ? (
          <Seg label="Chat type" value={type} onChange={setType} options={[{ value: 'all', label: 'All' }, { value: 'room', label: 'Room' }, { value: 'private', label: 'Private' }]} />
        ) : null}
        {player ? (
          <span className="select on">
            <span className="k">Player</span> {playerName.data?.username ?? `#${player}`}
            <button type="button" className="btn quiet sm icon" aria-label="Clear player" onClick={() => setPlayer(null)} style={{ width: 22, height: 22 }}>
              <Icon name="close" size={12} />
            </button>
          </span>
        ) : (
          <PlayerPicker onPick={setPlayer} />
        )}
        <label className={`select ${room ? 'on' : ''}`}>
          <span className="k">Room</span>
          <input value={room} onChange={(e) => setRoom(e.target.value.replace(/\D/g, ''))} placeholder="Any" aria-label="Room ID" className="mono" style={{ border: 0, outline: 0, background: 'transparent', color: 'inherit', width: 64, font: 'inherit' }} />
        </label>
        <FilterSelect label="When" value={when} options={WHEN} anyLabel="Last 24 hours" onChange={(v) => setWhen(v || '24h')} />
        <label className="check">
          <input type="checkbox" checked={filtered} onChange={(e) => setFiltered(e.target.checked)} />
          Filtered words only
        </label>
      </div>
      <div className="split">
        <section className="card main-col">
          <div className="card-h">
            <span className="sub">
              {hits.data ? `${hits.data.capped ? 'The newest 100' : list.length} ${list.length === 1 ? 'message' : 'messages'}${q ? ` match “${q}”` : ''}` : 'Searching'}
            </span>
            <Btn sm onClick={exportCsv} disabled={!list.length}>
              Export
            </Btn>
          </div>
          <Loadable state={hits} rows={8} empty={(x) => (x.hits.length === 0 ? <Empty title="No messages match" body="Try a longer time range or fewer filters." /> : false)}>
            {() => (
              <div className="tw">
                <table className="t">
                  <thead>
                    <tr>
                      <th>When</th>
                      <th>Where</th>
                      <th>Speaker</th>
                      <th>Message</th>
                    </tr>
                  </thead>
                  <tbody>
                    {list.map((h) => (
                      <tr key={h.key} className={`click ${current?.key === h.key ? 'sel' : ''}`} onClick={() => setSel(h.key)}>
                        <td className="mono">{fmtWhen(h.at)}</td>
                        <td>
                          <Pill tone={h.kind === 'room' ? 'info' : 'brand'}>{where(h)}</Pill>
                        </td>
                        <td style={{ fontWeight: 500 }}>{h.speaker.username}</td>
                        <td className="wrap">
                          <button type="button" onClick={() => setSel(h.key)} style={{ border: 0, background: 'none', padding: 0, font: 'inherit', color: 'var(--ink)', textAlign: 'left', cursor: 'pointer', overflowWrap: 'anywhere' }}>
                            <Highlight text={h.text} term={q} />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Loadable>
        </section>
        {current ? (
          <aside className="peek side-col" style={{ flexBasis: 400 }} aria-label="Context">
            <div className="card-h">
              <div>
                <h3>{context.data?.title ?? where(current)}</h3>
                <p className="sub">Five lines either side · {context.data?.source ?? (current.kind === 'room' ? 'chatlogs' : 'chatlogs_console')}</p>
              </div>
              <Btn sm to={`/logs?kind=chat&player=${current.speaker.id}${current.room ? `&room=${current.room.id}` : ''}&when=7d`}>
                Open in log explorer
              </Btn>
            </div>
            <Loadable state={context} rows={6}>
              {(c) =>
                c.lines.map((m) => (
                  <div key={m.id} style={{ display: 'grid', gridTemplateColumns: '64px 96px minmax(0, 1fr)', gap: 10, padding: '7px 20px', borderBottom: '1px solid var(--line)', background: m.hit ? 'var(--brand-soft)' : 'transparent', fontSize: 13 }}>
                    <span className="mono">{fmtClock(m.at)}</span>
                    <span className="ell" style={{ fontWeight: 500 }}>
                      {m.player?.username ?? 'Unknown'}
                    </span>
                    <span style={{ overflowWrap: 'anywhere' }}>{m.text}</span>
                  </div>
                ))
              }
            </Loadable>
            <div className="card-f" style={{ justifyContent: 'flex-start', flexWrap: 'wrap' }}>
              <Btn to={`/players/${current.speaker.id}/chat`}>Open {current.speaker.username}</Btn>
              <Btn to={`/players/${current.speaker.id}/discipline#ban`}>Mute or ban</Btn>
            </div>
          </aside>
        ) : null}
      </div>
    </main>
  );
}
