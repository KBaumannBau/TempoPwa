import { useSyncExternalStore } from 'react';

// Ob das Handy gerade Netz hat, laut Browser. Das ist nur ein Hinweis: „online“ heißt nicht sicher,
// dass der Server erreichbar ist. Verbindlich ist später der Abgleich (docs/pwa/offline.md, Abschnitt 4.2).
function abonnieren(melden: () => void) {
  window.addEventListener('online', melden);
  window.addEventListener('offline', melden);
  return () => {
    window.removeEventListener('online', melden);
    window.removeEventListener('offline', melden);
  };
}

export function useOnline(): boolean {
  return useSyncExternalStore(abonnieren, () => navigator.onLine, () => true);
}
