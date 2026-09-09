// pixelarticons, bundled locally (no CDN). Add a name here to use it in <Icon>.
import alert from 'pixelarticons/svg/warning-diamond.svg';
import article from 'pixelarticons/svg/article.svg';
import bookmark from 'pixelarticons/svg/bookmark.svg';
import briefcase from 'pixelarticons/svg/briefcase.svg';
import building from 'pixelarticons/svg/building.svg';
import camera from 'pixelarticons/svg/camera.svg';
import cart from 'pixelarticons/svg/shopping-cart.svg';
import chevronLeft from 'pixelarticons/svg/chevron-left.svg';
import chevronRight from 'pixelarticons/svg/chevron-right.svg';
import close from 'pixelarticons/svg/close.svg';
import code from 'pixelarticons/svg/code.svg';
import coin from 'pixelarticons/svg/dollar.svg';
import copy from 'pixelarticons/svg/copy.svg';
import externalLink from 'pixelarticons/svg/external-link.svg';
import eye from 'pixelarticons/svg/eye.svg';
import eyeClosed from 'pixelarticons/svg/eye-off.svg';
import flag from 'pixelarticons/svg/flag.svg';
import folder from 'pixelarticons/svg/folder.svg';
import gift from 'pixelarticons/svg/gift.svg';
import group from 'pixelarticons/svg/human-arms-up.svg';
import heart from 'pixelarticons/svg/heart.svg';
import home from 'pixelarticons/svg/home.svg';
import image from 'pixelarticons/svg/image.svg';
import label from 'pixelarticons/svg/label.svg';
import layout from 'pixelarticons/svg/layout.svg';
import lock from 'pixelarticons/svg/lock.svg';
import lockOpen from 'pixelarticons/svg/unlock.svg';
import mail from 'pixelarticons/svg/mail.svg';
import menu from 'pixelarticons/svg/menu.svg';
import message from 'pixelarticons/svg/message.svg';
import moon from 'pixelarticons/svg/moon.svg';
import moreHorizontal from 'pixelarticons/svg/more-horizontal.svg';
import search from 'pixelarticons/svg/search.svg';
import shield from 'pixelarticons/svg/shield.svg';
import sliders from 'pixelarticons/svg/sliders.svg';
import sun from 'pixelarticons/svg/sun.svg';
import sword from 'pixelarticons/svg/sword.svg';
import trophy from 'pixelarticons/svg/trophy.svg';
import upload from 'pixelarticons/svg/upload.svg';
import userPlus from 'pixelarticons/svg/user-plus.svg';
import users from 'pixelarticons/svg/users.svg';
import zap from 'pixelarticons/svg/zap.svg';

export const ICONS = {
  alert, article, bookmark, briefcase, building, camera, cart, 'chevron-left': chevronLeft, 'chevron-right': chevronRight, close, code, coin, copy,
  'external-link': externalLink, eye, 'eye-closed': eyeClosed, flag, folder, gift, group, heart, home, image, label, layout, lock, 'lock-open': lockOpen,
  mail, menu, message, moon, 'more-horizontal': moreHorizontal, search, shield, sliders, sun, sword, trophy, upload, 'user-plus': userPlus, users, zap,
} as const;

export type IconName = keyof typeof ICONS;
