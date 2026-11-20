import { useEffect, useState } from 'react';
import { api } from '../api/client';
import type { components } from '../api/generated/schema';

type Health = components['schemas']['Health'];

type Zustand =
  | { art: 'laedt' }
  | { art: 'ok'; health: Health }
  | { art: 'fehler'; text: string };

/** Vorläufige Startseite: zeigt, ob PWA, API und Datenbank zusammenspielen. */
export function Start() {
  const [zustand, setZustand] = useState<Zustand>({ art: 'laedt' });

  useEffect(() => {
    let aktiv = true;
    api.GET('/api/health')
      .then(({ data, error, response }) => {
        if (!aktiv) return;
        if (data) setZustand({ art: 'ok', health: data });
        else setZustand({ art: 'fehler', text: `HTTP ${response.status}${error ? ` – ${JSON.stringify(error)}` : ''}` });
      })
      .catch((e: unknown) => aktiv && setZustand({ art: 'fehler', text: e instanceof Error ? e.message : String(e) }));
    return () => { aktiv = false; };
  }, []);

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
