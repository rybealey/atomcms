/** Figure renders from the hotel's own imager (setting avatar_imager). */
export function headUrl(imager: string, look: string): string {
  return `${imager}${encodeURIComponent(look)}&headonly=1&size=m&head_direction=3`;
}

export function fullUrl(imager: string, look: string): string {
  return `${imager}${encodeURIComponent(look)}&direction=2&head_direction=3&size=m`;
}

export function badgeUrl(badgesPath: string, code: string): string {
  const base = badgesPath.endsWith('/') ? badgesPath : badgesPath + '/';
  return `${base}${code}.gif`;
}
