/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Address of the API if it is not on the same origin (e.g. https://api.example.de). Empty = same origin. */
  readonly VITE_API_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

/** Version of the app (from VERSION or the pipeline), inserted by Vite at build time */
declare const __APP_VERSION__: string;
