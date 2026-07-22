# Package root layout

Este repositorio es la **fuente del paquete** `lebytek/framework`, no el sitio lebytek.com.

| Path | Uso |
|------|-----|
| `src/` | Código del paquete |
| `skeleton/` | Plantilla mínima para nuevos tenants |
| `database/`, `scripts/` | SQL/scripts de plataforma shippeados en el paquete |
| `config/`, `public/`, stub `app/` | **Harness de tests / smoke local del mantenedor** |
| Portal | Repo hermano `Lebytek_Portal` |

**Política:** este árbol **no se despliega** en VPS. Deploy = Portal (o tenant desde skeleton) + `composer install`.

Deuda aceptada A7: el harness permanece hasta CI contra `skeleton/` instalado.
