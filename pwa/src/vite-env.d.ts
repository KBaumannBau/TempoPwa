/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Adresse der API, wenn sie nicht auf demselben Origin liegt (z. B. https://api.example.de). Leer = gleicher Origin. */
  readonly VITE_API_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
