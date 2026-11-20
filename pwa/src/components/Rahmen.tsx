import { Outlet } from 'react-router';
import { Aktualisierung } from './Aktualisierung';
import { Fusszeile } from './Fusszeile';
import { Kopf } from './Kopf';

/** Gemeinsamer Rahmen aller Seiten: blaue Kopfleiste, Hinweis auf neue Version, Inhalt, Fußzeile. */
export function Rahmen() {
  return (
    <div className="rahmen">
      <Kopf />
      <Aktualisierung />
      <div className="rahmen-inhalt"><Outlet /></div>
      <Fusszeile />
    </div>
  );
}
