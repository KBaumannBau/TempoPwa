// Generates the app icons: white "K." from the K. Baumann logo on a blue background (--blue from TempoVibe).
// Usage: node scripts/icons.mjs  → SVG sources in public/icons/ and public/favicon.svg,
// then the PNG files with ImageMagick (see end of file). The PNG files are checked in.
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';

const BLUE = '#0077C8';

// Mark "K." from src/components/Logo.tsx (coordinates of the original logo)
const K = `<path d="M97.1,94.4v62.93l21.49-45.5v-17.42h-21.49ZM133.04,94.4l-35.94,76.12v20.02h21.49l-.2-20.22,36.38-75.92h-21.72ZM138.77,141.47l-12.06,25.55,6.53,23.52h22.82l-17.29-49.07Z"/><polygon points="162.34 190.53 174.58 190.53 174.58 172.12 156.17 172.12 162.34 190.53"/>`;
const box = { x: 97.1, y: 94.4, w: 174.58 - 97.1, h: 190.53 - 94.4 };

/** Square icon; share = height of the K relative to the edge length */
function svg(size, share, radius = 0) {
  const s = (size * share) / box.h;
  const tx = size / 2 - (box.x + box.w / 2) * s;
  const ty = size / 2 - (box.y + box.h / 2) * s;
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">`
    + `<rect width="${size}" height="${size}" rx="${radius}" fill="${BLUE}"/>`
    + `<g fill="#FFFFFF" transform="translate(${tx.toFixed(3)} ${ty.toFixed(3)}) scale(${s.toFixed(5)})">${K}</g></svg>\n`;
}

const target = new URL('../public/icons/', import.meta.url);
mkdirSync(target, { recursive: true });

// any: full area, the system rounds itself. maskable: K inside the safe circle (80 %), hence smaller.
const files = {
  'icon.svg': svg(512, 0.56),
  'icon-maskable.svg': svg(512, 0.42),
};
for (const [name, content] of Object.entries(files)) writeFileSync(new URL(name, target), content);
writeFileSync(new URL('../public/favicon.svg', import.meta.url), svg(32, 0.62, 7));

// PNG files (Android, iPhone). Without ImageMagick only the SVG sources remain.
const png = [
  ['icon.svg', 'icon-192.png', 192],
  ['icon.svg', 'icon-512.png', 512],
  ['icon-maskable.svg', 'icon-maskable-512.png', 512],
  ['icon.svg', 'apple-touch-icon.png', 180],
];
try {
  for (const [source, name, px] of png) {
    execFileSync('convert', ['-background', 'none', '-density', '300', new URL(source, target).pathname,
      '-resize', `${px}x${px}`, '-strip', new URL(name, target).pathname]);
  }
  console.log('Icons generated in public/icons/');
} catch (e) {
  console.warn('ImageMagick (convert) missing or failed, PNG files not generated:', e.message);
}
