// Pieces the Content screens share: the list-and-editor frame, an image
// picker that hands the server a data URL, and save helpers that toast what
// the server said about reaching the hotel.
import React, { useEffect, useRef, useState } from 'react';
import { useSession } from '@hk/lib/session';
import { ApiError } from '@hk/lib/api';
import { Btn, Icon } from '@hk/ui';

/** Run a save, toast its message, and say whether it worked. */
export function useSave() {
  const { toast } = useSession();
  const [busy, setBusy] = useState(false);
  const run = async (fn: () => Promise<{ message?: string } | unknown>, fallback = 'Saved'): Promise<boolean> => {
    setBusy(true);
    try {
      const r = (await fn()) as { message?: string; reloaded?: boolean } | undefined;
      toast(r && r.reloaded === false ? 'warn' : 'ok', r?.message ?? fallback);
      return true;
    } catch (e) {
      toast('bad', 'Could not save', e instanceof ApiError || e instanceof Error ? e.message : undefined);
      return false;
    } finally {
      setBusy(false);
    }
  };
  return { busy, run };
}

/** Pick an image file and read it as a data URL for a JSON request. */
export function ImagePick({ label, accept = 'image/png,image/gif,image/jpeg,image/webp', onPick, maxBytes = 2 * 1024 * 1024 }: { label: string; accept?: string; onPick: (dataUrl: string, file: File) => void; maxBytes?: number }) {
  const ref = useRef<HTMLInputElement>(null);
  const { toast } = useSession();
  return (
    <>
      <input
        ref={ref}
        type="file"
        accept={accept}
        className="sr-only"
        aria-label={label}
        onChange={(e) => {
          const file = e.target.files?.[0];
          e.target.value = '';
          if (!file) return;
          if (file.size > maxBytes) {
            toast('bad', 'That file is too big', `The limit is ${Math.round(maxBytes / 1024)} KB.`);
            return;
          }
          const reader = new FileReader();
          reader.onload = () => onPick(String(reader.result), file);
          reader.readAsDataURL(file);
        }}
      />
      <Btn sm onClick={() => ref.current?.click()}>
        <Icon name="image" /> {label}
      </Btn>
    </>
  );
}

/** A pixel-art image at whole-number scales on the neutral tile. */
export function PixelScales({ src, alt, scales = [1, 2, 3], base = 40 }: { src: string; alt: string; scales?: number[]; base?: number }) {
  const [broken, setBroken] = useState(false);
  useEffect(() => setBroken(false), [src]);
  if (broken) {
    return (
      <div style={{ padding: 16, borderRadius: 10, background: 'var(--sunken)' }} className="sub">
        No image yet for this badge.
      </div>
    );
  }
  return (
    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 16, padding: 16, borderRadius: 10, background: 'var(--sunken)', flexWrap: 'wrap' }}>
      {scales.map((s) => (
        <div key={s} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
          <img src={src} alt={`${alt} at ${s}×`} width={base * s} height={base * s} className="px" style={{ background: 'var(--surface)', objectFit: 'contain' }} onError={() => setBroken(true)} />
          <span className="mono" style={{ fontSize: 11 }}>
            {s}×
          </span>
        </div>
      ))}
    </div>
  );
}

/** The list card and editor side by side, stacking at phone width. */
export function ListEditor({ list, editor }: { list: React.ReactNode; editor: React.ReactNode }) {
  return (
    <div className="split">
      <div className="main-col" style={{ flexBasis: 560 }}>
        {list}
      </div>
      {editor}
    </div>
  );
}

/** Badge art from the hotel's badge folder; a missing image leaves the tile empty instead of a broken glyph. */
export function BadgeArt({ code, size = 40 }: { code: string; size?: number }) {
  const { me } = useSession();
  const [broken, setBroken] = useState(false);
  useEffect(() => setBroken(false), [code]);
  if (!code || broken) return null;
  const base = me.config.badges_path.endsWith('/') ? me.config.badges_path : `${me.config.badges_path}/`;
  return <img className="badge-img" src={`${base}${code}.gif`} alt={code} title={code} width={size} height={size} style={{ objectFit: 'none' }} loading="lazy" onError={() => setBroken(true)} />;
}
