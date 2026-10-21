/** Figure renders from the hotel's own imager (setting avatar_imager). Size s is half scale, m is 1x, l is 2x. */
export function headUrl(imager: string, look: string, size: 's' | 'm' | 'l' = 'm'): string {
  return `${imager}${encodeURIComponent(look)}&headonly=1&size=${size}&head_direction=3`;
}

export function fullUrl(imager: string, look: string, size: 's' | 'm' | 'l' = 'm'): string {
  return `${imager}${encodeURIComponent(look)}&direction=2&head_direction=3&size=${size}`;
}

export function badgeUrl(badgesPath: string, code: string): string {
  const base = badgesPath.endsWith('/') ? badgesPath : badgesPath + '/';
  return `${base}${code}.gif`;
}
