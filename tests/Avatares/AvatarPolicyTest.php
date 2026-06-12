<?php

declare(strict_types=1);

use App\Domain\Policies\AvatarPolicy;

test('AvatarPolicy permite al dueño aunque no tenga permiso de gestión', function (): void {
    $policy = new AvatarPolicy();
    assert_true($policy->puedeGestionar(actorId: 5, usuarioObjetivoId: 5, puedeGestionarUsuarios: false));
});

test('AvatarPolicy permite a otro actor con usuarios.gestionar', function (): void {
    $policy = new AvatarPolicy();
    assert_true($policy->puedeGestionar(actorId: 1, usuarioObjetivoId: 5, puedeGestionarUsuarios: true));
});

test('AvatarPolicy deniega a otro actor sin permiso', function (): void {
    $policy = new AvatarPolicy();
    assert_true(!$policy->puedeGestionar(actorId: 1, usuarioObjetivoId: 5, puedeGestionarUsuarios: false));
});
