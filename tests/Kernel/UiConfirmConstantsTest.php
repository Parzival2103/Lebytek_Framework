<?php

declare(strict_types=1);

use App\Kernel\Constants\UiConfirmConstants;

test('UiConfirmConstants define textos de logout', function (): void {
    assert_same('Cerrar sesión', UiConfirmConstants::LOGOUT_TITLE);
    assert_same('¿Deseas cerrar la sesión actual?', UiConfirmConstants::LOGOUT_BODY);
    assert_same('Cerrar sesión', UiConfirmConstants::LOGOUT_OK);
    assert_same('warning', UiConfirmConstants::LOGOUT_ICON);
});

test('UiConfirmConstants mantiene defaults existentes', function (): void {
    assert_same('Confirmar acción', UiConfirmConstants::DEFAULT_TITLE);
    assert_same('Confirmar', UiConfirmConstants::DEFAULT_OK);
    assert_same('Cancelar', UiConfirmConstants::DEFAULT_CANCEL);
});
