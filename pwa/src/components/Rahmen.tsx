import { Outlet } from 'react-router';
import { Fusszeile } from './Fusszeile';
import { Kopf } from './Kopf';

/** Gemeinsamer Rahmen aller Seiten: blaue Kopfleiste, Inhalt, Fußzeile. */
export function Rahmen() {
  return (
    <div className="rahmen">
      <Kopf />
      <div className="rahmen-inhalt"><Outlet /></div>
      <Fusszeile />
    </div>
  );
}
