<?php

declare(strict_types=1);

use App\Application\Crud\Context\CrudActionContext;
use App\Application\Crud\Context\CrudListContext;
use App\Application\Crud\Context\CrudTransitionContext;
use App\Application\Crud\Context\CrudValidationContext;
use App\Domain\Interfaces\CrudActionHandlerInterface;
use App\Domain\Interfaces\CrudListScopeInterface;
use App\Domain\Interfaces\CrudTransitionGuardInterface;
use App\Domain\Interfaces\CrudValidatorInterface;

test('segregated interfaces: a class can implement all four', function (): void {
    $impl = new class implements
        CrudActionHandlerInterface,
        CrudTransitionGuardInterface,
        CrudValidatorInterface,
        CrudListScopeInterface {
        public bool $called = false;
        public function handle(CrudActionContext $ctx): void { $this->called = true; }
        public function authorize(CrudTransitionContext $ctx): void {}
        public function validate(CrudValidationContext $ctx): void {}
        public function apply(CrudListContext $ctx): void {}
    };

    assert_true($impl instanceof CrudActionHandlerInterface);
    assert_true($impl instanceof CrudTransitionGuardInterface);
    assert_true($impl instanceof CrudValidatorInterface);
    assert_true($impl instanceof CrudListScopeInterface);

    $impl->handle(new CrudActionContext('r', 't', 'id', 1, '', 1, null, 'a', []));
    assert_true($impl->called, 'handle ran');
});
