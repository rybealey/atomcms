import { IconName } from './icons';
import { Me } from './session';

export interface NavItem {
  id: string;
  label: string;
  icon: IconName;
  to: string;
  /** any of these housekeeping permissions grants the entry */
  permissions?: string[];
  /** emulator Feature value the entry depends on */
  feature?: string;
  /** Filament slug when the screen is still served by the classic panel */
  legacy?: string;
  description?: string;
  count?: (me: Me) => number;
}
export interface NavGroup { label: string; items: NavItem[] }

export const NAV: NavGroup[] = [
  { label: '', items: [{ id: 'home', label: 'Home', icon: 'home', to: '/' }] },
  {
    label: 'Community',
    items: [
      { id: 'players', label: 'Players', icon: 'users', to: '/players' },
      { id: 'bans', label: 'Bans', icon: 'lock', to: '/bans', permissions: ['manage_bans'], feature: 'ban-management', legacy: 'user-management/plus-bans', description: 'Active and expired bans. Issue new bans from a player card.' },
      { id: 'chatlogs', label: 'Chatlogs', icon: 'message', to: '/chatlogs', permissions: ['manage_room_chatlogs'], feature: 'room-chatlogs', legacy: 'hotel/plus-chatlogs', description: 'Room chat history, searchable by player and room.' },
    ],
  },
  {
    label: 'Roleplay',
    items: [
      { id: 'corporations', label: 'Corporations', icon: 'building', to: '/roleplay/corporations', description: 'Corps, their rank ladders and employees.' },
      { id: 'gangs', label: 'Gangs', icon: 'sword', to: '/roleplay/gangs', description: 'Gangs, roles and members.' },
      { id: 'crimes', label: 'Crimes', icon: 'shield', to: '/roleplay/crimes', description: 'What the police can charge people with.' },
    ],
  },
  {
    label: 'Content',
    items: [
      { id: 'articles', label: 'Articles', icon: 'article', to: '/articles', permissions: ['write_article', 'edit_article'], legacy: 'website/articles', description: 'News articles on the website and in the phone.' },
      { id: 'photos', label: 'Photos', icon: 'camera', to: '/photos', permissions: ['manage_camera_web'], legacy: 'camera-web', description: 'Pictures players take with the in-game camera.' },
      { id: 'tags', label: 'Tags', icon: 'label', to: '/tags', permissions: ['manage_article_tags'], legacy: 'website/tags', description: 'Tags group articles on the news page.' },
      { id: 'teams', label: 'Teams', icon: 'group', to: '/teams', permissions: ['manage_teams'], legacy: 'website/teams', description: 'Teams group staff on the public staff page.' },
      { id: 'homeitems', label: 'Home Page Items', icon: 'layout', to: '/home-items', permissions: ['manage_home_items'], legacy: 'home-management/items', description: 'Widgets, notes and backgrounds for player Me pages.' },
    ],
  },
  {
    label: 'Hotel',
    items: [
      { id: 'badges', label: 'Badges', icon: 'trophy', to: '/badges', permissions: ['manage_badges'], count: (me) => me.counts.draw_badges },
      { id: 'achievements', label: 'Achievements', icon: 'zap', to: '/achievements', permissions: ['manage_achievements'], legacy: 'hotel/achievements', description: 'Achievement definitions synced from the emulator.' },
      { id: 'catalog', label: 'Catalog Pages', icon: 'folder', to: '/catalog', permissions: ['manage_catalog_pages'], feature: 'catalog-management', legacy: 'hotel/catalog-pages', description: 'The in-game shop tree.' },
      { id: 'wordfilter', label: 'Word Filter', icon: 'shield', to: '/wordfilter', permissions: ['manage_wordfilter'], feature: 'wordfilter', legacy: 'hotel/wordfilters', description: 'Filtered words for chat and the website.' },
      { id: 'ads', label: 'Ads', icon: 'image', to: '/ads', permissions: ['manage_website_ads'], legacy: 'website-ads', description: 'In-game advertisement images.' },
    ],
  },
  {
    label: 'Shop',
    items: [
      { id: 'packages', label: 'Packages', icon: 'gift', to: '/shop/packages', permissions: ['manage_shop'], legacy: 'shop/packages', description: 'Bundles players can buy in the shop.' },
      { id: 'items', label: 'Items', icon: 'cart', to: '/shop/items', permissions: ['manage_shop'], legacy: 'shop/items', description: 'Furni, badges and currency that go into packages.' },
      { id: 'categories', label: 'Categories', icon: 'bookmark', to: '/shop/categories', permissions: ['manage_shop'], legacy: 'shop/categories', description: 'Shop categories.' },
    ],
  },
  {
    label: 'Recruiting',
    items: [
      { id: 'positions', label: 'Open Positions', icon: 'briefcase', to: '/positions', permissions: ['manage_staff_applications'], legacy: 'open-positions', description: 'Roles players can apply for.' },
      { id: 'applications', label: 'Applications', icon: 'mail', to: '/applications', permissions: ['manage_staff_applications'], legacy: 'staff-applications', description: 'Applications move through pending, approved and rejected.', count: (me) => me.counts.staff_applications },
    ],
  },
];

export function visibleNav(me: Me, can: (p: string) => boolean): NavGroup[] {
  return NAV.map((g) => ({
    label: g.label,
    items: g.items.filter((item) => {
      if (item.feature && !me.config.features.includes(item.feature)) return false;
      if (item.permissions && !item.permissions.some(can)) return false;
      return true;
    }),
  })).filter((g) => g.items.length > 0);
}

export function findNavItem(id: string): NavItem | undefined {
  for (const g of NAV) for (const i of g.items) if (i.id === id) return i;
  return undefined;
}

export function groupOf(id: string): string {
  for (const g of NAV) if (g.items.some((i) => i.id === id)) return g.label;
  return '';
}
