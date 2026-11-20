/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Adresse der API, wenn sie nicht auf demselben Origin liegt (z. B. https://api.example.de). Leer = gleicher Origin. */
  readonly VITE_API_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

/** Version der App (aus VERSION bzw. der Pipeline), eingesetzt von Vite beim Bauen */
declare const __APP_VERSION__: string;
