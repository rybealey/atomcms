import React, { Suspense, lazy } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { SessionProvider, useSession } from '@hk/lib/session';
import { screenAllowed, screenOf } from '@hk/lib/nav';
import { Note, Skeleton } from '@hk/ui';
import Shell from './Shell';

// One chunk per screen: a moderator opening Today never downloads the catalog editor.
const Today = lazy(() => import('./pages/Today'));
const Players = lazy(() => import('./pages/people/Players'));
const Player360 = lazy(() => import('./pages/people/Player360'));
const Discipline = lazy(() => import('./pages/people/Discipline'));
const Security = lazy(() => import('./pages/people/Security'));
const Support = lazy(() => import('./pages/moderation/Support'));
const Tickets = lazy(() => import('./pages/moderation/Tickets'));
const ChatLogs = lazy(() => import('./pages/moderation/ChatLogs'));
const Sitch = lazy(() => import('./pages/moderation/Sitch'));
const Justice = lazy(() => import('./pages/city/Justice'));
const Corporations = lazy(() => import('./pages/city/Corporations'));
const Corporation = lazy(() => import('./pages/city/Corporation'));
const Gangs = lazy(() => import('./pages/city/Gangs'));
const Gang = lazy(() => import('./pages/city/Gang'));
const Economy = lazy(() => import('./pages/city/Economy'));
const Rooms = lazy(() => import('./pages/city/Rooms'));
const Badges = lazy(() => import('./pages/content/Badges'));
const Catalog = lazy(() => import('./pages/content/Catalog'));
const Achievements = lazy(() => import('./pages/content/Achievements'));
const WordFilter = lazy(() => import('./pages/content/WordFilter'));
const PhoneNews = lazy(() => import('./pages/content/PhoneNews'));
const Website = lazy(() => import('./pages/content/Website'));
const LogExplorer = lazy(() => import('./pages/logs/LogExplorer'));
const SavedViews = lazy(() => import('./pages/logs/SavedViews'));
const StaffAudit = lazy(() => import('./pages/logs/StaffAudit'));
const Access = lazy(() => import('./pages/admin/Access'));
const Staff = lazy(() => import('./pages/admin/Staff'));
const WebsiteSettings = lazy(() => import('./pages/admin/WebsiteSettings'));
const EmulatorSettings = lazy(() => import('./pages/admin/EmulatorSettings'));

/** A screen the signed-in rank may not open says so instead of failing its API calls. */
function Gate({ children }: { children: React.ReactNode }) {
  const { me, can, rankName } = useSession();
  const { pathname } = useLocation();
  const screen = screenOf(pathname);
  if (screen && !screenAllowed(screen, me, can)) {
    const needs = screen.permissions?.[0];
    return (
      <main className="page">
        <Note icon="lock" title={`You can’t open ${screen.label} with your rank`}>
          {needs ? `It needs the ${needs} capability, which starts above ${rankName(me.user.rank)}. Ask an owner on the Access screen.` : 'It is turned off for this hotel.'}
        </Note>
      </main>
    );
  }
  return <>{children}</>;
}

function Loading() {
  return (
    <main className="page">
      <div className="card">
        <Skeleton rows={6} />
      </div>
    </main>
  );
}

export default function App() {
  return (
    <BrowserRouter basename="/housekeeping">
      <SessionProvider>
        <Routes>
          <Route
            element={<Shell />}
          >
            <Route
              path="*"
              element={
                <Suspense fallback={<Loading />}>
                  <Gate>
                    <Routes>
                      <Route index element={<Today />} />
                      <Route path="players" element={<Players />} />
                      <Route path="players/:id" element={<Player360 />} />
                      <Route path="players/:id/:tab" element={<Player360 />} />
                      <Route path="people/discipline" element={<Discipline />} />
                      <Route path="people/security" element={<Security />} />
                      <Route path="moderation/support" element={<Support />} />
                      <Route path="moderation/tickets" element={<Tickets />} />
                      <Route path="moderation/chat" element={<ChatLogs />} />
                      <Route path="moderation/sitch" element={<Sitch />} />
                      <Route path="city/justice" element={<Justice />} />
                      <Route path="city/corporations" element={<Corporations />} />
                      <Route path="city/corporations/:id" element={<Corporation />} />
                      <Route path="city/gangs" element={<Gangs />} />
                      <Route path="city/gangs/:id" element={<Gang />} />
                      <Route path="city/economy" element={<Economy />} />
                      <Route path="city/rooms" element={<Rooms />} />
                      <Route path="content/badges" element={<Badges />} />
                      <Route path="content/catalog" element={<Catalog />} />
                      <Route path="content/achievements" element={<Achievements />} />
                      <Route path="content/wordfilter" element={<WordFilter />} />
                      <Route path="content/news" element={<PhoneNews />} />
                      <Route path="content/website" element={<Website />} />
                      <Route path="logs" element={<LogExplorer />} />
                      <Route path="logs/views" element={<SavedViews />} />
                      <Route path="logs/audit" element={<StaffAudit />} />
                      <Route path="admin/access" element={<Access />} />
                      <Route path="admin/staff" element={<Staff />} />
                      <Route path="admin/website" element={<WebsiteSettings />} />
                      <Route path="admin/website/:group" element={<WebsiteSettings />} />
                      <Route path="admin/emulator" element={<EmulatorSettings />} />
                      <Route path="*" element={<Navigate to="/" replace />} />
                    </Routes>
                  </Gate>
                </Suspense>
              }
            />
          </Route>
        </Routes>
      </SessionProvider>
    </BrowserRouter>
  );
}
