import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { SessionProvider } from '@hk/lib/session';
import Shell from './Shell';
import Dashboard from './pages/Dashboard';
import Players from './pages/Players';
import PlayerDetail from './pages/PlayerDetail';
import Settings from './pages/Settings';
import Staff from './pages/Staff';
import Badges from './pages/Badges';
import Corporations from './pages/Corporations';
import CorporationDetail from './pages/CorporationDetail';
import Gangs from './pages/Gangs';
import GangDetail from './pages/GangDetail';
import Legacy from './pages/Legacy';

export default function App() {
  return (
    <BrowserRouter basename="/housekeeping">
      <SessionProvider>
        <Routes>
          <Route element={<Shell />}>
            <Route index element={<Dashboard />} />
            <Route path="players" element={<Players />} />
            <Route path="players/:id" element={<PlayerDetail />} />
            <Route path="settings" element={<Settings />} />
            <Route path="settings/:group" element={<Settings />} />
            <Route path="staff" element={<Staff />} />
            <Route path="badges" element={<Badges />} />
            <Route path="roleplay/corporations" element={<Corporations />} />
            <Route path="roleplay/corporations/:id" element={<CorporationDetail />} />
            <Route path="roleplay/gangs" element={<Gangs />} />
            <Route path="roleplay/gangs/:id" element={<GangDetail />} />
            <Route path="bans" element={<Legacy id="bans" />} />
            <Route path="chatlogs" element={<Legacy id="chatlogs" />} />
            <Route path="articles" element={<Legacy id="articles" />} />
            <Route path="photos" element={<Legacy id="photos" />} />
            <Route path="tags" element={<Legacy id="tags" />} />
            <Route path="teams" element={<Legacy id="teams" />} />
            <Route path="home-items" element={<Legacy id="homeitems" />} />
            <Route path="achievements" element={<Legacy id="achievements" />} />
            <Route path="catalog" element={<Legacy id="catalog" />} />
            <Route path="wordfilter" element={<Legacy id="wordfilter" />} />
            <Route path="ads" element={<Legacy id="ads" />} />
            <Route path="shop/packages" element={<Legacy id="packages" />} />
            <Route path="shop/items" element={<Legacy id="items" />} />
            <Route path="shop/categories" element={<Legacy id="categories" />} />
            <Route path="positions" element={<Legacy id="positions" />} />
            <Route path="applications" element={<Legacy id="applications" />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Route>
        </Routes>
      </SessionProvider>
    </BrowserRouter>
  );
}
