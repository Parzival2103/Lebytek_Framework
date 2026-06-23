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
use App\Application\Services\AuthTokenService;
use App\Application\Services\CorreoAuthService;
use App\Application\Services\LoginRateLimitService;
use App\Application\UseCases\Auth\LoginUseCase;
use App\Application\UseCases\Auth\LogoutUseCase;
use App\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use App\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use App\Application\UseCases\Auth\VerificarCorreoUseCase;
use App\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use App\Application\UseCases\Auth\RestablecerPasswordUseCase;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\LoginIntentoRepositoryInterface;
use App\Domain\Interfaces\MailerInterface;
use App\Infrastructure\Repositories\AuthTokenRepository;
use App\Infrastructure\Repositories\LoginIntentoRepository;
use App\Infrastructure\Mail\LogMailer;
use App\Infrastructure\Mail\PhpMailerMailer;
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
use App\Application\Services\CrudDetailBuilder;
use App\Application\Services\CrudFieldValidationService;
use App\Application\Services\CrudFormBuilder;
use App\Application\Services\CrudHandlerRegistry;
use App\Application\Services\CrudRelationService;
use App\Application\Services\CrudScopeResolver;
use App\Application\Services\FileUploadService;
use App\Application\Services\ImageProcessor;
use App\Domain\Interfaces\ArchivoRepositoryInterface;
use App\Infrastructure\Repositories\ArchivoRepository;
use App\Application\Services\CrudHookRunner;
use App\Application\Services\CrudResourceService;
use App\Application\Services\CrudTableBuilder;
use App\Application\Services\CrudTransitionService;
use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Domain\Interfaces\ModuleStateRepositoryInterface;
use App\Infrastructure\Repositories\MigrationRepository;
use App\Infrastructure\Repositories\ModuleStateRepository;
use App\Infrastructure\Install\SqlFileRunner;
use App\Application\Install\ModuleRegistry;
use App\Application\Install\DependencyResolver;
use App\Application\Install\Installer;
use App\Application\Install\DeploymentStatus;
use App\Kernel\Config\Config;

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

    $container->singleton(AuthTokenRepositoryInterface::class, fn() => new AuthTokenRepository());

    $container->singleton(MailerInterface::class, static function (): MailerInterface {
        $mailConfig = (array) Config::get('mail', []);
        return ($mailConfig['driver'] ?? 'log') === 'smtp'
            ? new PhpMailerMailer($mailConfig)
            : new LogMailer();
    });

    $container->singleton(AuthTokenService::class, fn(Container $c) => new AuthTokenService(
        $c->get(AuthTokenRepositoryInterface::class),
        (int) Config::get('auth.tokens.max_por_hora', 3)
    ));

    $container->singleton(LoginIntentoRepositoryInterface::class, fn() => new LoginIntentoRepository());

    $container->singleton(LoginRateLimitService::class, fn(Container $c) => new LoginRateLimitService(
        $c->get(LoginIntentoRepositoryInterface::class),
        (int) Config::get('auth.login.max_intentos', 5),
        (int) Config::get('auth.login.ventana_min', 15),
        (bool) Config::get('auth.login.habilitado', true)
    ));

    $container->singleton(CorreoAuthService::class, fn(Container $c) => new CorreoAuthService(
        $c->get(MailerInterface::class),
        $c->get(ConfiguracionService::class),
        (string) Config::get('app.url', 'http://localhost')
    ));

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
    $container->singleton(CrudScopeResolver::class, fn(Container $c) => new CrudScopeResolver(
        $c->get(CrudHandlerRegistry::class)
    ));
    $container->singleton(ArchivoRepositoryInterface::class, fn() => new ArchivoRepository());
    $container->singleton(ImageProcessor::class, fn() => new ImageProcessor());
    $container->singleton(FileUploadService::class, fn(Container $c) => new FileUploadService(
        $c->get(ImageProcessor::class),
        $c->get(ArchivoRepositoryInterface::class)
    ));
    $container->singleton(CrudDataService::class, fn(Container $c) => new CrudDataService(
        $c->get(GenericCrudRepository::class),
        $c->get(BitacoraRepositoryInterface::class),
        $c->get(CrudHookRunner::class),
        $c->get(CrudFieldValidationService::class),
        $c->get(CrudDbConstraintValidator::class),
        $c->get(CrudHandlerRegistry::class),
        $c->get(CrudScopeResolver::class),
        $c->get(FileUploadService::class)
    ));
    $container->singleton(CrudRelationService::class, fn(Container $c) => new CrudRelationService(
        $c->get(GenericCrudRepository::class)
    ));
    $container->singleton(CrudFormBuilder::class, fn(Container $c) => new CrudFormBuilder(
        $c->get(CrudRelationService::class)
    ));
    $container->singleton(CrudDetailBuilder::class, fn(Container $c) => new CrudDetailBuilder(
        $c->get(CrudRelationService::class),
        $c->get(BitacoraRepositoryInterface::class)
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
        $c->get(CrudTransitionService::class),
        $c->get(CrudScopeResolver::class)
    ));
    $container->singleton(\App\Application\Services\CrudReturnUrlResolver::class, fn(Container $c) => new \App\Application\Services\CrudReturnUrlResolver(
        $c->get(\App\Application\Services\CalendarConfigLoader::class)
    ));
    $container->singleton(CrudResourceService::class, fn(Container $c) => new CrudResourceService(
        $c->get(CrudConfigLoader::class),
        $c->get(CrudDataService::class),
        $c->get(CrudFormBuilder::class),
        $c->get(CrudTableBuilder::class),
        $c->get(RbacService::class),
        $c->get(CrudActionResolver::class),
        $c->get(CrudActionService::class),
        $c->get(CrudDetailBuilder::class),
        $c->get(CrudScopeResolver::class),
        $c->get(\App\Application\Services\CrudReturnUrlResolver::class)
    ));

    // ── Módulo Calendario ───────────────────────────────────────────────────
    $container->singleton(\App\Application\Services\CalendarConfigValidator::class, fn() => new \App\Application\Services\CalendarConfigValidator());
    $container->singleton(\App\Application\Services\CalendarConfigLoader::class, fn(Container $c) => new \App\Application\Services\CalendarConfigLoader(
        $c->get(\App\Application\Services\CalendarConfigValidator::class)
    ));
    $container->singleton(\App\Application\Services\CalendarEventMapper::class, fn() => new \App\Application\Services\CalendarEventMapper());
    $container->singleton(\App\Application\Services\CalendarViewModelBuilder::class, fn(Container $c) => new \App\Application\Services\CalendarViewModelBuilder(
        $c->get(\App\Application\Services\CalendarConfigLoader::class),
        $c->get(RbacService::class)
    ));
    $container->singleton(\App\Application\UseCases\Calendar\ListarEventosCalendarioUseCase::class, fn(Container $c) => new \App\Application\UseCases\Calendar\ListarEventosCalendarioUseCase(
        $c->get(\App\Application\Services\CalendarConfigLoader::class),
        $c->get(CrudResourceService::class),
        $c->get(\App\Application\Services\CalendarEventMapper::class)
    ));
    // Pre-vinculado (con su dependencia) para que el loop de proveedores no lo
    // construya sin argumentos.
    $container->singleton(\App\Infrastructure\Dashboard\CalendarDashboardProvider::class, fn(Container $c) => new \App\Infrastructure\Dashboard\CalendarDashboardProvider(
        $c->get(\App\Application\Services\CalendarConfigLoader::class)
    ));

    // ── Módulo Kit de PDF ───────────────────────────────────────────────────
    $container->singleton(\App\Application\Pdf\PdfComponentRenderer::class, fn() => new \App\Application\Pdf\PdfComponentRenderer());

    $container->singleton(\App\Domain\Pdf\PdfEngineInterface::class, fn() => new \App\Infrastructure\Pdf\DompdfRenderer(
        (string) (require ROOT_PATH . '/config/pdf.php')['font']
    ));

    $container->singleton(\App\Application\Pdf\PdfTemplateRegistry::class, fn() => new \App\Application\Pdf\PdfTemplateRegistry(
        require ROOT_PATH . '/config/pdf_templates.php'
    ));

    $container->singleton(\App\Infrastructure\Pdf\PdfStorage::class, fn() => new \App\Infrastructure\Pdf\PdfStorage());

    $container->singleton(\App\Application\Pdf\PdfRenderingService::class, fn(Container $c) => new \App\Application\Pdf\PdfRenderingService(
        $c->get(\App\Application\Pdf\PdfComponentRenderer::class),
        $c->get(\App\Domain\Pdf\PdfEngineInterface::class),
        $c->get(\App\Application\Pdf\PdfTemplateRegistry::class),
        require ROOT_PATH . '/config/pdf.php'
    ));

    $container->singleton(\App\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase::class, fn(Container $c) => new \App\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase(
        $c->get(\App\Application\Pdf\PdfComponentRenderer::class)
    ));

    // ── Módulo Reportes ─────────────────────────────────────────────────────
    $container->singleton(\App\Application\Reporte\ReporteConfigValidator::class, fn() => new \App\Application\Reporte\ReporteConfigValidator());
    $container->singleton(\App\Application\Reporte\ReporteConfigLoader::class, fn(Container $c) => new \App\Application\Reporte\ReporteConfigLoader(
        $c->get(\App\Application\Reporte\ReporteConfigValidator::class)
    ));
    $container->singleton(\App\Application\Reporte\PeriodoResolver::class, fn() => new \App\Application\Reporte\PeriodoResolver());
    $container->singleton(\App\Application\Reporte\ReporteAggregator::class, fn() => new \App\Application\Reporte\ReporteAggregator());
    $container->singleton(\App\Application\Reporte\CrudReporteDataSource::class, fn(Container $c) => new \App\Application\Reporte\CrudReporteDataSource(
        $c->get(CrudDataService::class),
        $c->get(CrudRelationService::class)
    ));
    $container->singleton(\App\Domain\Reporte\ReporteDataSourceInterface::class, fn(Container $c) => $c->get(\App\Application\Reporte\CrudReporteDataSource::class));
    $container->singleton(\App\Domain\Reporte\ReporteRecordSourceInterface::class, fn(Container $c) => $c->get(\App\Application\Reporte\CrudReporteDataSource::class));
    $container->singleton(\App\Application\Reporte\BuildReporteDataUseCase::class, fn(Container $c) => new \App\Application\Reporte\BuildReporteDataUseCase(
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class),
        $c->get(\App\Domain\Reporte\ReporteDataSourceInterface::class),
        $c->get(\App\Application\Reporte\PeriodoResolver::class),
        $c->get(\App\Application\Reporte\ReporteAggregator::class)
    ));
    $container->singleton(\App\Application\Reporte\GenerarReporteUseCase::class, fn(Container $c) => new \App\Application\Reporte\GenerarReporteUseCase(
        $c->get(\App\Application\Reporte\BuildReporteDataUseCase::class),
        $c->get(\App\Application\Pdf\PdfRenderingService::class)
    ));
    $container->singleton(\App\Application\Reporte\GenerarDocumentoUseCase::class, fn(Container $c) => new \App\Application\Reporte\GenerarDocumentoUseCase(
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class),
        $c->get(\App\Domain\Reporte\ReporteRecordSourceInterface::class),
        $c->get(\App\Application\Pdf\PdfTemplateRegistry::class),
        $c->get(\App\Application\Pdf\PdfRenderingService::class)
    ));
    $container->singleton(\App\Application\Reporte\GuardarReporteUseCase::class, fn(Container $c) => new \App\Application\Reporte\GuardarReporteUseCase(
        $c->get(\App\Application\Reporte\ReporteConfigLoader::class)
    ));
    $container->singleton(\App\Domain\Interfaces\ReporteRepositoryInterface::class, fn() => new \App\Infrastructure\Repositories\PdoReporteRepository());

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
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Application\Services\SettingsSectionRegistry::class)
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
            new LoginUseCase(
                $authService,
                new LoginValidator(),
                $c->get(LoginRateLimitService::class)
            ),
            new LogoutUseCase($authService),
            $c->get(ConfiguracionService::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\RegistroController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        $habilitado  = (bool) Config::get('auth.registro.habilitado', false);
        $ttlVerif    = (int) Config::get('auth.tokens.verificacion_ttl_min', 1440);
        return new \App\Presentation\Controllers\RegistroController(
            $c->get(ConfiguracionService::class),
            new RegistrarUsuarioUseCase(
                usuarioRepo:        $usuarioRepo,
                rolRepo:            $c->get(RolRepositoryInterface::class),
                validator:          new CrearUsuarioValidator(),
                tokens:             $c->get(AuthTokenService::class),
                correo:             $c->get(CorreoAuthService::class),
                habilitado:         $habilitado,
                rolDefault:         (string) Config::get('auth.registro.rol_default', 'usuario'),
                verificacionTtlMin: $ttlVerif
            ),
            new VerificarCorreoUseCase($c->get(AuthTokenRepositoryInterface::class), $usuarioRepo),
            new ReenviarVerificacionUseCase(
                usuarioRepo:        $usuarioRepo,
                tokens:             $c->get(AuthTokenService::class),
                correo:             $c->get(CorreoAuthService::class),
                verificacionTtlMin: $ttlVerif
            ),
            $habilitado
        );
    });

    $container->bind(\App\Presentation\Controllers\RecuperacionController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        return new \App\Presentation\Controllers\RecuperacionController(
            $c->get(ConfiguracionService::class),
            new SolicitarRecuperacionUseCase(
                usuarioRepo:        $usuarioRepo,
                tokens:             $c->get(AuthTokenService::class),
                correo:             $c->get(CorreoAuthService::class),
                recuperacionTtlMin: (int) Config::get('auth.tokens.recuperacion_ttl_min', 60)
            ),
            new RestablecerPasswordUseCase($usuarioRepo, $c->get(AuthTokenRepositoryInterface::class))
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\UsuariosController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        $rolRepo     = $c->get(RolRepositoryInterface::class);
        $archivoRepo = $c->get(ArchivoRepositoryInterface::class);
        $validator   = new CrearUsuarioValidator();
        return new \App\Presentation\Controllers\Admin\UsuariosController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            new CrearUsuarioUseCase($usuarioRepo, $rolRepo, $validator),
            new ListarUsuariosUseCase($usuarioRepo),
            new ActualizarUsuarioUseCase($usuarioRepo, $rolRepo, $validator),
            new EliminarUsuarioUseCase($usuarioRepo),
            $usuarioRepo,
            $rolRepo,
            new \App\Application\UseCases\Avatares\SubirAvatarUseCase($c->get(FileUploadService::class), $usuarioRepo),
            new \App\Application\UseCases\Avatares\FijarAvatarActualUseCase($archivoRepo, $usuarioRepo),
            new \App\Application\UseCases\Avatares\EliminarAvatarUseCase($archivoRepo, $usuarioRepo),
            new \App\Application\UseCases\Avatares\ListarAvataresUseCase($archivoRepo),
            new \App\Domain\Policies\AvatarPolicy(),
            $c->get(RbacService::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\PerfilController::class, function (Container $c) {
        $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
        $archivoRepo = $c->get(ArchivoRepositoryInterface::class);
        return new \App\Presentation\Controllers\Admin\PerfilController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $usuarioRepo,
            new \App\Application\UseCases\Perfil\ActualizarPerfilUseCase($usuarioRepo, new CrearUsuarioValidator()),
            new \App\Application\UseCases\Avatares\SubirAvatarUseCase($c->get(FileUploadService::class), $usuarioRepo),
            new \App\Application\UseCases\Avatares\FijarAvatarActualUseCase($archivoRepo, $usuarioRepo),
            new \App\Application\UseCases\Avatares\EliminarAvatarUseCase($archivoRepo, $usuarioRepo),
            new \App\Application\UseCases\Avatares\ListarAvataresUseCase($archivoRepo)
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

    $container->bind(\App\Presentation\Controllers\Admin\CalendarioController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\CalendarioController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Application\Services\CalendarViewModelBuilder::class),
            $c->get(\App\Application\UseCases\Calendar\ListarEventosCalendarioUseCase::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\PdfKitDemoController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\PdfKitDemoController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase::class),
            $c->get(\App\Application\Pdf\PdfRenderingService::class)
        );
    });

    $container->bind(\App\Presentation\Controllers\Admin\ReportesController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\ReportesController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(\App\Application\Reporte\ReporteConfigLoader::class),
            $c->get(\App\Domain\Interfaces\ReporteRepositoryInterface::class),
            $c->get(\App\Application\Reporte\GuardarReporteUseCase::class),
            $c->get(\App\Application\Reporte\GenerarReporteUseCase::class),
            $c->get(\App\Application\Reporte\GenerarDocumentoUseCase::class)
        );
    });

    // ── Motor de instalación / versionado ───────────────────────────────────
    $container->singleton(MigrationRepositoryInterface::class, fn() => new MigrationRepository());
    $container->singleton(ModuleStateRepositoryInterface::class, fn() => new ModuleStateRepository());
    $container->singleton(SqlFileRunner::class, fn() => new SqlFileRunner());
    $container->singleton(ModuleRegistry::class, fn() => new ModuleRegistry(ROOT_PATH . '/config/modules'));
    $container->singleton(DependencyResolver::class, fn() => new DependencyResolver());

    $container->singleton(Installer::class, fn(Container $c) => new Installer(
        $c->get(ModuleRegistry::class),
        $c->get(DependencyResolver::class),
        $c->get(MigrationRepositoryInterface::class),
        $c->get(ModuleStateRepositoryInterface::class),
        $c->get(SqlFileRunner::class),
        ROOT_PATH . '/database/migrations',
        ROOT_PATH . '/database/seeds'
    ));

    $container->singleton(DeploymentStatus::class, fn(Container $c) => new DeploymentStatus(
        $c->get(ModuleRegistry::class),
        $c->get(Installer::class),
        $c->get(ModuleStateRepositoryInterface::class),
        (string) Config::get('app.version', '0.0.0')
    ));

    $container->bind(\App\Presentation\Controllers\Admin\SistemaEstadoController::class, function (Container $c) {
        return new \App\Presentation\Controllers\Admin\SistemaEstadoController(
            $c->get(ConfiguracionService::class),
            $c->get(AdminNavigationMenuService::class),
            $c->get(DeploymentStatus::class)
        );
    });

    // Registry de secciones de Ajustes — siempre resoluble; los providers se cargan
    // solo si su módulo está activo (toggle inline). AjustesController lo consume.
    $container->singleton(\App\Application\Services\SettingsSectionRegistry::class, function () {
        $providers = [];
        if ((bool) Config::get('vertical.modules.marketing', false)) {
            $providers = [
                new \App\Infrastructure\Marketing\Settings\MarketingCorreoSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingPaquetesSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingTrackingSettingsProvider(),
                new \App\Infrastructure\Marketing\Settings\MarketingContenidoSettingsProvider(),
            ];
        }
        return new \App\Application\Services\SettingsSectionRegistry($providers);
    });

    // ── Módulo Marketing (bindings condicionales al toggle; ver config/modules/marketing.php) ──
    if ((bool) Config::get('vertical.modules.marketing', false)) {
        $container->singleton(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class,
            fn() => new \App\Infrastructure\Repositories\PdoMarketingContentRepository());

        $container->singleton(\App\Domain\Marketing\Contracts\LandingContentProviderInterface::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\CrudLandingContentProvider(
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class,
            fn(Container $c) => new \App\Infrastructure\Marketing\CrudCommercialPackageSource(
                $c->get(\App\Domain\Marketing\Contracts\MarketingContentRepositoryInterface::class)));

        $container->singleton(\App\Application\Marketing\RenderLandingUseCase::class,
            fn(Container $c) => new \App\Application\Marketing\RenderLandingUseCase(
                $c->get(\App\Domain\Marketing\Contracts\LandingContentProviderInterface::class),
                $c->get(\App\Domain\Marketing\Contracts\CommercialPackageSourceInterface::class)));

        $container->bind(\App\Presentation\Controllers\Publico\LandingController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LandingController(
                $c->get(ConfiguracionService::class),
                $c->get(\App\Application\Marketing\RenderLandingUseCase::class)));

        $container->singleton(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class,
            fn() => new \App\Infrastructure\Repositories\PdoLeadRepository());

        $container->singleton(\App\Application\Marketing\CapturarLeadUseCase::class, function (Container $c) {
            $destinoInterno = (string) $c->get(ConfiguracionService::class)->get('mkt_mail_from', '');
            return new \App\Application\Marketing\CapturarLeadUseCase([
                new \App\Infrastructure\Marketing\LeadCapture\PersistLeadHandler(
                    $c->get(\App\Domain\Marketing\Contracts\LeadRepositoryInterface::class)),
                new \App\Infrastructure\Marketing\LeadCapture\NotifyInternalHandler(
                    $c->get(\App\Domain\Interfaces\MailerInterface::class),
                    $destinoInterno),
                new \App\Infrastructure\Marketing\LeadCapture\AutoresponderHandler(
                    $c->get(\App\Domain\Interfaces\MailerInterface::class)),
            ]);
        });

        $container->bind(\App\Presentation\Controllers\Publico\LeadController::class,
            fn(Container $c) => new \App\Presentation\Controllers\Publico\LeadController(
                $c->get(\App\Application\Marketing\CapturarLeadUseCase::class)));

        // Task 14 añade aquí: PortalClienteController.
    }
};
