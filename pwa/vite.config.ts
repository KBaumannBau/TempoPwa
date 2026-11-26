import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

// Version of the app: set as APP_VERSION in the pipeline (with build id),
// otherwise from the file VERSION in the root folder of the repo (maintained by deployment.sh).
function appVersion(): string {
  if (process.env.APP_VERSION) return process.env.APP_VERSION;
  try {
    return `${readFileSync(new URL('../VERSION', import.meta.url), 'utf8').trim()}+local`;
  } catch {
    return 'unknown';
  }
}

// Runs in the pwa container behind nginx (one origin, see docker/nginx/conf).
// The browser only talks to nginx; the HMR websocket therefore goes through HTTP_PORT.
const httpPort = Number(process.env.HTTP_PORT ?? 8011);

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify(appVersion()),
  },
  plugins: [
    react(),
    VitePWA({
      // Take over updates in a controlled way, not in the middle of work (components/UpdateNotice.tsx,
      // docs/pwa/offline.md section 7). The service worker is registered in UpdateNotice.tsx.
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
        // Icons generated with scripts/icons.mjs (white "K." from the logo on Baumann blue)
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
          { src: '/icons/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        ],
      },
      workbox: {
        // The API is never answered by the service worker with the app shell
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api(\/|$)/, /^\/bundles\//, /^\/_profiler/, /^\/_wdt/],
        // The whole app shell on the phone (precache), so the app starts without network.
        // Business data does not live here but later in IndexedDB (docs/pwa/offline.md).
        globPatterns: ['**/*.{js,css,html,svg,png,woff2,json}'],
        cleanupOutdatedCaches: true,
        // Take control of the open page on the very first visit: the app then works offline at once, and
        // later updates report reliably ("controlling"), so the page reloads once.
        // No skipWaiting: a new version waits until UpdateNotice.tsx releases it.
        clientsClaim: true,
      },
      // No service worker in the dev server, it would disturb HMR
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
