import { useEffect, useState } from 'react';
import { api } from './client';
import type { components } from './generated/schema';

export type Health = components['schemas']['Health'];

export type HealthState =
  | { kind: 'loading' }
  | { kind: 'ok'; health: Health }
  | { kind: 'error'; text: string };

/** Fetches GET /api/health once (start page, footer). */
export function useHealth(): HealthState {
  const [state, setState] = useState<HealthState>({ kind: 'loading' });

  useEffect(() => {
    let active = true;
    api.GET('/api/health')
      .then(({ data, error, response }) => {
        if (!active) return;
        if (data) setState({ kind: 'ok', health: data });
        else setState({ kind: 'error', text: `HTTP ${response.status}${error ? ` – ${JSON.stringify(error)}` : ''}` });
      })
      .catch((e: unknown) => active && setState({ kind: 'error', text: e instanceof Error ? e.message : String(e) }));
    return () => { active = false; };
  }, []);

  return state;
}
