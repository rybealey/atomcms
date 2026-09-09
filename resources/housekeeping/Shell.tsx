import React, { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useSession } from '@hk/lib/session';
import { visibleNav } from '@hk/lib/nav';
import { Avatar, Icon, IconButton, SearchBox } from '@hk/ui';

function useIsMobile(): boolean {
  const [mobile, setMobile] = useState(() => window.innerWidth < 768);
  useEffect(() => {
    const onResize = () => setMobile(window.innerWidth < 768);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);
  return mobile;
}

export interface Crumb { label: string; to?: string }
const CrumbContext = React.createContext<(crumbs: Crumb[]) => void>(() => undefined);
export function useCrumbs(crumbs: Crumb[]) {
  const set = React.useContext(CrumbContext);
  const key = JSON.stringify(crumbs);
  useEffect(() => {
    set(crumbs);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, set]);
}

function NavList({ onNavigate, expanded }: { onNavigate?: () => void; expanded: boolean }) {
  const { me, can } = useSession();
  const groups = visibleNav(me, can);
  return (
    <>
      {groups.map((g, gi) => (
        <div key={gi} className="hk-nav__group">
          {g.label && expanded ? <div className="hk-nav__label">{g.label}</div> : null}
          {g.items.map((item) => {
            const count = item.count ? item.count(me) : 0;
            return (
              <NavLink key={item.id} to={item.to} end={item.to === '/'} className="hk-nav__item" title={item.label} onClick={onNavigate}>
                <Icon name={item.icon} size={20} />
                {expanded ? (
                  <>
                    <span className="label">{item.label}</span>
                    {count > 0 ? <span className="hk-count">{count}</span> : null}
                  </>
                ) : null}
              </NavLink>
            );
          })}
        </div>
      ))}
    </>
  );
}

export default function Shell() {
  const { me, can, theme, toggleTheme } = useSession();
  const mobile = useIsMobile();
  const navigate = useNavigate();
  const location = useLocation();
  const [drawer, setDrawer] = useState(false);
  const [userMenu, setUserMenu] = useState(false);
  const [crumbs, setCrumbs] = useState<Crumb[]>([{ label: 'Home' }]);
  const [quick, setQuick] = useState('');

  useEffect(() => {
    setDrawer(false);
    setUserMenu(false);
    window.scrollTo(0, 0);
  }, [location.pathname]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === '/' && !(e.target instanceof HTMLInputElement) && !(e.target instanceof HTMLTextAreaElement)) {
        e.preventDefault();
        document.querySelector<HTMLInputElement>('#hk-quick input')?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const settingsActive = location.pathname.startsWith('/settings') || location.pathname.startsWith('/staff');
  const showSettings = can('manage_website_settings') || can('manage_housekeeping_permissions');

  const submitQuick = (e: React.FormEvent) => {
    e.preventDefault();
    const q = quick.trim();
    if (!q) return;
    navigate(`/players?q=${encodeURIComponent(q)}`);
    setQuick('');
  };

  return (
    <CrumbContext.Provider value={setCrumbs}>
      <div className="hk-frame">
        {!mobile ? (
          <aside aria-label="Main navigation" className="hk-side">
            <div className="hk-brand">
              <img src="/favicon-32x32.png" alt="PixelRP" className="hk-brand__mark px-art" />
              <div style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
                <span className="hk-brand__name">PIXELRP</span>
                <span className="hk-brand__sub">Housekeeping</span>
              </div>
            </div>
            <nav className="hk-nav">
              <NavList expanded />
            </nav>
            <div className="hk-side__foot">
              {showSettings ? (
                <NavLink to="/settings" className="hk-nav__item" aria-current={settingsActive ? 'page' : undefined} title="Settings">
                  <Icon name="sliders" size={20} />
                  <span className="label">Settings</span>
                </NavLink>
              ) : null}
              <button type="button" className="hk-user" onClick={() => setUserMenu((o) => !o)} title={`${me.user.username} · ${me.user.rank_name}`}>
                <Avatar look={me.user.look} imager={me.config.imager} size="xs" />
                <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.2, flex: 1, minWidth: 0 }}>
                  <span style={{ fontWeight: 600 }}>{me.user.username}</span>
                  <span className="small muted">{me.user.rank_name}</span>
                </span>
                <Icon name="more-horizontal" size={16} />
              </button>
            </div>
          </aside>
        ) : null}

        <main className="hk-main">
          <a href="#hk-content" className="hk-skip">
            Skip to Content
          </a>
          <header className="hk-header">
            {mobile ? <IconButton name="menu" label="Open navigation" plain onClick={() => setDrawer(true)} style={{ marginLeft: -12 }} /> : null}
            <nav aria-label="Breadcrumb" className="hk-crumbs">
              {crumbs.map((c, i) => (
                <span key={i} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  {i > 0 ? <span aria-hidden="true">›</span> : null}
                  <span className={i === crumbs.length - 1 ? 'cur' : ''}>{c.label}</span>
                </span>
              ))}
            </nav>
            {!mobile ? (
              <form id="hk-quick" onSubmit={submitQuick} style={{ display: 'contents' }}>
                <SearchBox value={quick} onChange={setQuick} placeholder="Find a player" width={300} kbd="/" />
              </form>
            ) : null}
            <IconButton name={theme === 'dark' ? 'sun' : 'moon'} label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'} onClick={toggleTheme} />
          </header>

          <div id="hk-content" tabIndex={-1} className="hk-content">
            <Outlet />
          </div>

          <footer className="hk-footer">
            <span>PixelRP Housekeeping</span>
            <span>·</span>
            <span>Signed in as {me.user.username}</span>
            <span>·</span>
            <a href={me.config.legacy_url} target="_blank" rel="noreferrer">
              Classic housekeeping
            </a>
          </footer>
        </main>

        {drawer ? (
          <>
            <div className="hk-scrim hk-scrim--dark" onClick={() => setDrawer(false)} />
            <aside aria-label="Navigation" className="hk-drawer">
              <div className="hk-brand" style={{ justifyContent: 'space-between' }}>
                <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <img src="/favicon-32x32.png" alt="" className="hk-brand__mark px-art" />
                  <span style={{ fontWeight: 600, fontSize: 13 }}>Housekeeping</span>
                </span>
                <IconButton name="close" label="Close navigation" plain onClick={() => setDrawer(false)} />
              </div>
              <nav className="hk-nav">
                <NavList expanded onNavigate={() => setDrawer(false)} />
                {showSettings ? (
                  <NavLink to="/settings" className="hk-nav__item" style={{ borderTop: '2px solid var(--line)' }} onClick={() => setDrawer(false)}>
                    <Icon name="sliders" size={20} />
                    <span className="label">Settings</span>
                  </NavLink>
                ) : null}
              </nav>
            </aside>
          </>
        ) : null}

        {userMenu ? (
          <>
            <div className="hk-scrim" onClick={() => setUserMenu(false)} />
            <div role="menu" className="hk-menu hk-menu--user" style={{ left: 272 }}>
              <div className="hk-menu__head">
                <strong>{me.user.username}</strong>
                <div className="small muted">
                  {me.user.rank_name} · rank {me.user.rank}
                </div>
              </div>
              <button type="button" role="menuitem" onClick={() => navigate(`/players/${me.user.id}`)}>
                My player card
              </button>
              <a role="menuitem" href="/user/me">
                Back to the site
              </a>
              <a role="menuitem" href={me.config.legacy_url} target="_blank" rel="noreferrer">
                Classic housekeeping
              </a>
              <div className="hk-menu__foot">
                {me.config.hotel_name} · {me.config.emulator} emulator
              </div>
              <form method="post" action="/logout">
                <input type="hidden" name="_token" value={document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''} />
                <button type="submit" role="menuitem" className="danger" style={{ width: '100%', textAlign: 'left' }}>
                  Sign out
                </button>
              </form>
            </div>
          </>
        ) : null}
      </div>
    </CrumbContext.Provider>
  );
}
