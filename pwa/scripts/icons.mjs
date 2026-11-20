// Erzeugt die App-Icons: weißes „K.“ aus dem Logo K. Baumann auf blauem Grund (--blau aus TempoVibe).
// Aufruf: node scripts/icons.mjs  → SVG-Quellen in public/icons/ und public/favicon.svg,
// danach die PNG-Dateien mit ImageMagick (siehe Ende der Datei). Die PNG-Dateien werden mit eingecheckt.
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';

const BLAU = '#0077C8';

// Bildmarke „K.“ aus src/components/Logo.tsx (Koordinaten des Original-Logos)
const K = `<path d="M97.1,94.4v62.93l21.49-45.5v-17.42h-21.49ZM133.04,94.4l-35.94,76.12v20.02h21.49l-.2-20.22,36.38-75.92h-21.72ZM138.77,141.47l-12.06,25.55,6.53,23.52h22.82l-17.29-49.07Z"/><polygon points="162.34 190.53 174.58 190.53 174.58 172.12 156.17 172.12 162.34 190.53"/>`;
const box = { x: 97.1, y: 94.4, b: 174.58 - 97.1, h: 190.53 - 94.4 };

/** Quadratisches Icon; anteil = Höhe des K im Verhältnis zur Kantenlänge */
function svg(groesse, anteil, rund = 0) {
  const s = (groesse * anteil) / box.h;
  const tx = groesse / 2 - (box.x + box.b / 2) * s;
  const ty = groesse / 2 - (box.y + box.h / 2) * s;
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${groesse}" height="${groesse}" viewBox="0 0 ${groesse} ${groesse}">`
    + `<rect width="${groesse}" height="${groesse}" rx="${rund}" fill="${BLAU}"/>`
    + `<g fill="#FFFFFF" transform="translate(${tx.toFixed(3)} ${ty.toFixed(3)}) scale(${s.toFixed(5)})">${K}</g></svg>\n`;
}

const ziel = new URL('../public/icons/', import.meta.url);
mkdirSync(ziel, { recursive: true });

// any: volle Fläche, das System rundet selbst. maskable: K im sicheren Kreis (80 %), daher kleiner.
const dateien = {
  'icon.svg': svg(512, 0.56),
  'icon-maskable.svg': svg(512, 0.42),
};
for (const [name, inhalt] of Object.entries(dateien)) writeFileSync(new URL(name, ziel), inhalt);
writeFileSync(new URL('../public/favicon.svg', import.meta.url), svg(32, 0.62, 7));

// PNG-Dateien (Android, iPhone). Ohne ImageMagick bleiben nur die SVG-Quellen.
const png = [
  ['icon.svg', 'icon-192.png', 192],
  ['icon.svg', 'icon-512.png', 512],
  ['icon-maskable.svg', 'icon-maskable-512.png', 512],
  ['icon.svg', 'apple-touch-icon.png', 180],
];
try {
  for (const [quelle, name, px] of png) {
    execFileSync('convert', ['-background', 'none', '-density', '300', new URL(quelle, ziel).pathname,
      '-resize', `${px}x${px}`, '-strip', new URL(name, ziel).pathname]);
  }
  console.log('Icons erzeugt in public/icons/');
} catch (e) {
  console.warn('ImageMagick (convert) fehlt oder schlug fehl, PNG-Dateien nicht erzeugt:', e.message);
}
