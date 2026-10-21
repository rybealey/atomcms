// The rail: six areas grouped by staff job, each with its screens. An area
// links to its first screen the signed-in rank may open; a screen with
// `permissions` shows when any one of them is granted.
import type { IconName } from '@hk/ui/icons';
import type { Counts, Me } from './session';

export interface NavScreen {
  id: string;
  label: string;
  to: string;
  permissions?: string[];
  feature?: string;
  count?: (c: Counts) => number;
  countTone?: 'bad';
}
export interface NavArea {
  id: string;
  label: string;
  icon: IconName;
  screens: NavScreen[];
  count?: (c: Counts) => number;
}

export const NAV: NavArea[] = [
  { id: 'today', label: 'Today', icon: 'home', screens: [{ id: 'today', label: 'Today', to: '/' }] },
  {
    id: 'people',
    label: 'People',
    icon: 'people',
    screens: [
      { id: 'players', label: 'Players', to: '/players' },
      { id: 'discipline', label: 'Discipline', to: '/people/discipline', permissions: ['manage_bans'] },
      { id: 'security', label: 'Security', to: '/people/security', permissions: ['edit_user'], count: (c) => c.guard_flags },
    ],
  },
  {
    id: 'moderation',
    label: 'Moderation',
    icon: 'shield',
    count: (c) => c.support_waiting + c.tickets_open,
    screens: [
      { id: 'support', label: 'Support queue', to: '/moderation/support', permissions: ['manage_support'], count: (c) => c.support_waiting },
      { id: 'tickets', label: 'Tickets', to: '/moderation/tickets', permissions: ['manage_support'], count: (c) => c.tickets_open },
      { id: 'chat', label: 'Chat logs', to: '/moderation/chat', permissions: ['manage_room_chatlogs', 'manage_private_chatlogs'] },
      { id: 'sitch', label: 'Sitch and photos', to: '/moderation/sitch', permissions: ['manage_camera_web', 'moderate_sitch'] },
    ],
  },
  {
    id: 'city',
    label: 'City',
    icon: 'city',
    screens: [
      { id: 'justice', label: 'Justice', to: '/city/justice', count: (c) => c.calls_open, countTone: 'bad' },
      { id: 'corporations', label: 'Corporations', to: '/city/corporations' },
      { id: 'gangs', label: 'Gangs', to: '/city/gangs' },
      { id: 'economy', label: 'Economy', to: '/city/economy', permissions: ['view_economy'] },
      { id: 'rooms', label: 'Rooms and world', to: '/city/rooms' },
    ],
  },
  {
    id: 'content',
    label: 'Content',
    icon: 'box',
    count: (c) => c.draw_badges,
    screens: [
      { id: 'badges', label: 'Badges', to: '/content/badges', permissions: ['manage_badges'], count: (c) => c.draw_badges },
      { id: 'catalog', label: 'Catalog and furni', to: '/content/catalog', permissions: ['manage_catalog_pages'] },
      { id: 'achievements', label: 'Achievements and texts', to: '/content/achievements', permissions: ['manage_achievements', 'manage_emulator_texts'] },
      { id: 'wordfilter', label: 'Word filter', to: '/content/wordfilter', permissions: ['manage_wordfilter'] },
      { id: 'news', label: 'Phone news', to: '/content/news', permissions: ['edit_article'] },
      { id: 'website', label: 'Website', to: '/content/website', permissions: ['write_article', 'edit_article', 'manage_teams', 'manage_article_tags', 'manage_website_ads', 'manage_home_items'] },
    ],
  },
  {
    id: 'logs',
    label: 'Logs',
    icon: 'list',
    screens: [
      { id: 'logs', label: 'Log explorer', to: '/logs', permissions: ['view_activity_logs'] },
      { id: 'views', label: 'Saved views', to: '/logs/views', permissions: ['view_activity_logs'] },
      { id: 'audit', label: 'Staff audit', to: '/logs/audit', permissions: ['view_activity_logs'] },
    ],
  },
  {
    id: 'admin',
    label: 'Admin',
    icon: 'sliders',
    screens: [
      { id: 'access', label: 'Access', to: '/admin/access', permissions: ['manage_housekeeping_permissions'] },
      { id: 'staff', label: 'Staff', to: '/admin/staff' },
      { id: 'websitesettings', label: 'Website settings', to: '/admin/website', permissions: ['manage_website_settings'] },
      { id: 'emulatorsettings', label: 'Emulator settings', to: '/admin/emulator', permissions: ['manage_emulator_settings'] },
    ],
  },
];

export function screenAllowed(screen: NavScreen, me: Me, can: (p: string) => boolean): boolean {
  if (screen.feature && !me.config.features.includes(screen.feature)) return false;
  if (screen.permissions && !screen.permissions.some(can)) return false;
  return true;
}

export function visibleNav(me: Me, can: (p: string) => boolean): NavArea[] {
  return NAV.map((area) => ({ ...area, screens: area.screens.filter((s) => screenAllowed(s, me, can)) })).filter((a) => a.screens.length > 0);
}

/** The area a path belongs to, by its first segment. */
export function areaOf(pathname: string): string {
  if (pathname === '/' || pathname === '') return 'today';
  if (pathname.startsWith('/players') || pathname.startsWith('/people')) return 'people';
  const first = pathname.split('/')[1] ?? '';
  return NAV.some((a) => a.id === first) ? first : 'today';
}

export function screenOf(pathname: string): NavScreen | undefined {
  let best: NavScreen | undefined;
  for (const area of NAV)
    for (const s of area.screens)
      if (s.to === '/' ? pathname === '/' : pathname === s.to || pathname.startsWith(s.to + '/'))
        if (!best || s.to.length > best.to.length) best = s;
  return best;
}
