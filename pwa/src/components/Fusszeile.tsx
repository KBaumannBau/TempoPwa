import { useHealth } from '../api/health';

/** Fußzeile mit den Versionen von App und API (App aus dem Build, API aus /api/health). */
export function Fusszeile() {
  const health = useHealth();
  const api = health.art === 'ok' ? health.health.version : health.art === 'laedt' ? '…' : 'nicht erreichbar';

  return (
    <footer className="fusszeile">
      <span>Tempo</span>
      <span title="Version der App">App {__APP_VERSION__}</span>
      <span title="Version der API">API {api}</span>
    </footer>
  );
}
