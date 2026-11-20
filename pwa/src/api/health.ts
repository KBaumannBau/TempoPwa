import { useEffect, useState } from 'react';
import { api } from './client';
import type { components } from './generated/schema';

export type Health = components['schemas']['Health'];

export type HealthZustand =
  | { art: 'laedt' }
  | { art: 'ok'; health: Health }
  | { art: 'fehler'; text: string };

/** Ruft GET /api/health einmal ab (Startseite, Fußzeile). */
export function useHealth(): HealthZustand {
  const [zustand, setZustand] = useState<HealthZustand>({ art: 'laedt' });

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

  return zustand;
}
