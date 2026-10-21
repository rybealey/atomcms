import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { get, qs } from '@hk/lib/api';
import { useSession } from '@hk/lib/session';
import { visibleNav } from '@hk/lib/nav';
import { Avatar, Icon, useDebounced } from '@hk/ui';

interface SearchResult {
  players: { id: number; username: string; look: string; online: boolean }[];
  rooms: { id: number; name: string; owner: string }[];
  corporations: { id: number; name: string; police: boolean }[];
  gangs: { id: number; name: string }[];
}

interface Option {
  key: string;
  group: string;
  label: React.ReactNode;
  meta?: React.ReactNode;
  go: string;
}

/**
 * Cmd-K or `/` from anywhere: jump to a player, room, corporation or gang by
 * name or ID, open a screen, or start a player action (the action opens on
 * the player's page, where its dialog asks for the reason).
 */
export default function CommandPalette({ initial, onClose }: { initial: string; onClose: () => void }) {
  const { me, can } = useSession();
  const navigate = useNavigate();
  const [query, setQuery] = useState(initial);
  const [result, setResult] = useState<SearchResult | null>(null);
  const [active, setActive] = useState(0);
  const q = useDebounced(query.trim(), 150);
  const listRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (q.length < 2 && !/^\d+$/.test(q)) {
      setResult(null);
      return;
    }
    let live = true;
    get<SearchResult>(`/search${qs({ q })}`)
      .then((r) => live && setResult(r))
      .catch(() => live && setResult(null));
    return () => {
      live = false;
    };
  }, [q]);

  const options = useMemo<Option[]>(() => {
    const out: Option[] = [];
    const lower = query.trim().toLowerCase();
    for (const p of result?.players ?? [])
      out.push({
        key: `p${p.id}`,
        group: 'Players',
        label: (
          <>
            <Avatar look={p.look} />
            {p.username}
            {p.online ? <span className="dot ok" /> : null}
          </>
        ),
        meta: <span className="mono">#{p.id}</span>,
        go: `/players/${p.id}`,
      });
    for (const r of result?.rooms ?? []) out.push({ key: `r${r.id}`, group: 'Rooms', label: r.name, meta: <span className="mono">room {r.id}</span>, go: `/city/rooms?room=${r.id}` });
    for (const c of result?.corporations ?? [])
      out.push({ key: `c${c.id}`, group: 'Corporations', label: <><span className={`dot ${c.police ? 'police' : 'corp'}`} />{c.name}</>, go: `/city/corporations/${c.id}` });
    for (const g of result?.gangs ?? []) out.push({ key: `g${g.id}`, group: 'Gangs', label: <><span className="dot gang" />{g.name}</>, go: `/city/gangs/${g.id}` });
    const first = result?.players[0];
    if (first) {
      if (can('manage_bans')) out.push({ key: 'a-ban', group: 'Actions', label: `Ban ${first.username}`, meta: <kbd>B</kbd>, go: `/players/${first.id}/discipline?act=ban` });
      out.push({ key: 'a-alert', group: 'Actions', label: `Alert ${first.username}`, go: `/players/${first.id}?act=alert` });
      if (can('view_activity_logs')) out.push({ key: 'a-logs', group: 'Actions', label: `Open ${first.username} in the log explorer`, meta: <kbd>L</kbd>, go: `/logs?player=${first.id}` });
    }
    for (const area of visibleNav(me, can))
      for (const s of area.screens)
        if (!lower || s.label.toLowerCase().includes(lower) || area.label.toLowerCase().includes(lower))
          out.push({ key: `s${s.id}`, group: 'Screens', label: s.label, meta: <span className="sub">{area.label}</span>, go: s.to });
    return out;
  }, [result, query, me, can]);

  useEffect(() => setActive(0), [options.length]);
  useEffect(() => {
    listRef.current?.querySelector<HTMLElement>(`[data-i="${active}"]`)?.scrollIntoView({ block: 'nearest' });
  }, [active]);

  const go = (o: Option | undefined) => {
    if (!o) return;
    onClose();
    navigate(o.go);
  };

  const onKey = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') onClose();
    else if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActive((a) => Math.min(options.length - 1, a + 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive((a) => Math.max(0, a - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      go(options[active]);
    }
  };

  let lastGroup = '';
  return (
    <>
      <div className="scrim" onClick={onClose} />
      <div className="palette-wrap" onClick={onClose}>
        <div className="palette" role="dialog" aria-modal="true" aria-label="Command palette" onClick={(e) => e.stopPropagation()} onKeyDown={onKey}>
          <label>
            <Icon name="search" />
            <input
              autoFocus
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search players, rooms, corporations or actions"
              aria-label="Command palette"
              role="combobox"
              aria-expanded="true"
              aria-controls="hk-palette-list"
              aria-activedescendant={options[active] ? `hk-opt-${options[active].key}` : undefined}
            />
            <kbd>esc</kbd>
          </label>
          <div className="list" id="hk-palette-list" role="listbox" ref={listRef}>
            {options.length === 0 ? <p className="sub" style={{ padding: '10px 10px' }}>Nothing matches “{query}”.</p> : null}
            {options.map((o, i) => {
              const head = o.group !== lastGroup ? <span className="overline">{o.group}</span> : null;
              lastGroup = o.group;
              return (
                <React.Fragment key={o.key}>
                  {head}
                  <button type="button" id={`hk-opt-${o.key}`} role="option" aria-selected={i === active} data-i={i} className={`opt ${i === active ? 'on' : ''}`} onMouseMove={() => setActive(i)} onClick={() => go(o)}>
                    <span>{o.label}</span>
                    {o.meta}
                  </button>
                </React.Fragment>
              );
            })}
          </div>
        </div>
      </div>
    </>
  );
}
