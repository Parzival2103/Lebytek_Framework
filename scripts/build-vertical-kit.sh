#!/usr/bin/env bash
#
# build-vertical-kit.sh
# -----------------------------------------------------------------------------
# Empaqueta en ./vertical-kit/ todo lo que una IA externa necesita para generar
# un vertical de negocio (T4, dom_*) sobre el Lebytek Framework:
#   - CONTEXTO  : docs + schema base (la IA lo lee, NO lo replica)
#   - PLANTILLAS: archivos demo que la IA clona y adapta por vertical
#
# Uso:
#   bash scripts/build-vertical-kit.sh            # genera ./vertical-kit
#   bash scripts/build-vertical-kit.sh /ruta/out  # genera en otra carpeta
#
# El kit es autosuficiente: incluye su propio BRIEF-IA.md con instrucciones.
# -----------------------------------------------------------------------------
set -euo pipefail

# Raíz del repo = carpeta padre de este script.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/vertical-kit}"

echo ">> Repo:   $ROOT"
echo ">> Salida: $OUT"

# --- Archivos a copiar (origen relativo a $ROOT) ----------------------------
CONTEXTO=(
  "CLAUDE.md"
  "docs/modules/uso-de-modulo-dominio.md"
  "docs/core/despliegue-y-versionado.md"
  "docs/core/vertical-onboarding.md"
  "docs/core/table-prefix-convention.md"
  "docs/core/convenciones_nombres.md"
  "docs/core/arquitectura.md"
  "docs/core/schema-code-map.md"
  "docs/modules/modulo-menu.md"
  "docs/modules/modulo-calendario.md"
  "database/schema/schema.sql"
  "database/migrations/README.md"
)

PLANTILLAS=(
  "database/schema/modules/crud-engine.sql"
  "database/schema/modules/reportes.sql"
  "database/schema/modules/calendario.sql"
  "config/modules/crud-engine.php"
  "config/crud_handlers.php"
  "config/cruds/demo_clientes.json"
  "config/cruds/demo_productos.json"
  "config/cruds/demo_pedidos.json"
  "config/cruds/demo_categorias.json"
  "config/cruds/demo_citas.json"
  "config/calendars/demo_citas.json"
  "config/reportes/clientes.json"
  "config/reportes/pedidos.json"
  "config/reportes/productos.json"
  "config/reportes/citas.json"
  "app/Application/Crud/Handlers/AbstractCrudHookHandler.php"
  "app/Application/Crud/Handlers/DemoProductoToggleStatusHandler.php"
  "app/Application/Crud/Handlers/DemoProductoStateGuard.php"
  "app/Application/Crud/Handlers/DemoPedidoTotalValidator.php"
  "app/Application/Crud/Handlers/DemoPedidoPagarGuard.php"
  "app/Application/Crud/Handlers/DemoClienteContactoValidator.php"
  "app/Application/Pdf/Templates/DemoReporteTemplate.php"
)

# --- Copiado preservando estructura de rutas --------------------------------
copy_into() {
  local subdir="$1"; shift
  local missing=0
  for rel in "$@"; do
    local src="$ROOT/$rel"
    local dst="$OUT/$subdir/$rel"
    if [ -e "$src" ]; then
      mkdir -p "$(dirname "$dst")"
      cp "$src" "$dst"
      echo "   + $subdir/$rel"
    else
      echo "   ! FALTA (omitido): $rel"
      missing=$((missing + 1))
    fi
  done
  return 0
}

rm -rf "$OUT"
mkdir -p "$OUT/contexto" "$OUT/plantillas"

echo ">> Copiando CONTEXTO..."
copy_into "contexto" "${CONTEXTO[@]}"

echo ">> Copiando PLANTILLAS..."
copy_into "plantillas" "${PLANTILLAS[@]}"

# --- Brief para la IA -------------------------------------------------------
cp "$ROOT/docs/core/BRIEF-IA-vertical.md" "$OUT/BRIEF-IA.md" 2>/dev/null \
  || echo "   ! No se encontró docs/core/BRIEF-IA-vertical.md (cópialo manualmente)."

# --- Manifiesto del kit -----------------------------------------------------
{
  echo "# Vertical Kit"
  echo
  echo "Generado: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "Origen:   Lebytek Framework"
  echo
  echo "## Estructura"
  echo '```'
  echo "BRIEF-IA.md        <- LÉEME PRIMERO: instrucciones para generar el vertical"
  echo "contexto/          <- referencia (la IA lo LEE, no lo replica)"
  echo "plantillas/        <- archivos demo a CLONAR y adaptar"
  echo '```'
} > "$OUT/README.md"

echo ">> Listo. Kit en: $OUT"
echo ">> Comprime con: (cd \"$(dirname "$OUT")\" && zip -r vertical-kit.zip \"$(basename "$OUT")\")"
