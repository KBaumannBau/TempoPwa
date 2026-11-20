Erzeugt aus dem OpenAPI-Schema der API – **nicht von Hand ändern**.

    docker compose exec pwa npm run api:types

Quelle: `http://nginx:8001/api/docs.jsonopenapi` (im Docker-Netz, `API_SCHEMA_URL` überschreibt).
Die erzeugte `schema.d.ts` wird versioniert, damit `npm run build` ohne laufende API funktioniert.
