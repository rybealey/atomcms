// How each website_settings key is presented. Keys the API returns that are
// not listed here still show up, under "Other", as plain text fields.
import { IconName } from './icons';

export type SettingType = 'bool' | 'int' | 'duration' | 'enum' | 'rank' | 'secret' | 'path' | 'text' | 'textarea' | 'image' | 'look';

export interface DangerSpec { title: string; body: string; impacts: string[]; cta: string }
export interface SettingMeta {
  key: string;
  group: string;
  type: SettingType;
  label: string;
  desc: string;
  options?: string[];
  unit?: string;
  min?: number;
  step?: number;
  max?: number;
  mono?: boolean;
  dependsOn?: string;
  preview?: boolean;
  hidden?: boolean;
  danger?: DangerSpec;
}
export interface GroupMeta { id: string; label: string; icon: IconName; desc: string; locked?: boolean }

export const GROUPS: GroupMeta[] = [
  { id: 'general', label: 'General & branding', icon: 'home', desc: 'Hotel name, theme, logo and how PixelRP shows up in search.' },
  { id: 'registration', label: 'Registration & new players', icon: 'user-plus', desc: 'Who can sign up and what a brand-new player starts with.' },
  { id: 'security', label: 'Security', icon: 'shield', desc: 'Staff 2FA, VPN blocking and bot protection.' },
  { id: 'maintenance', label: 'Maintenance', icon: 'alert', desc: 'Close the hotel website to players while you work on it.' },
  { id: 'staff', label: 'Staff ranks', icon: 'users', desc: 'Rank thresholds for staff visibility and Housekeeping access. The ladder and permission matrix live under Staff.' },
  { id: 'community', label: 'Community features', icon: 'heart', desc: 'Comments, guestbooks, referrals and player-drawn badges.' },
  { id: 'integrations', label: 'Integrations', icon: 'zap', desc: 'Discord, TinyMCE and the emulator RCON connection.' },
  { id: 'advanced', label: 'Advanced (paths)', icon: 'code', desc: 'Developer-only file paths and client locations.', locked: true },
  { id: 'other', label: 'Other', icon: 'sliders', desc: 'Settings without a home yet. Edit with care.' },
];

