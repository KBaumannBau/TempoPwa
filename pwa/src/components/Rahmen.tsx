import { Outlet } from 'react-router';
import { Fusszeile } from './Fusszeile';

/** Gemeinsamer Rahmen aller Seiten (vorläufig: Inhalt und Fußzeile). */
export function Rahmen() {
  return (
    <div className="rahmen">
      <div className="rahmen-inhalt"><Outlet /></div>
      <Fusszeile />
    </div>
  );
}
