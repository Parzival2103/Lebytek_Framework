<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Http\Response;
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/**
 * Registra en Response los renderers HTML para 404, 403 y 500.
 * Las plantillas viven en Presentation/Views/errors; el registro se centraliza aquí
 * para que Kernel/Bootstrap no duplique rutas de vistas.
 */
function registerPresentationErrorRenderers(): void
{
    Response::setNotFoundRenderer(function (): string {
        ob_start();
        require ViewHelper::resolve('errors/404');
        return ob_get_clean();
    });

    Response::setForbiddenRenderer(function (): string {
        ob_start();
        require ViewHelper::resolve('errors/403');
        return ob_get_clean();
    });

    Response::setInternalErrorRenderer(function (): string {
        ob_start();
        require ViewHelper::resolve('errors/500');
        return ob_get_clean();
    });
}
