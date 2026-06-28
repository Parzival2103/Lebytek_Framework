<?php

declare(strict_types=1);

use App\Kernel\Http\Response;

/**
 * Registra en Response los renderers HTML para 404, 403 y 500.
 * Las plantillas viven en Presentation/Views/errors; el registro se centraliza aquí
 * para que Kernel/Bootstrap no duplique rutas de vistas.
 */
function registerPresentationErrorRenderers(): void
{
    Response::setNotFoundRenderer(function (): string {
        ob_start();
        require APP_PATH . '/Presentation/Views/errors/404.php';
        return ob_get_clean();
    });

    Response::setForbiddenRenderer(function (): string {
        ob_start();
        require APP_PATH . '/Presentation/Views/errors/403.php';
        return ob_get_clean();
    });

    Response::setInternalErrorRenderer(function (): string {
        ob_start();
        require APP_PATH . '/Presentation/Views/errors/500.php';
        return ob_get_clean();
    });
}
