import { useEffect } from 'react';

// Is the app "idle", i.e. may a new version be activated (the page reloads)?
// It is busy while a screen has work in progress (e.g. "check and send the day") and,
// later, while the outbox is not empty (docs/pwa/offline.md, section 7).

const busyScreens = new Set<symbol>();
let isOutboxEmpty: () => boolean = () => true;

/** A screen reports: work in progress here, please do not reload now. */
export function useBusy(active: boolean) {
  useEffect(() => {
    if (!active) return;
    const token = Symbol('busy');
    busyScreens.add(token);
    return () => { busyScreens.delete(token); };
  }, [active]);
}

/** The sync (pwa/src/sync/, comes with step 2) registers here whether entries are still waiting in the outbox. */
export function setOutboxCheck(check: () => boolean) {
  isOutboxEmpty = check;
}

export function isIdle(): boolean {
  return busyScreens.size === 0 && isOutboxEmpty();
}
