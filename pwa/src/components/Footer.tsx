import { useHealth } from '../api/health';

/** Footer with the versions of app and API (app from the build, API from /api/health). */
export function Footer() {
  const health = useHealth();
  const apiVersion = health.kind === 'ok' ? health.health.version : health.kind === 'loading' ? '…' : 'nicht erreichbar';

  return (
    <footer className="footer">
      <span>Tempo</span>
      <span title="Version der App">App {__APP_VERSION__}</span>
      <span title="Version der API">API {apiVersion}</span>
    </footer>
  );
}
