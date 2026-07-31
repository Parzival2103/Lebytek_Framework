# Seguridad de secretos y checklist de despliegue (VPS)

> **Guía operativa de despliegue:** este documento es la fuente autoritativa de secretos y rotación; el contexto de despliegue completo está en [`despliegue-y-versionado.md`](despliegue-y-versionado.md).

**Regla:** En el repositorio solo vive `.env.example`. `.env` jamás se versiona.
El VPS de producción Portal hace auto-pull de `main` en **`Lebytek_Portal`**; cualquier secreto commiteado se considera comprometido.

### Portal vs package source (post-FPS)

- **lebytek.com / waapi.lebytek.com:** secretos de producción en el `.env` del VPS del checkout **`Lebytek_Portal`** (`DB_*`, `MKT_*`, `LEBYTEK_API_*`, `STRIPE_*`). Rotación y deploy siguen el runbook Portal — ver [`docs/ENVIRONMENTS.md`](../ENVIRONMENTS.md).
- **Este repositorio (`Lebytek_Framework`):** es el **package source** publicado como `lebytek/framework`; su root `.env.example` es harness de mantenedor, **no** el `.env` de producción Portal. No desplegar este repo como sitio web.

## Rotación obligatoria si `.env` estuvo alguna vez en git

1. Rotar `DB_PASSWORD` en el motor de base de datos y en el `.env` del VPS.
2. Regenerar `APP_KEY` (32+ caracteres aleatorios).
3. Revisar `git log -- .env` para confirmar si hubo exposición histórica.
   - Si hubo push a remoto: rotar TODO secreto presente en esos commits.
4. (Opcional, si el remoto es privado y se requiere limpieza histórica) purgar
   `.env` del historial con `git filter-repo`. Coordinar antes: reescribe SHAs.

## Checklist antes de cada despliegue a producción

- [ ] `APP_ENV=production` en el `.env` del servidor.
- [ ] `APP_DEBUG=false` (además, el código fuerza `false` cuando `APP_ENV=production`).
- [ ] `SESSION_SECURE=true` (cookies solo por HTTPS).
- [ ] `APP_KEY` único por entorno (no el de `.env.example`).
- [ ] `DB_PASSWORD` rotado respecto a desarrollo.
- [ ] `MAX_UPLOAD_MB` acorde al límite real del servidor PHP (`upload_max_filesize`).
- [ ] `git ls-files .env` no devuelve nada.
