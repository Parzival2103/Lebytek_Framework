# Assets plataforma — checklist skeleton

**Plan:** FPS 04 — Skeleton mínimo  
**Política:** ViewHelper::asset no lee vendor; el consumidor copia esta lista al desplegar.

## Archivos obligatorios

| Path | Rol |
|------|-----|
| `public/assets/css/app.css` | Shell admin |
| `public/assets/css/lebytek-ui.css` | Design system |
| `public/assets/css/crud-engine.css` | CRUD Engine UI |
| `public/assets/js/app.js` | Shell JS |
| `public/assets/js/crud-engine.js` | CRUD Engine JS |
| `public/assets/js/calendar.js` | Módulo calendario |
| `public/assets/js/avatar-manager.js` | Perfil/admin |
| `public/assets/js/reportes-builder.js` | Reportes |
| `public/assets/icons/app-icon.svg` | PWA icon |
| `public/assets/images/logo.png` | Branding default |

## Excluidos del skeleton

- `public/assets/publico/**` — landing Portal (Plan 05)

## Verificación

`php tests/run.php SkeletonPurity` — test `skeleton ships required platform UI assets`.
