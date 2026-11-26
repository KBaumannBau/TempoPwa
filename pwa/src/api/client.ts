// Typed access to the Tempo API.
// The types in ./generated are generated from the OpenAPI schema: npm run api:types
// (do not edit by hand).
//
// Default is the same origin as the PWA (locally via nginx, see ADR 0001): no base URL, no CORS.
// If the API lives on another domain (VITE_API_URL at build time), requests go there;
// the API must then allow the origin of the PWA via CORS_ALLOW_ORIGIN.
import createClient from 'openapi-fetch';
import type { paths } from './generated/schema';

const baseUrl = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '');

export const api = createClient<paths>({
  baseUrl,
  credentials: baseUrl ? 'include' : 'same-origin',
  headers: { Accept: 'application/json' },
});
