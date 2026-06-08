<?php

use App\Kernel\Container\Container;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\Interfaces\PermisoRepositoryInterface;
use App\Domain\Interfaces\ConfiguracionRepositoryInterface;
use App\Infrastructure\Repositories\UsuarioRepository;
use App\Infrastructure\Repositories\RolRepository;
use App\Infrastructure\Repositories\PermisoRepository;
use App\Infrastructure\Repositories\ConfiguracionRepository;
use App\Application\Services\ConfiguracionService;
use App\Application\Services\AuthService;
use App\Application\Services\RbacService;
use App\Application\Validators\Usuarios\CrearUsuarioValidator;
use App\Application\Validators\Auth\LoginValidator;
use App\Application\UseCases\Usuarios\CrearUsuarioUseCase;
use App\Application\UseCases\Usuarios\ListarUsuariosUseCase;
use App\Application\UseCases\Usuarios\ActualizarUsuarioUseCase;
use App\Application\UseCases\Usuarios\EliminarUsuarioUseCase;
use App\Application\UseCases\Roles\CrearRolUseCase;
use App\Application\UseCases\Roles\ListarRolesUseCase;
use App\Application\UseCases\Roles\ActualizarRolUseCase;
use App\Application\UseCases\Roles\EliminarRolUseCase;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\UseCases\Auth\LogoutUseCase;
use App\Application\UseCases\Dashboard\BuildDashboardViewModelUseCase;
use App\Domain\Interfaces\DashboardContributionProviderInterface;
use App\Domain\Interfaces\MenuCatalogRepositoryInterface;
use App\Infrastructure\Repositories\MenuCatalogRepository;
use App\Application\Services\AdminNavigationMenuService;
use App\Application\Services\RbacIntegrityReportService;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Infrastructure\Repositories\BitacoraRepository;
use App\Infrastructure\Repositories\GenericCrudRepository;
use App\Application\Services\CrudActionResolver;
use App\Application\Services\CrudActionService;
use App\Application\Services\CrudConfigLoader;
use App\Application\Services\CrudConfigValidator;
use App\Application\Services\CrudDataService;
use App\Application\Services\CrudDbConstraintValidator;
use App\Application\Services\CrudFieldValidationService;
use App\Application\Services\CrudFormBuilder;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudRelationService;
use App\Application\Services\CrudHookRunner;
use App\Application\Services\CrudResourceService;
use App\Application\Services\CrudTableBuilder;
use App\Application\Services\CrudTransitionService;

