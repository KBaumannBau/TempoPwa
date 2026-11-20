import { useEffect } from 'react';

// Ist die App gerade „ruhig“, also darf eine neue Version übernommen werden (Seite lädt dabei neu)?
// Unruhig ist sie, solange ein Bildschirm etwas in Arbeit hat (z. B. „Tag prüfen und senden“) und,
// später, solange der Ausgang nicht leer ist (docs/pwa/offline.md, Abschnitt 7).

const beschaeftigt = new Set<symbol>();
let ausgangLeer: () => boolean = () => true;

/** Ein Bildschirm meldet: Hier wird gerade gearbeitet, bitte jetzt nicht neu laden. */
export function useBeschaeftigt(aktiv: boolean) {
  useEffect(() => {
    if (!aktiv) return;
    const ich = Symbol('beschaeftigt');
    beschaeftigt.add(ich);
    return () => { beschaeftigt.delete(ich); };
  }, [aktiv]);
}

/** Der Abgleich (pwa/src/sync/, kommt mit Schritt 2) meldet hier, ob noch Einträge im Ausgang stehen. */
export function ausgangPruefenMit(pruefen: () => boolean) {
  ausgangLeer = pruefen;
}

export function istRuhig(): boolean {
  return beschaeftigt.size === 0 && ausgangLeer();
}
