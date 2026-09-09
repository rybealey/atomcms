import { useState } from 'react';
import { useCrumbs } from '../Shell';
import { useSession } from '@hk/lib/session';
import { findNavItem, groupOf } from '@hk/lib/nav';
import { Button, EmptyState, Icon } from '@hk/ui';

/**
 * Bridge for screens the new panel has not rebuilt yet: the classic Filament
 * page is embedded in place so staff never leave the new shell, with an
 * escape hatch to open it in its own tab.
 */
export default function Legacy({ id }: { id: string }) {
  const item = findNavItem(id);
  const { me } = useSession();
  const [embedded, setEmbedded] = useState(true);
  useCrumbs([{ label: groupOf(id) || 'Housekeeping' }, { label: item?.label ?? id }]);

  if (!item || !item.legacy) {
    return <EmptyState title="Not built yet" body="This screen has no classic equivalent to fall back on." />;
  }
  const url = `${me.config.legacy_url}/${item.legacy}`;

  return (
    <section className="hk-page" aria-label={item.label}>
      <div className="hk-pagehead" style={{ alignItems: 'center' }}>
        <div>
          <h1 className="hk-h1" style={{ marginBottom: 4 }}>{item.label}</h1>
          <p className="hk-lede">{item.description ?? ''} Served by the classic panel until it is rebuilt here.</p>
        </div>
        <div className="hk-actions">
          <Button variant="ghost" size="sm" onClick={() => setEmbedded((e) => !e)}>
            {embedded ? 'Hide' : 'Show'} embedded view
          </Button>
          <Button variant="secondary" size="sm" href={url} external>
            Open in classic <Icon name="external-link" size={14} />
          </Button>
        </div>
      </div>
      {embedded ? (
        <div className="hk-legacy">
          <iframe title={item.label} src={url} />
        </div>
      ) : (
        <EmptyState title={`${item.label} lives in the classic panel`} body="Open it in a new tab, or show the embedded view again." action={<Button variant="secondary" href={url} external>Open in classic</Button>} />
      )}
    </section>
  );
}
