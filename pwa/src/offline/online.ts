import { useSyncExternalStore } from 'react';

// Whether the phone has network right now, according to the browser. This is only a hint: "online" does not
// guarantee that the server is reachable. The sync decides later (docs/pwa/offline.md, section 4.2).
function subscribe(notify: () => void) {
  window.addEventListener('online', notify);
  window.addEventListener('offline', notify);
  return () => {
    window.removeEventListener('online', notify);
    window.removeEventListener('offline', notify);
  };
}

export function useOnline(): boolean {
  return useSyncExternalStore(subscribe, () => navigator.onLine, () => true);
}
