# Integración api.lebytek.com

Documentos copiados desde **WhatsApiLebytek** (`api.lebytek.com`). La fuente de verdad del contrato sigue en el repo api; aquí se implementa el consumidor (back-office lebytek.com).

| Archivo | Uso en este repo |
|---------|------------------|
| `waapi-api-contract.md` | Contrato HTTP v1 (endpoints, headers, tokens) |
| `waapi-implementation-real.md` | Guía operativa: cliente PHP, migraciones, flujo leads |
| `role-delegation-waapi.md` | Qué hace api vs back-office |
| `prompt2-review-pre-waapi.md` | Auditoría del núcleo api (referencia) |
| `VPS_CHECKLIST.md` | Checklist deploy api + sitio |

## Variables `.env`

```env
LEBYTEK_API_URL=https://api.lebytek.com/api/v1
LEBYTEK_API_TOKEN=          # php artisan integration:issue-waapi-token en api VPS
```

## Próximo paso de implementación (branch actual)

1. `LebytekApiClient` en `app/Infrastructure/Integrations/`
2. Columnas `api_tenant_public_id`, `external_ref` en org/leads
3. Orquestar `POST /tenants` al aprobar lead
4. Fase 2: `POST /instances` vía contrato api
