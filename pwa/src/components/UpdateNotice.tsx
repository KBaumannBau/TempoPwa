import { useCallback, useEffect, useRef } from 'react';
import { useRegisterSW } from 'virtual:pwa-register/react';
import { tx } from '../i18n';
import { isIdle } from '../offline/idle';

/** How often the open app asks for a new version */
const CHECK_INTERVAL_MS = 30 * 60 * 1000;

/**
 * Updates of the app (docs/pwa/offline.md, section 7).
 *
 * - Checking: on start, when coming to the foreground, when the network is back and every 30 minutes.
 *   Important for the iPhone: an installed app is often only brought back from the background, never restarted.
 * - Activating: the new version is downloaded in the background but not activated in the middle of work.
 *   It is taken over as soon as the app goes to the background and is idle (nothing in progress, outbox empty),
 *   or when the worker taps the notice. At the latest it is active after the next cold start.
 */
export function UpdateNotice() {
  const registration = useRef<ServiceWorkerRegistration | undefined>(undefined);
  const activating = useRef(false);

  const {
    needRefresh: [newVersion],
    updateServiceWorker,
  } = useRegisterSW({
    onRegisteredSW(_url, r) {
      registration.current = r;
    },
    onRegisterError(error) {
      console.warn('Service worker not registered', error);
    },
  });

  // Ask for a new version
  useEffect(() => {
    const check = () => {
      const r = registration.current;
      if (!r || !navigator.onLine || r.installing) return;
      r.update().catch(() => { /* no network or server gone: next time */ });
    };
    const onVisible = () => { if (document.visibilityState === 'visible') check(); };
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('online', check);
    const timer = window.setInterval(check, CHECK_INTERVAL_MS);
    return () => {
      document.removeEventListener('visibilitychange', onVisible);
      window.removeEventListener('online', check);
      window.clearInterval(timer);
    };
  }, []);

  // Activate the new version and reload the page once it has taken control.
  // We reload ourselves: Workbox reports an update found via registration.update() as "external"
  // and then does not reload. The listener is only added here, so that the first installation
  // (clientsClaim) does not reload the page.
  const activate = useCallback(() => {
    if (activating.current) return;
    activating.current = true;
    navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload(), { once: true });
    void updateServiceWorker();
  }, [updateServiceWorker]);

  // Take over the new version silently when the app goes to the background and nothing is in progress.
  // When it comes back it is already active; the worker does not notice the reload.
  useEffect(() => {
    if (!newVersion) return;
    const onHidden = () => {
      if (document.visibilityState === 'hidden' && isIdle()) activate();
    };
    document.addEventListener('visibilitychange', onHidden);
    return () => document.removeEventListener('visibilitychange', onHidden);
  }, [newVersion, activate]);

  if (!newVersion) return null;

  return (
    <div className="update-notice" role="status">
      <span>{tx('Neue Version der App verfügbar.')}</span>
      <button
        type="button"
        onClick={() => {
          if (isIdle() || window.confirm(tx('Es ist noch etwas in Arbeit. Trotzdem jetzt aktualisieren?'))) {
            activate();
          }
        }}
      >
        {tx('Jetzt aktualisieren')}
      </button>
    </div>
  );
}
