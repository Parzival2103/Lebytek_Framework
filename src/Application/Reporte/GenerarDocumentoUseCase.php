<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

use Lebytek\Framework\Application\Pdf\PdfRenderingService;
use Lebytek\Framework\Application\Pdf\PdfTemplateRegistry;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Reporte\ReporteRecordSourceInterface;
use Lebytek\Framework\Kernel\Config\Config;

/**
 * Modo registro: valida fuente+plantilla, lee el registro con scope y renderiza PDF.
 */
final class GenerarDocumentoUseCase
{
    public function __construct(
        private readonly ReporteConfigLoader $loader,
        private readonly ReporteRecordSourceInterface $source,
        private readonly PdfTemplateRegistry $registry,
        private readonly ?PdfRenderingService $pdf = null,
    ) {}

    /**
     * @return array<string,mixed>|null
     * @throws ValidationException
     */
    public function buildPayload(string $fuenteKey, int $id, string $templateKey, ?int $userId, callable $can): ?array
    {
        $fuente = $this->loader->load($fuenteKey);

        if (!in_array($templateKey, $fuente->templatesFor('registro'), true)) {
            throw new ValidationException("La plantilla '{$templateKey}' no está disponible para registro en esta fuente.");
        }
        if (!$this->registry->has($templateKey)) {
            throw new ValidationException("La plantilla PDF '{$templateKey}' no existe.");
        }
        if (!$this->registry->resolve($templateKey)->supports('registro')) {
            throw new ValidationException("La plantilla '{$templateKey}' no soporta el modo registro.");
        }

        $definition = $this->loader->crudDefinition($fuente->resource());
        $found = $this->source->findRecord($definition, $id, $userId, $can, $fuente->relationNames());
        if ($found === null) {
            return null;
        }

        return [
            'orientation' => 'portrait',
            'title'       => $fuente->title(),
            'record'      => is_array($found['record'] ?? null) ? $found['record'] : [],
            'relations'   => is_array($found['relations'] ?? null) ? $found['relations'] : [],
        ];
    }

    /**
     * @return string|null bytes del PDF
     * @throws ValidationException
     */
    public function generar(string $fuenteKey, int $id, string $templateKey, ?int $userId, callable $can): ?string
    {
        $payload = $this->buildPayload($fuenteKey, $id, $templateKey, $userId, $can);
        if ($payload === null) {
            return null;
        }
        if ($this->pdf === null) {
            throw new \LogicException('PdfRenderingService es obligatorio para generar().');
        }

        $payload['marca'] = $this->marca();
        return $this->pdf->renderTemplate($templateKey, $payload);
    }

    /** @return array<string,mixed> */
    private function marca(): array
    {
        $marca = Config::get('pdf.marca', []);
        return is_array($marca) ? $marca : [];
    }
}