return static function (Container $container): void {

    $container->singleton(RbacIntegrityReportService::class, static fn(Container $c) => new RbacIntegrityReportService(
        $c->get(PermisoRepositoryInterface::class),
        $c->get(MenuCatalogRepositoryInterface::class)
    ));

    $container->singleton(UsuarioRepositoryInterface::class,   fn() => new UsuarioRepository());
    $container->singleton(RolRepositoryInterface::class,       fn() => new RolRepository());
    $container->singleton(PermisoRepositoryInterface::class,   fn() => new PermisoRepository());
    $container->singleton(ConfiguracionRepositoryInterface::class, fn() => new ConfiguracionRepository());

    $container->singleton(ConfiguracionService::class, fn(Container $c) =>
        new ConfiguracionService($c->get(ConfiguracionRepositoryInterface::class))
    );

    $container->singleton(MenuCatalogRepositoryInterface::class, static fn() => new MenuCatalogRepository());

    $container->singleton(AdminNavigationMenuService::class, static fn(Container $c) => new AdminNavigationMenuService(
        $c->get(MenuCatalogRepositoryInterface::class)
    ));

    $container->singleton(AuthService::class, fn(Container $c) =>
        new AuthService(
            $c->get(UsuarioRepositoryInterface::class),
            $c->get(PermisoRepositoryInterface::class),
            $c->get(RolRepositoryInterface::class)
        )
    );

    $container->singleton(RbacService::class, fn() => new RbacService());
    $container->singleton(BitacoraRepositoryInterface::class, fn() => new BitacoraRepository());
    $container->singleton(GenericCrudRepository::class, fn() => new GenericCrudRepository());
    $container->singleton(CrudHandlerRegistry::class, static function (): CrudHandlerRegistry {
        /** @var array<string, class-string> $map */
        $map = require ROOT_PATH . '/config/crud_handlers.php';
        return new CrudHandlerRegistry(is_array($map) ? $map : []);
    });
    $container->singleton(CrudConfigValidator::class, fn(Container $c) => new CrudConfigValidator(
        $c->get(GenericCrudRepository::class),
        $c->get(CrudHandlerRegistry::class)
    ));
    $container->singleton(CrudConfigLoader::class, fn(Container $c) => new CrudConfigLoader(
        $c->get(CrudConfigValidator::class)
    ));
    $container->singleton(CrudHookRunner::class, fn(Container $c) => new CrudHookRunner(
        $c->get(CrudHandlerRegistry::class)
    ));
    $container->singleton(CrudFieldValidationService::class, fn() => new CrudFieldValidationService());
    $container->singleton(CrudDbConstraintValidator::class, fn(Container $c) => new CrudDbConstraintValidator(
        $c->get(GenericCrudRepository::class)
    ));
    $container->singleton(CrudDataService::class, fn(Container $c) => new CrudDataService(
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class),
        $c->get(CrudFieldValidationService::class),
        $c->get(CrudDbConstraintValidator::class),
        $c->get(CrudHandlerRegistry::class)
    ));
    $container->singleton(CrudRelationService::class, fn(Container $c) => new CrudRelationService(
        $c->get(GenericCrudRepository::class)
    ));
    $container->singleton(CrudFormBuilder::class, fn(Container $c) => new CrudFormBuilder(
        $c->get(CrudRelationService::class)
    ));
    $container->singleton(CrudTableBuilder::class, fn() => new CrudTableBuilder());
    $container->singleton(CrudActionResolver::class, fn() => new CrudActionResolver());
    $container->singleton(CrudTransitionService::class, fn(Container $c) => new CrudTransitionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class)
    ));
    $container->singleton(CrudActionService::class, fn(Container $c) => new CrudActionService(
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudActionResolver::class),
        $c->get(RbacService::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudTransitionService::class)
    ));
    $container->singleton(CrudResourceService::class, fn(Container $c) => new CrudResourceService(
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudFormBuilder::class),
        $c->get(CrudTableBuilder::class),
        $c->get(RbacService::class),
        $c->get(CrudActionResolver::class),
        $c->get(CrudActionService::class)
    ));

    foreach ((require ROOT_PATH . '/config/dashboard.php')['providers'] as $fqcnProvider) {
        if (!$container->has($fqcnProvider)) {
            $fqcn = $fqcnProvider;
            $container->singleton($fqcn, static function () use ($fqcn) {
                return new $fqcn();
            });
        }
    }

    $container->singleton(BuildDashboardViewModelUseCase::class, function (Container $c) {
        $cfg       = require ROOT_PATH . '/config/dashboard.php';
        $providers = [];
        foreach ($cfg['providers'] as $fqcn) {
            /** @var DashboardContributionProviderInterface $p */
            $p            = $c->get($fqcn);
            $providers[]  = $p;
        }

        return new BuildDashboardViewModelUseCase($providers);
    });

    $container->bind(\App\Presentation\Controllers\Admin\DashboardController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\DashboardController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(BuildDashboardViewModelUseCase::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\PermisosController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\PermisosController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(PermisoRepositoryInterface::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\AjustesController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\AjustesController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\PwaController::class, function (Container $c) {
        return new \App\Presentation\Controllers\PwaController(
            $c->get(ConfiguracionService::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\AuthController::class, function (Container $c) {
        $authService = $c->get(AuthService::class);
        return new \App\Presentation\Controllers\AuthController(
            new LoginUseCase($authService, new LoginValidator()),
            new LogoutUseCase($authService),
            $c->get(ConfiguracionService::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\UsuariosController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        $rolRepo     = $c->get(RolRepositoryInterface::class);
        $validator   = new CrearUsuarioValidator();
        return new \App\Presentation\Controllers\Admin\UsuariosController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            new CrearUsuarioUseCase($usuarioRepo, $rolRepo, $validator),
            new ListarUsuariosUseCase($usuarioRepo),
            new ActualizarUsuarioUseCase($usuarioRepo, $rolRepo, $validator),
            new EliminarUsuarioUseCase($usuarioRepo),
            $usuarioRepo,
            $rolRepo
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\RolesController::class, function (Container $c) {
        $rolRepo     = $c->get(RolRepositoryInterface::class);
        $permisoRepo = $c->get(PermisoRepositoryInterface::class);
        return new \App\Presentation\Controllers\Admin\RolesController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            new ListarRolesUseCase($rolRepo, $permisoRepo),
            new CrearRolUseCase($rolRepo, $permisoRepo),
            new ActualizarRolUseCase($rolRepo, $permisoRepo),
            new EliminarRolUseCase($rolRepo)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\CrudController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\CrudController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(CrudResourceService::class)
        );
    });
};
