// Typisierter Zugriff auf die Tempo-API.
// Die Typen in ./generated werden aus dem OpenAPI-Schema erzeugt: npm run api:types
// (nicht von Hand ändern).
//
// Standard ist derselbe Origin wie die PWA (lokal über nginx, siehe ADR 0001): keine Basis-URL, kein CORS.
// Liegt die API unter einer anderen Domain (VITE_API_URL beim Build), gehen die Anfragen dorthin;
// die API muss den Origin der PWA dann per CORS_ALLOW_ORIGIN zulassen.
import createClient from 'openapi-fetch';
import type { paths } from './generated/schema';

const basis = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '');

export const api = createClient<paths>({
  baseUrl: basis,
  credentials: basis ? 'include' : 'same-origin',
  headers: { Accept: 'application/json' },
});
