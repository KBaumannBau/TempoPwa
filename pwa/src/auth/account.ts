import type { components } from '../api/generated/schema';
import type { Sprache } from '../i18n';

/**
 * What the phone knows about the signed-in person (GET /api/auth/me). Generated from the OpenAPI schema;
 * only `language` is narrowed to the languages the app knows.
 */
export type Account = Omit<components['schemas']['AccountView'], 'language'> & { language: Sprache };

/** Same normalization as the server: case and whitespace do not matter */
export const normalizeUsername = (raw: string) => raw.trim().toUpperCase().replace(/\s+/g, '');
