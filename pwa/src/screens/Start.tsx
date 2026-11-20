import { useHealth } from '../api/health';

/** Vorläufige Startseite: zeigt, ob PWA, API und Datenbank zusammenspielen. */
export function Start() {
  const zustand = useHealth();

  return (
    <main className="start">
      <h1>Tempo</h1>
      <p>Stundenerfassung K. Baumann Bau</p>
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
        {zustand.art === 'fehler' && <p className="fehler">Nicht erreichbar: {zustand.text}</p>}
      </section>
    </main>
  );
}
