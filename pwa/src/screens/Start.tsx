import { useHealth } from '../api/health';
import { useOnline } from '../offline/online';

/** Vorläufige Startseite: zeigt, ob PWA, API und Datenbank zusammenspielen. */
export function Start() {
  const zustand = useHealth();
  const online = useOnline();

  return (
    <main className="start">
      <h1>Stundenerfassung</h1>
      <p>Tempo · K. Baumann Bau</p>
      {!online && (
        <p className="hinweis" role="status">
          Kein Netz. Die App läuft trotzdem. Sobald wieder Netz da ist, verbindet sie sich von selbst.
        </p>
      )}
      <section className="karte" aria-live="polite">
        <h2>Verbindung zur API</h2>
        {zustand.art === 'laedt' && <p>Prüfe …</p>}
        {zustand.art === 'ok' && (
          <dl>
            <dt>Status</dt><dd data-testid="health-status">{zustand.health.status}</dd>
            <dt>Datenbank</dt><dd>{zustand.health.datenbank}</dd>
            <dt>Serverzeit</dt><dd>{new Date(zustand.health.zeit).toLocaleString('de-DE')}</dd>
          </dl>
        )}
        {zustand.art === 'fehler' && (
          online
            ? <p className="fehler">Nicht erreichbar: {zustand.text}</p>
            : <p>Offline, die API ist gerade nicht erreichbar.</p>
        )}
      </section>
    </main>
  );
}