export const SETTINGS: SettingMeta[] = [
  { group: 'general', key: 'hotel_name', type: 'text', label: 'Hotel name', desc: 'Used in page titles, emails and the maintenance page.', max: 32 },
  { group: 'general', key: 'theme', type: 'enum', label: 'Website theme', desc: 'Which frontend theme players see. The deploy resets this to the built theme.', options: ['pixelrp', 'dusk', 'atom'] },
  { group: 'general', key: 'cms_color_mode', type: 'enum', label: 'Colour mode', desc: 'Default colour mode for the website. Players can still switch.', options: ['light', 'dark', 'auto'] },
  { group: 'general', key: 'cms_logo', type: 'image', label: 'Logo', desc: 'Shown in the site header. PNG or GIF, transparent background.' },
  { group: 'general', key: 'cms_header', type: 'image', label: 'Me-page header', desc: "Wide art across the top of every player's Me page." },
  { group: 'general', key: 'cms_me_backdrop', type: 'image', label: 'Me-page backdrop', desc: 'Scene behind the Me page widgets.' },
  { group: 'general', key: 'seo_description', type: 'textarea', label: 'Search description', desc: 'One or two sentences shown under the link in search results.' },
  { group: 'general', key: 'seo_keywords', type: 'text', label: 'Search keywords', desc: 'Comma-separated.' },
  { group: 'general', key: 'avatar_imager', type: 'path', label: 'Avatar imager URL', desc: 'Where the website renders player figures from.' },
  { group: 'general', key: 'housekeeping_url', type: 'path', label: 'Housekeeping URL', desc: 'Path to this panel.' },
  { group: 'registration', key: 'disable_registration', type: 'bool', label: 'Pause registration', desc: 'While on, nobody can create an account. Existing players can still log in.', danger: { title: 'Pause registration?', body: 'New players will see "Registration is closed" on the signup page until you turn this off.', impacts: ['Beta codes stop working too', 'Staff can still log in'], cta: 'Pause Registration' } },
  { group: 'registration', key: 'requires_beta_code', type: 'bool', label: 'Require a beta code to register', desc: 'New players must enter a valid beta code before creating an account.' },
  { group: 'registration', key: 'max_accounts_per_ip', type: 'int', label: 'Accounts per IP address', desc: 'Signups from the same connection beyond this number are refused.', unit: 'accounts', min: 1 },
  { group: 'registration', key: 'username_regex', type: 'path', label: 'Allowed username characters', desc: 'Edit the pattern only if you know regex.' },
  { group: 'registration', key: 'start_look', type: 'look', label: 'Starting outfit', desc: 'The figure every new player spawns with.' },
  { group: 'registration', key: 'start_motto', type: 'text', label: 'Starting motto', desc: 'Players can change it right away.', max: 127 },
  { group: 'registration', key: 'hotel_home_room', type: 'int', label: 'Home room', desc: 'Room ID new players are dropped into. 0 means no home room.', unit: 'room id', min: 0 },
  { group: 'registration', key: 'start_credits', type: 'int', label: 'Starting credits', desc: '', unit: 'credits', min: 0, step: 500 },
  { group: 'registration', key: 'start_duckets', type: 'int', label: 'Starting duckets', desc: '', unit: 'duckets', min: 0, step: 500 },
  { group: 'registration', key: 'start_diamonds', type: 'int', label: 'Starting diamonds', desc: '', unit: 'diamonds', min: 0, step: 10 },
  { group: 'registration', key: 'start_points', type: 'int', label: 'Starting points', desc: '', unit: 'points', min: 0, step: 10 },
  { group: 'registration', key: 'give_hc_on_register', type: 'bool', label: 'Free club membership on signup', desc: 'Every new player gets a club subscription immediately.' },
  { group: 'registration', key: 'hc_on_register_duration', type: 'duration', label: 'Club duration on signup', desc: 'How long the free club membership lasts.', dependsOn: 'give_hc_on_register' },
  { group: 'security', key: 'force_staff_2fa', type: 'bool', label: 'Require 2FA for staff', desc: 'Staff without two-factor set up are sent to the 2FA page before anything else.', danger: { title: 'Require 2FA for all staff?', body: 'Staff members without two-factor will be locked to the setup page on their next visit.', impacts: ['Applies from the "counts as staff" rank upward', 'You can turn this off again at any time'], cta: 'Require 2FA' } },
  { group: 'security', key: 'vpn_block_enabled', type: 'bool', label: 'Block VPN connections', desc: 'Refuses logins and signups from known VPN ranges using ipdata.co.' },
  { group: 'security', key: 'ipdata_api_key', type: 'secret', label: 'ipdata.co API key', desc: 'Needed for VPN blocking. Never shown in lists.', dependsOn: 'vpn_block_enabled' },
  { group: 'security', key: 'cloudflare_turnstile_enabled', type: 'bool', label: 'Cloudflare Turnstile', desc: 'Bot check on login and signup. Turn off reCAPTCHA if you use this.' },
  { group: 'security', key: 'google_recaptcha_enabled', type: 'bool', label: 'Google reCAPTCHA', desc: 'Alternative bot check. Do not enable together with Turnstile.' },
  { group: 'security', key: 'website_wordfilter_enabled', type: 'bool', label: 'Word filter on the website', desc: 'Applies the hotel word filter to comments, guestbooks and mottos.' },
  { group: 'maintenance', key: 'maintenance_enabled', type: 'bool', hidden: true, label: 'Maintenance mode', desc: '', danger: { title: 'Turn on maintenance mode?', body: 'Everyone below the maintenance login rank is logged out of the website and sees the maintenance message.', impacts: ['The hotel client stays reachable for staff'], cta: 'Turn Maintenance On' } },
  { group: 'maintenance', key: 'maintenance_message', type: 'textarea', label: 'Maintenance message', desc: 'Shown to everyone below the required rank while maintenance is on.', preview: true },
  { group: 'maintenance', key: 'min_maintenance_login_rank', type: 'rank', label: 'Who can still log in', desc: 'This rank and above bypass the maintenance page.' },
  { group: 'staff', key: 'min_staff_rank', type: 'rank', label: 'Counts as staff from', desc: 'Players at this rank and above appear on the staff page and are held to the staff 2FA rule.' },
  { group: 'staff', key: 'min_housekeeping_rank', type: 'rank', label: 'Housekeeping button from', desc: 'Sees the Housekeeping link on the website. Access itself is the can_access_housekeeping capability under Staff.' },
  { group: 'staff', key: 'min_rank_to_see_hidden_staff', type: 'rank', label: 'Can see hidden ranks from', desc: 'Hidden ranks are visible to this rank and above.' },
  { group: 'community', key: 'max_comment_per_article', type: 'int', label: 'Comments per article per player', desc: '', unit: 'comments', min: 0 },
  { group: 'community', key: 'max_guestbook_posts_per_profile', type: 'int', label: 'Guestbook posts per profile per player', desc: '', unit: 'posts', min: 0 },
  { group: 'community', key: 'referrals_needed', type: 'int', label: 'Referrals to claim a reward', desc: '', unit: 'referrals', min: 1 },
  { group: 'community', key: 'referral_reward_currency_type', type: 'enum', label: 'Referral reward currency', desc: '', options: ['credits', 'duckets', 'diamonds', 'points'] },
  { group: 'community', key: 'referral_reward_amount', type: 'int', label: 'Referral reward amount', desc: '', unit: 'per claim', min: 0 },
  { group: 'community', key: 'drawbadge_currency_type', type: 'enum', label: 'Drawn badge price currency', desc: 'What players pay to submit a badge they drew.', options: ['credits', 'duckets', 'diamonds', 'points'] },
  { group: 'community', key: 'drawbadge_currency_value', type: 'int', label: 'Drawn badge price', desc: '', unit: 'per badge', min: 0 },
  { group: 'community', key: 'point_currency_number', type: 'int', label: 'Points currency ID', desc: 'Emulator currency type used for points. Usually 101.', unit: 'currency id', min: 0 },
  { group: 'integrations', key: 'enable_discord_webhook', type: 'bool', label: 'Post to Discord', desc: 'Sends new articles to a Discord channel.' },
  { group: 'integrations', key: 'discord_webhook_url', type: 'secret', label: 'Discord webhook URL', desc: '', dependsOn: 'enable_discord_webhook' },
  { group: 'integrations', key: 'discord_widget_id', type: 'text', label: 'Discord widget ID', desc: 'Server ID shown in the website Discord widget.', mono: true },
  { group: 'integrations', key: 'discord_invitation_link', type: 'text', label: 'Discord invite link', desc: '' },
  { group: 'integrations', key: 'tinymce_api_key', type: 'secret', label: 'TinyMCE API key', desc: 'Powers the article editor.' },
  { group: 'integrations', key: 'rcon_ip', type: 'text', label: 'RCON host', desc: 'Emulator address the website sends commands to.', mono: true },
  { group: 'integrations', key: 'rcon_port', type: 'int', label: 'RCON port', desc: '', unit: 'port', min: 1 },
  { group: 'advanced', key: 'nitro_path', type: 'path', label: 'Nitro client path', desc: 'Folder containing the Nitro index.html.' },
  { group: 'advanced', key: 'nitro_external_texts_file', type: 'path', label: 'Nitro ExternalTexts file', desc: '' },
  { group: 'advanced', key: 'flash_external_texts_file', type: 'path', label: 'Flash external texts file', desc: 'Leave empty when not serving the flash client.' },
  { group: 'advanced', key: 'furniture_icons_path', type: 'path', label: 'Furniture icons path', desc: '' },
  { group: 'advanced', key: 'room_thumbnail_path', type: 'path', label: 'Room thumbnails path', desc: '' },
  { group: 'advanced', key: 'catalog_icons_path', type: 'path', label: 'Catalog icons path', desc: '' },
  { group: 'advanced', key: 'badges_path', type: 'path', label: 'Badges path (frontend)', desc: '' },
  { group: 'advanced', key: 'badge_path_filesystem', type: 'path', label: 'Badges path (server)', desc: '' },
  { group: 'advanced', key: 'group_badge_path', type: 'path', label: 'Group badges path', desc: '' },
  { group: 'advanced', key: 'ads_picture_path', type: 'path', label: 'Ads path (frontend)', desc: '' },
  { group: 'advanced', key: 'ads_path_filesystem', type: 'path', label: 'Ads path (server)', desc: '' },
];

export function metaFor(key: string, comment: string): SettingMeta {
  const known = SETTINGS.find((s) => s.key === key);
  if (known) return known;
  return { key, group: 'other', type: 'text', label: key.replace(/_/g, ' '), desc: comment, mono: true };
}
