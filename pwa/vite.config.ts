import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

// Version der App: in der Pipeline als APP_VERSION gesetzt (mit Build-Kennung),
// sonst aus der Datei VERSION im Wurzelordner des Repos (gepflegt von deployment.sh).
function appVersion(): string {
  if (process.env.APP_VERSION) return process.env.APP_VERSION;
  try {
    return `${readFileSync(new URL('../VERSION', import.meta.url), 'utf8').trim()}+lokal`;
  } catch {
    return 'unbekannt';
  }
}

// Läuft im pwa-Container hinter nginx (ein Origin, siehe docker/nginx/conf).
// Der Browser spricht nur mit nginx; der HMR-Websocket geht deshalb über HTTP_PORT.
const httpPort = Number(process.env.HTTP_PORT ?? 8011);

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify(appVersion()),
  },
  plugins: [
    react(),
    VitePWA({
      // Updates kontrolliert übernehmen, nicht mitten in der Arbeit (components/Aktualisierung.tsx,
      // docs/pwa/offline.md Abschnitt 7). Registriert wird der Service Worker in Aktualisierung.tsx.
      registerType: 'prompt',
      injectRegister: false,
      includeAssets: ['favicon.svg', 'icons/apple-touch-icon.png'],
      manifest: {
        id: '/',
        name: 'Tempo – Stundenerfassung K. Baumann Bau',
        short_name: 'Tempo',
        description: 'Stundenerfassung für Mitarbeiter, Polier, Bauleitung und Büro. Stempeln geht auch ohne Netz.',
        lang: 'de',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#0077c8',
        theme_color: '#0077c8',
        // Icons erzeugt mit scripts/icons.mjs (weißes „K.“ aus dem Logo auf Baumann-Blau)
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
          { src: '/icons/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        ],
      },
      workbox: {
        // Die API wird nie vom Service Worker als App-Shell beantwortet
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api(\/|$)/, /^\/bundles\//, /^\/_profiler/, /^\/_wdt/],
        // App-Hülle komplett auf dem Handy (Precache), damit die App ohne Netz startet.
        // Fachdaten liegen nicht hier, sondern später in IndexedDB (docs/pwa/offline.md).
        globPatterns: ['**/*.{js,css,html,svg,png,woff2,json}'],
        cleanupOutdatedCaches: true,
        // Schon beim ersten Besuch die offene Seite übernehmen: Die App läuft dann sofort offline, und
        // spätere Updates melden sich zuverlässig („controlling“), damit die Seite einmal neu lädt.
        // Kein skipWaiting: Eine neue Version wartet, bis Aktualisierung.tsx sie freigibt.
        clientsClaim: true,
      },
      // Im Devserver kein Service Worker, sonst stört er HMR
      devOptions: { enabled: false },
    }),
  ],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    hmr: { clientPort: httpPort },
    watch: process.env.VITE_POLLING === '1' ? { usePolling: true, interval: 300 } : undefined,
  },
  build: {
    sourcemap: true,
  },
});
