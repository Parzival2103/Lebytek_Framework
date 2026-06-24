# Patrón: conector de integración (API / canal externo)

> **Referencia, no plantilla.** No se clona. Lee este patrón para entender cómo el
> framework añade integraciones externas desacopladas, y planea la tuya imitando la
> estructura. Ejemplo trabajado: módulo `integrations` (Green API WhatsApp).

## Cuándo usar este patrón

- Necesitas **enviar mensajes / consumir una API externa / recibir webhooks** desde
  un vertical, sin acoplar el dominio concreto al core.

## Piezas (ejemplo: módulo integrations)

- Connector HTTP genérico:
  `app/Infrastructure/Integrations/Http/HttpApiConnector.php`.
- Canales (estrategia por proveedor):
  `app/Infrastructure/Integrations/Channels/EmailChannel.php`,
  `GreenApiWhatsappChannel.php`.
- Partner connector (alta/gestión de cuentas del proveedor):
  `app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php`.
- Cliente de cuenta:
  `app/Infrastructure/Integrations/GreenApi/GreenApiAccountClient.php`.
- Repositorios de cuenta y log:
  `app/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php`,
  `IntegrationLogRepository.php`.
- Settings provider (configuración por instancia):
  `app/Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider.php`.

## Cableado (plataforma)

- Manifiesto `config/modules/integrations.php`: `permisos`
  (`integrations.ver`, `integrations.enviar`, `integrations.configurar`),
  `bootstrap_sql` = `database/schema/modules/integrations.sql`.

## Desacople (regla clave)

El **dominio concreto** (p. ej. WhatsApp para un vertical) vive como **datos / demo
/ seeds**, NO como lógica acoplada al core. El conector expone canales genéricos; el
vertical configura cuál usa. Coherente con el contrato de `docs/modules/uso-de-modulo-dominio.md`.
