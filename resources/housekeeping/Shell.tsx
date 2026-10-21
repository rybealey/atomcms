import React, { useCallback, useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { useSession } from '@hk/lib/session';
import { areaOf, screenOf, visibleNav } from '@hk/lib/nav';
import { Avatar, Btn, Count, Icon, Pill } from '@hk/ui';
import CommandPalette from './CommandPalette';

export interface Crumb { label: string; to?: string }
const CrumbContext = React.createContext<(crumbs: Crumb[]) => void>(() => undefined);

/** Set the top bar's breadcrumbs (and the phone bar's title) for a page. */
export function useCrumbs(crumbs: Crumb[]) {
  const set = React.useContext(CrumbContext);
  const key = JSON.stringify(crumbs);
  useEffect(() => {
    set(crumbs);
    document.title = `${crumbs[crumbs.length - 1]?.label ?? 'Today'} · Housekeeping`;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, set]);
}

const PaletteContext = React.createContext<(query?: string) => void>(() => undefined);
export function usePalette() {
  return React.useContext(PaletteContext);
}

function Rail({ onNavigate }: { onNavigate?: () => void }) {
  const { me, can, pulse, collapsed: railCollapsed, toggleCollapsed, theme, toggleTheme, density, toggleDensity } = useSession();
  // The phone drawer always shows the full rail.
  const collapsed = railCollapsed && !onNavigate;
  const location = useLocation();
  const area = areaOf(location.pathname);
  const current = screenOf(location.pathname);
  const [menu, setMenu] = useState(false);
  const env = me.config.environment;

  return (
    <nav className="rail" aria-label="Housekeeping">
      <div className="brandbox">
        <Link to="/" onClick={onNavigate} aria-label="Today">
          {collapsed ? (
            <img src="/assets/images/pixelrp/emblem.png" width={31} height={55} alt="PixelRP" />
          ) : (
            <img src="/assets/images/pixelrp/wordmark.png" width={201} height={55} alt="PixelRP" style={{ maxWidth: '100%', height: 'auto' }} />
          )}
        </Link>
        <div className="envrow">
          <span className="overline">Housekeeping</span>
          <Pill tone={env === 'production' ? '' : 'acc'}>{env === 'production' ? 'Production' : env === 'beta' ? 'Beta' : 'Local'}</Pill>
        </div>
      </div>
      {visibleNav(me, can).map((a) => {
        const on = a.id === area;
        const n = a.count ? a.count(pulse.counts) : 0;
        return (
          <React.Fragment key={a.id}>
            <Link className={`nav-a ${on ? 'on' : ''}`} to={a.screens[0].to} onClick={onNavigate} title={collapsed ? a.label : undefined} aria-current={on && a.id === 'today' ? 'page' : undefined}>
              <Icon name={a.icon} />
              <span className="label">{a.label}</span>
              {!on ? <Count n={n} /> : null}
            </Link>
            {on && a.screens.length > 1 ? (
              <div className="nav-sub">
                {a.screens.map((s) => {
                  const sn = s.count ? s.count(pulse.counts) : 0;
                  const active = current?.id === s.id;
                  return (
                    <NavLink key={s.id} className={`nav-s ${active ? 'on' : ''}`} to={s.to} end onClick={onNavigate} aria-current={active ? 'page' : undefined}>
                      {s.label} <Count n={sn} tone={s.countTone} />
                    </NavLink>
                  );
                })}
              </div>
            ) : null}
          </React.Fragment>
        );
      })}
      <div className="railfoot rel">
        <button type="button" className="me" onClick={() => setMenu((m) => !m)} aria-haspopup="menu" aria-expanded={menu} title={collapsed ? me.user.username : undefined}>
          <Avatar look={me.user.look} size={30} />
          <span className="me-text grow">
            <span style={{ display: 'block', fontSize: 13, fontWeight: 500 }} className="ell">
              {me.user.username}
            </span>
            <span className="sub ell" style={{ display: 'block', fontSize: 12 }}>
              {me.user.rank_name}
            </span>
          </span>
        </button>
        {menu ? (
          <>
            <div className="scrim clear" onClick={() => setMenu(false)} />
            <div className="menu" role="menu" style={{ bottom: '100%', left: 0, marginBottom: 4 }}>
              <Link role="menuitem" to={`/players/${me.user.id}`} onClick={() => { setMenu(false); onNavigate?.(); }}>
                <Icon name="person" /> My player page
              </Link>
              <button type="button" role="menuitem" onClick={toggleTheme}>
                <Icon name={theme === 'dark' ? 'sun' : 'moon'} /> {theme === 'dark' ? 'Light theme' : 'Dark theme'}
              </button>
              <button type="button" role="menuitem" onClick={toggleDensity}>
                <Icon name="list" /> {density === 'compact' ? 'Comfortable rows' : 'Compact rows'}
              </button>
              <a role="menuitem" href="/">
                <Icon name="external" /> Back to the site
              </a>
              <hr />
              <form method="post" action="/logout">
                <input type="hidden" name="_token" value={document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''} />
                <button type="submit" role="menuitem" className="danger" style={{ width: '100%' }}>
                  <Icon name="logout" /> Sign out
                </button>
              </form>
            </div>
          </>
        ) : null}
        {!onNavigate ? (
          <Btn variant="quiet" sm icon={collapsed ? 'expand' : 'collapse'} onClick={toggleCollapsed} style={{ justifyContent: 'flex-start' }} aria-label={collapsed ? 'Expand menu' : 'Collapse menu'}>
            <span className="label">{collapsed ? 'Expand menu' : 'Collapse menu'}</span>
          </Btn>
        ) : null}
      </div>
    </nav>
  );
}

const TABBAR = [
  { area: 'today', label: 'Today', icon: 'home', to: '/' },
  { area: 'people', label: 'People', icon: 'people', to: '/players' },
  { area: 'moderation', label: 'Support', icon: 'message', to: '/moderation/support' },
  { area: 'city', label: 'City', icon: 'city', to: '/city/justice' },
] as const;

export default function Shell() {
  const { me, pulse, collapsed } = useSession();
  const location = useLocation();
  const [drawer, setDrawer] = useState(false);
  const [palette, setPalette] = useState<string | null>(null);
  const [crumbs, setCrumbs] = useState<Crumb[]>([{ label: 'Today' }]);
  const area = areaOf(location.pathname);

  useEffect(() => {
    setDrawer(false);
    window.scrollTo(0, 0);
  }, [location.pathname]);

  const openPalette = useCallback((query = '') => setPalette(query), []);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const typing = e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLSelectElement || (e.target instanceof HTMLElement && e.target.isContentEditable);
      if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing)) {
        e.preventDefault();
        setPalette('');
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const env = me.config.environment;
  const online = pulse.emulator.online;

  return (
    <CrumbContext.Provider value={setCrumbs}>
      <PaletteContext.Provider value={openPalette}>
        {env === 'beta' ? <div className="envbar">Beta environment · changes here never reach the live hotel</div> : null}
        <div className={`shell ${collapsed ? 'collapsed' : ''}`}>
          <Rail />
          <div className="main">
            <a href="#hk-content" className="sr-only">
              Skip to content
            </a>
            <header className="top">
              <nav className="crumbs" aria-label="Breadcrumb">
                {crumbs.map((c, i) => (
                  <React.Fragment key={i}>
                    {i > 0 ? <span aria-hidden="true">/</span> : null}
                    {i === crumbs.length - 1 ? <b>{c.label}</b> : c.to ? <Link to={c.to}>{c.label}</Link> : <span>{c.label}</span>}
                  </React.Fragment>
                ))}
              </nav>
              <button className="search" type="button" onClick={() => openPalette('')}>
                <Icon name="search" />
                <span>Search players, rooms, corporations or actions</span>
                <kbd>/</kbd>
              </button>
              <span className="status" role="status">
                <span className={`dot ${online ? 'ok' : 'bad'}`} />
                {online ? 'Emulator online' : 'Emulator offline'}
              </span>
            </header>
            <header className="phonebar">
              <button className="btn quiet icon" type="button" aria-label="Open menu" onClick={() => setDrawer(true)}>
                <Icon name="menu" />
              </button>
              <h1>{crumbs[crumbs.length - 1]?.label ?? 'Today'}</h1>
              <span className="status" style={{ fontSize: 12 }}>
                <span className={`dot ${online ? 'ok' : 'bad'}`} />
                {online ? 'Online' : 'Offline'}
              </span>
              <button className="btn quiet icon" type="button" aria-label="Search" onClick={() => openPalette('')}>
                <Icon name="search" />
              </button>
            </header>
            <div id="hk-content" tabIndex={-1} style={{ outline: 0, flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
              <Outlet />
            </div>
            <nav className="tabbar" aria-label="Sections">
              {TABBAR.map((t) => (
                <Link key={t.area} to={t.to} className={area === t.area ? 'on' : ''} aria-current={area === t.area ? 'page' : undefined}>
                  <Icon name={t.icon} />
                  {t.label}
                </Link>
              ))}
              <button type="button" className={['content', 'logs', 'admin'].includes(area) ? 'on' : ''} onClick={() => setDrawer(true)}>
                <Icon name="more" />
                More
              </button>
            </nav>
          </div>
        </div>
        {drawer ? (
          <>
            <div className="scrim" onClick={() => setDrawer(false)} />
            <div className="drawer" role="dialog" aria-modal="true" aria-label="Menu">
              <Rail onNavigate={() => setDrawer(false)} />
            </div>
          </>
        ) : null}
        {palette !== null ? <CommandPalette initial={palette} onClose={() => setPalette(null)} /> : null}
      </PaletteContext.Provider>
    </CrumbContext.Provider>
  );
}
