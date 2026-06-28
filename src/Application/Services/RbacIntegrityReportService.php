<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\MenuCatalogRepositoryInterface;
use App\Domain\Interfaces\PermisoRepositoryInterface;
use App\Domain\Rules\PermisoSlugFormatRule;

/**
 * Reporte estático de coherencia entre permisos en BD, menú y rutas/CRUD declarados.
 * No modifica datos.
 */
final class RbacIntegrityReportService
{
    public function __construct(
        private readonly PermisoRepositoryInterface $permisoRepo,
        private readonly MenuCatalogRepositoryInterface $menuCatalogRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generarInforme(): array
    {
        $slugsEnBd    = $this->permisoRepo->listarTodosLosSlugs();
        $slugsSet     = array_fill_keys($slugsEnBd, true);
        $activoPorSlug = $this->permisoRepo->mapSlugActivo();
        $menuSlugs    = $this->menuCatalogRepository->listarSlugsPermisoReferenciadosEnMenu();
        $rutaCfg      = $this->cargarPermisosRutaEsperados();
        $crudEsperados = $this->slugsEsperadosDesdeCrudConfigs();

        $menuSinRegistro = array_values(array_filter(
            $menuSlugs,
            static fn(string $s): bool => ! isset($slugsSet[$s])
        ));

        $rutaSinRegistro = array_values(array_filter(
            $rutaCfg,
            static fn(string $s): bool => ! isset($slugsSet[$s])
        ));

        $crudSinRegistro = array_values(array_filter(
            $crudEsperados,
            static fn(string $s): bool => ! isset($slugsSet[$s])
        ));

        $esperadosUso = array_fill_keys([...$menuSlugs, ...$rutaCfg, ...$crudEsperados], true);
        $sinUsoClaro  = array_values(array_filter(
            $slugsEnBd,
            static fn(string $s): bool => ! isset($esperadosUso[$s])
        ));

        $slugMalFormado = [];
        $legacyPatrones = ['catalogo.', 'entregas.'];
        $clasificacion  = [];
        foreach ($slugsEnBd as $slug) {
            if (! PermisoSlugFormatRule::isValid($slug)) {
                $slugMalFormado[] = $slug;
                $clasificacion[$slug] = 'requiere_revision';
                continue;
            }
            $activo = (int) ($activoPorSlug[$slug] ?? 1);
            if ($activo === 0) {
                $clasificacion[$slug] = 'deprecated';
                continue;
            }
            $legacy = false;
            foreach ($legacyPatrones as $pref) {
                if (str_starts_with($slug, $pref)) {
                    $legacy = true;
                    break;
                }
            }
            if ($legacy) {
                $clasificacion[$slug] = 'legacy_detectado';
            } elseif (str_starts_with($slug, 'demo_')) {
                $clasificacion[$slug] = 'vigente';
            } elseif (in_array($slug, ['administracion.ver', 'usuarios.gestionar', 'roles.gestionar', 'dashboard.ver', 'bitacora.ver'], true)) {
                $clasificacion[$slug] = 'vigente';
            } elseif (isset($esperadosUso[$slug])) {
                $clasificacion[$slug] = 'vigente';
            } else {
                $clasificacion[$slug] = 'sin_uso_confirmado';
            }
        }

        return [
            'total_slugs_en_bd'           => count($slugsEnBd),
            'menu_slugs_huerfanos'        => $menuSinRegistro,
            'ruta_config_sin_registro'    => $rutaSinRegistro,
            'crud_json_sin_registro'      => $crudSinRegistro,
            'slugs_posiblemente_sin_uso'  => $sinUsoClaro,
            'slugs_formato_invalido'     => $slugMalFormado,
            'clasificacion_por_slug'      => $clasificacion,
        ];
    }

    /**
     * @return list<string>
     */
    private function cargarPermisosRutaEsperados(): array
    {
        $root = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 3);
        $path = $root . '/config/rbac_route_permissions.php';
        if (! is_readable($path)) {
            return [];
        }
        /** @var mixed $cfg */
        $cfg = require $path;
        if (! is_array($cfg) || ! isset($cfg['middleware']) || ! is_array($cfg['middleware'])) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $cfg['middleware'])));
    }

    /**
     * @return list<string>
     */
    private function slugsEsperadosDesdeCrudConfigs(): array
    {
        $root = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 3);
        $dir  = $root . '/config/cruds';
        if (! is_dir($dir)) {
            return [];
        }
        $slugs = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }
            /** @var mixed $data */
            $data = json_decode($json, true);
            if (! is_array($data)) {
                continue;
            }
            $prefix = $data['resource']['permission_prefix'] ?? null;
            if (! is_string($prefix) || $prefix === '') {
                continue;
            }
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $acc) {
                $slugs[] = $prefix . '.' . $acc;
            }
        }

        return array_values(array_unique($slugs));
    }
}
