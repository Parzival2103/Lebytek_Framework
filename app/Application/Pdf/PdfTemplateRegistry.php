<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Exceptions\ValidationException;
use App\Domain\Pdf\PdfTemplateInterface;

/**
 * Whitelist clave → clase de plantilla. Misma política que los handlers del CRUD
 * Engine: jamás se acepta un FQCN proveniente de datos de usuario; solo claves que
 * el programador registró en config/pdf_templates.php.
 */
final class PdfTemplateRegistry
{
    /** @var array<string,class-string> */
    private array $map;

    /** @param array<string,string> $map clave => FQCN de PdfTemplateInterface */
    public function __construct(array $map)
    {
        $clean = [];
        foreach ($map as $key => $class) {
            $key = (string) $key;
            if ($key !== '' && is_string($class) && $class !== '') {
                $clean[$key] = $class;
            }
        }
        $this->map = $clean;
    }

    public function has(string $key): bool
    {
        return isset($this->map[$key]);
    }

    public function resolve(string $key): PdfTemplateInterface
    {
        $class = $this->map[$key] ?? null;
        if ($class === null) {
            throw new ValidationException("No existe la plantilla PDF '{$key}'.");
        }
        if (!class_exists($class)) {
            throw new ValidationException("La plantilla PDF '{$key}' apunta a una clase inexistente.");
        }
        $instance = new $class();
        if (!$instance instanceof PdfTemplateInterface) {
            throw new ValidationException("La plantilla PDF '{$key}' no implementa PdfTemplateInterface.");
        }
        return $instance;
    }
}
