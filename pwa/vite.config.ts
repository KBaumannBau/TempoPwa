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
const httpPort = Number(process.env.HTTP_PORT ?? 8001);

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify(appVersion()),
  },
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      injectRegister: 'auto',
      includeAssets: ['favicon.svg'],
      manifest: {
        name: 'Tempo – Stundenerfassung K. Baumann Bau',
        short_name: 'Tempo',
        description: 'Stundenerfassung für Mitarbeiter, Polier, Bauleitung und Büro',
        lang: 'de',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#ffffff',
        theme_color: '#1f3a5f',
        icons: [{ src: '/favicon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' }],
      },
      workbox: {
        // Die API wird nie vom Service Worker als App-Shell beantwortet
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api(\/|$)/, /^\/bundles\//, /^\/_profiler/, /^\/_wdt/],
        globPatterns: ['**/*.{js,css,html,svg,png,woff2,json}'],
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
