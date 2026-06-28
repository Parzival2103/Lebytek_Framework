<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Container;

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\ConfiguracionRepositoryInterface;
use Lebytek\Framework\Infrastructure\Repositories\UsuarioRepository;
use Lebytek\Framework\Infrastructure\Repositories\RolRepository;
use Lebytek\Framework\Infrastructure\Repositories\PermisoRepository;
use Lebytek\Framework\Infrastructure\Repositories\ConfiguracionRepository;
use Lebytek\Framework\Application\Services\ConfiguracionService;
use Lebytek\Framework\Application\Services\AuthService;
use Lebytek\Framework\Application\Services\RbacService;
use Lebytek\Framework\Application\Validators\Usuarios\CrearUsuarioValidator;
use Lebytek\Framework\Application\Validators\Auth\LoginValidator;
use Lebytek\Framework\Application\UseCases\Usuarios\CrearUsuarioUseCase;
use Lebytek\Framework\Application\UseCases\Usuarios\ListarUsuariosUseCase;
use Lebytek\Framework\Application\UseCases\Usuarios\ActualizarUsuarioUseCase;
use Lebytek\Framework\Application\UseCases\Usuarios\EliminarUsuarioUseCase;
use Lebytek\Framework\Application\UseCases\Roles\CrearRolUseCase;
use Lebytek\Framework\Application\UseCases\Roles\ListarRolesUseCase;
use Lebytek\Framework\Application\UseCases\Roles\ActualizarRolUseCase;
use Lebytek\Framework\Application\UseCases\Roles\EliminarRolUseCase;
use Lebytek\Framework\Application\Services\AuthTokenService;
use Lebytek\Framework\Application\Services\CorreoAuthService;
use Lebytek\Framework\Application\Services\LoginRateLimitService;
use Lebytek\Framework\Application\UseCases\Auth\LoginUseCase;
use Lebytek\Framework\Application\UseCases\Auth\LogoutUseCase;
use Lebytek\Framework\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use Lebytek\Framework\Application\UseCases\Auth\ReenviarVerificacionUseCase;
use Lebytek\Framework\Application\UseCases\Auth\VerificarCorreoUseCase;
use Lebytek\Framework\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use Lebytek\Framework\Application\UseCases\Auth\RestablecerPasswordUseCase;
use Lebytek\Framework\Domain\Interfaces\AuthTokenRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\LoginIntentoRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\MailerInterface;
use Lebytek\Framework\Infrastructure\Repositories\AuthTokenRepository;
use Lebytek\Framework\Infrastructure\Repositories\LoginIntentoRepository;
use Lebytek\Framework\Infrastructure\Mail\LogMailer;
use Lebytek\Framework\Infrastructure\Mail\PhpMailerMailer;
use Lebytek\Framework\Application\UseCases\Dashboard\BuildDashboardViewModelUseCase;
use Lebytek\Framework\Domain\Interfaces\DashboardContributionProviderInterface;
use Lebytek\Framework\Domain\Interfaces\MenuCatalogRepositoryInterface;
use Lebytek\Framework\Infrastructure\Repositories\MenuCatalogRepository;
use Lebytek\Framework\Application\Services\AdminNavigationMenuService;
use Lebytek\Framework\Application\Services\RbacIntegrityReportService;
use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;
use Lebytek\Framework\Infrastructure\Repositories\BitacoraRepository;
use Lebytek\Framework\Infrastructure\Repositories\GenericCrudRepository;
use Lebytek\Framework\Application\Services\CrudActionResolver;
use Lebytek\Framework\Application\Services\CrudActionService;
use Lebytek\Framework\Application\Services\CrudConfigLoader;
use Lebytek\Framework\Application\Services\CrudConfigValidator;
use Lebytek\Framework\Application\Services\CrudDataService;
use Lebytek\Framework\Application\Services\CrudDbConstraintValidator;
use Lebytek\Framework\Application\Services\CrudDetailBuilder;
use Lebytek\Framework\Application\Services\CrudFieldValidationService;
use Lebytek\Framework\Application\Services\CrudFormBuilder;
use Lebytek\Framework\Application\Services\CrudHandlerRegistry;
use Lebytek\Framework\Application\Services\CrudRelationService;
use Lebytek\Framework\Application\Services\CrudScopeResolver;
use Lebytek\Framework\Application\Services\FileUploadService;
use Lebytek\Framework\Application\Services\ImageProcessor;
use Lebytek\Framework\Domain\Interfaces\ArchivoRepositoryInterface;
use Lebytek\Framework\Infrastructure\Repositories\ArchivoRepository;
use Lebytek\Framework\Application\Services\CrudHookRunner;
use Lebytek\Framework\Application\Services\CrudResourceService;
use Lebytek\Framework\Application\Services\CrudTableBuilder;
use Lebytek\Framework\Application\Services\CrudTransitionService;
use Lebytek\Framework\Domain\Interfaces\MigrationRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\ModuleStateRepositoryInterface;
use Lebytek\Framework\Infrastructure\Repositories\MigrationRepository;
use Lebytek\Framework\Infrastructure\Repositories\ModuleStateRepository;
use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;
use Lebytek\Framework\Application\Install\ModuleRegistry;
use Lebytek\Framework\Application\Install\DependencyResolver;
use Lebytek\Framework\Application\Install\Installer;
use Lebytek\Framework\Application\Install\DeploymentStatus;
use Lebytek\Framework\Kernel\Config\Config;

final class FrameworkServiceProvider
{
    public static function register(Container $container): void
    {
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
            $container->singleton(\Lebytek\Framework\Application\Services\CrudReturnUrlResolver::class, fn(Container $c) => new \Lebytek\Framework\Application\Services\CrudReturnUrlResolver(
                $c->get(\Lebytek\Framework\Application\Services\CalendarConfigLoader::class)
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
                $c->get(\Lebytek\Framework\Application\Services\CrudReturnUrlResolver::class)
            ));
        
            // ── Módulo Calendario ───────────────────────────────────────────────────
            $container->singleton(\Lebytek\Framework\Application\Services\CalendarConfigValidator::class, fn() => new \Lebytek\Framework\Application\Services\CalendarConfigValidator());
            $container->singleton(\Lebytek\Framework\Application\Services\CalendarConfigLoader::class, fn(Container $c) => new \Lebytek\Framework\Application\Services\CalendarConfigLoader(
                $c->get(\Lebytek\Framework\Application\Services\CalendarConfigValidator::class)
            ));
            $container->singleton(\Lebytek\Framework\Application\Services\CalendarEventMapper::class, fn() => new \Lebytek\Framework\Application\Services\CalendarEventMapper());
            $container->singleton(\Lebytek\Framework\Application\Services\CalendarViewModelBuilder::class, fn(Container $c) => new \Lebytek\Framework\Application\Services\CalendarViewModelBuilder(
                $c->get(\Lebytek\Framework\Application\Services\CalendarConfigLoader::class),
                $c->get(RbacService::class)
            ));
            $container->singleton(\Lebytek\Framework\Application\UseCases\Calendar\ListarEventosCalendarioUseCase::class, fn(Container $c) => new \Lebytek\Framework\Application\UseCases\Calendar\ListarEventosCalendarioUseCase(
                $c->get(\Lebytek\Framework\Application\Services\CalendarConfigLoader::class),
                $c->get(CrudResourceService::class),
                $c->get(\Lebytek\Framework\Application\Services\CalendarEventMapper::class)
            ));
            // Pre-vinculado (con su dependencia) para que el loop de proveedores no lo
            // construya sin argumentos.
            $container->singleton(\Lebytek\Framework\Infrastructure\Dashboard\CalendarDashboardProvider::class, fn(Container $c) => new \Lebytek\Framework\Infrastructure\Dashboard\CalendarDashboardProvider(
                $c->get(\Lebytek\Framework\Application\Services\CalendarConfigLoader::class)
            ));
        
            // ── Módulo Kit de PDF ───────────────────────────────────────────────────
            $container->singleton(\Lebytek\Framework\Application\Pdf\PdfComponentRenderer::class, fn() => new \Lebytek\Framework\Application\Pdf\PdfComponentRenderer());
        
            $container->singleton(\Lebytek\Framework\Domain\Pdf\PdfEngineInterface::class, fn() => new \Lebytek\Framework\Infrastructure\Pdf\DompdfRenderer(
                (string) (require ROOT_PATH . '/config/pdf.php')['font']
            ));
        
            $container->singleton(\Lebytek\Framework\Application\Pdf\PdfTemplateRegistry::class, fn() => new \Lebytek\Framework\Application\Pdf\PdfTemplateRegistry(
                require ROOT_PATH . '/config/pdf_templates.php'
            ));
        
            $container->singleton(\Lebytek\Framework\Infrastructure\Pdf\PdfStorage::class, fn() => new \Lebytek\Framework\Infrastructure\Pdf\PdfStorage());
        
            $container->singleton(\Lebytek\Framework\Application\Pdf\PdfRenderingService::class, fn(Container $c) => new \Lebytek\Framework\Application\Pdf\PdfRenderingService(
                $c->get(\Lebytek\Framework\Application\Pdf\PdfComponentRenderer::class),
                $c->get(\Lebytek\Framework\Domain\Pdf\PdfEngineInterface::class),
                $c->get(\Lebytek\Framework\Application\Pdf\PdfTemplateRegistry::class),
                require ROOT_PATH . '/config/pdf.php'
            ));
        
            $container->singleton(\Lebytek\Framework\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase::class, fn(Container $c) => new \Lebytek\Framework\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase(
                $c->get(\Lebytek\Framework\Application\Pdf\PdfComponentRenderer::class)
            ));
        
            // ── Módulo Reportes ─────────────────────────────────────────────────────
            $container->singleton(\Lebytek\Framework\Application\Reporte\ReporteConfigValidator::class, fn() => new \Lebytek\Framework\Application\Reporte\ReporteConfigValidator());
            $container->singleton(\Lebytek\Framework\Application\Reporte\ReporteConfigLoader::class, fn(Container $c) => new \Lebytek\Framework\Application\Reporte\ReporteConfigLoader(
                $c->get(\Lebytek\Framework\Application\Reporte\ReporteConfigValidator::class)
            ));
            $container->singleton(\Lebytek\Framework\Application\Reporte\PeriodoResolver::class, fn() => new \Lebytek\Framework\Application\Reporte\PeriodoResolver());
            $container->singleton(\Lebytek\Framework\Application\Reporte\ReporteAggregator::class, fn() => new \Lebytek\Framework\Application\Reporte\ReporteAggregator());
            $container->singleton(\Lebytek\Framework\Application\Reporte\CrudReporteDataSource::class, fn(Container $c) => new \Lebytek\Framework\Application\Reporte\CrudReporteDataSource(
                $c->get(CrudDataService::class),
                $c->get(CrudRelationService::class)
            ));
            $container->singleton(\Lebytek\Framework\Domain\Reporte\ReporteDataSourceInterface::class, fn(Container $c) => $c->get(\Lebytek\Framework\Application\Reporte\CrudReporteDataSource::class));
            $container->singleton(\Lebytek\Framework\Domain\Reporte\ReporteRecordSourceInterface::class, fn(Container $c) => $c->get(\Lebytek\Framework\Application\Reporte\CrudReporteDataSource::class));
            $container->singleton(\Lebytek\Framework\Application\Reporte\BuildReporteDataUseCase::class, fn(Container $c) => new \Lebytek\Framework\Application\Reporte\BuildReporteDataUseCase(
                $c->get(\Lebytek\Framework\Application\Reporte\ReporteConfigLoader::class),
                $c->get(\Lebytek\Framework\Domain\Reporte\ReporteDataSourceInterface::class),
                $c->get(\Lebytek\Framework\Application\Reporte\PeriodoResolver::class),
                $c->get(\Lebytek\Framework\Application\Reporte\ReporteAggregator::class)
            ));
            $container->singleton(\Lebytek\Framework\Application\Reporte\GenerarReporteUseCase::class, fn(Container $c) => new \Lebytek\Framework\Application\Reporte\GenerarReporteUseCase(
                $c->get(\Lebytek\Framework\Application\Reporte\BuildReporteDataUseCase::class),
                $c->get(\Lebytek\Framework\Application\Pdf\PdfRenderingService::class)
            ));
            $container->singleton(\Lebytek\Framework\Application\Reporte\GenerarDocumentoUseCase::class, fn(Container $c) => new \Lebytek\Framework\Application\Reporte\GenerarDocumentoUseCase(
                $c->get(\Lebytek\Framework\Application\Reporte\ReporteConfigLoader::class),
                $c->get(\Lebytek\Framework\Domain\Reporte\ReporteRecordSourceInterface::class),
                $c->get(\Lebytek\Framework\Application\Pdf\PdfTemplateRegistry::class),
                $c->get(\Lebytek\Framework\Application\Pdf\PdfRenderingService::class)
            ));
            $container->singleton(\Lebytek\Framework\Application\Reporte\GuardarReporteUseCase::class, fn(Container $c) => new \Lebytek\Framework\Application\Reporte\GuardarReporteUseCase(
                $c->get(\Lebytek\Framework\Application\Reporte\ReporteConfigLoader::class)
            ));
            $container->singleton(\Lebytek\Framework\Domain\Interfaces\ReporteRepositoryInterface::class, fn() => new \Lebytek\Framework\Infrastructure\Repositories\PdoReporteRepository());
        
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
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\DashboardController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\DashboardController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(BuildDashboardViewModelUseCase::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\PermisosController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\PermisosController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(PermisoRepositoryInterface::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\AjustesController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\AjustesController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(\Lebytek\Framework\Application\Services\SettingsSectionRegistry::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\PwaController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\PwaController(
                    $c->get(ConfiguracionService::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\AuthController::class, function (Container $c) {
                $authService = $c->get(AuthService::class);
                return new \Lebytek\Framework\Presentation\Controllers\AuthController(
                    new LoginUseCase(
                        $authService,
                        new LoginValidator(),
                        $c->get(LoginRateLimitService::class)
                    ),
                    new LogoutUseCase($authService),
                    $c->get(ConfiguracionService::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\RegistroController::class, function (Container $c) {
                $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
                $habilitado  = (bool) Config::get('auth.registro.habilitado', false);
                $ttlVerif    = (int) Config::get('auth.tokens.verificacion_ttl_min', 1440);
                return new \Lebytek\Framework\Presentation\Controllers\RegistroController(
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
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\RecuperacionController::class, function (Container $c) {
                $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
                return new \Lebytek\Framework\Presentation\Controllers\RecuperacionController(
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
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\UsuariosController::class, function (Container $c) {
                $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
                $rolRepo     = $c->get(RolRepositoryInterface::class);
                $archivoRepo = $c->get(ArchivoRepositoryInterface::class);
                $validator   = new CrearUsuarioValidator();
                return new \Lebytek\Framework\Presentation\Controllers\Admin\UsuariosController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    new CrearUsuarioUseCase($usuarioRepo, $rolRepo, $validator),
                    new ListarUsuariosUseCase($usuarioRepo),
                    new ActualizarUsuarioUseCase($usuarioRepo, $rolRepo, $validator),
                    new EliminarUsuarioUseCase($usuarioRepo),
                    $usuarioRepo,
                    $rolRepo,
                    new \Lebytek\Framework\Application\UseCases\Avatares\SubirAvatarUseCase($c->get(FileUploadService::class), $usuarioRepo),
                    new \Lebytek\Framework\Application\UseCases\Avatares\FijarAvatarActualUseCase($archivoRepo, $usuarioRepo),
                    new \Lebytek\Framework\Application\UseCases\Avatares\EliminarAvatarUseCase($archivoRepo, $usuarioRepo),
                    new \Lebytek\Framework\Application\UseCases\Avatares\ListarAvataresUseCase($archivoRepo),
                    new \Lebytek\Framework\Domain\Policies\AvatarPolicy(),
                    $c->get(RbacService::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\PerfilController::class, function (Container $c) {
                $usuarioRepo = $c->get(UsuarioRepositoryInterface::class);
                $archivoRepo = $c->get(ArchivoRepositoryInterface::class);
                return new \Lebytek\Framework\Presentation\Controllers\Admin\PerfilController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $usuarioRepo,
                    new \Lebytek\Framework\Application\UseCases\Perfil\ActualizarPerfilUseCase($usuarioRepo, new CrearUsuarioValidator()),
                    new \Lebytek\Framework\Application\UseCases\Avatares\SubirAvatarUseCase($c->get(FileUploadService::class), $usuarioRepo),
                    new \Lebytek\Framework\Application\UseCases\Avatares\FijarAvatarActualUseCase($archivoRepo, $usuarioRepo),
                    new \Lebytek\Framework\Application\UseCases\Avatares\EliminarAvatarUseCase($archivoRepo, $usuarioRepo),
                    new \Lebytek\Framework\Application\UseCases\Avatares\ListarAvataresUseCase($archivoRepo)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\RolesController::class, function (Container $c) {
                $rolRepo     = $c->get(RolRepositoryInterface::class);
                $permisoRepo = $c->get(PermisoRepositoryInterface::class);
                return new \Lebytek\Framework\Presentation\Controllers\Admin\RolesController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    new ListarRolesUseCase($rolRepo, $permisoRepo),
                    new CrearRolUseCase($rolRepo, $permisoRepo),
                    new ActualizarRolUseCase($rolRepo, $permisoRepo),
                    new EliminarRolUseCase($rolRepo)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\CrudController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\CrudController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(CrudResourceService::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\CalendarioController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\CalendarioController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(\Lebytek\Framework\Application\Services\CalendarViewModelBuilder::class),
                    $c->get(\Lebytek\Framework\Application\UseCases\Calendar\ListarEventosCalendarioUseCase::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\PdfKitDemoController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\PdfKitDemoController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(\Lebytek\Framework\Application\UseCases\Pdf\BuildPdfKitDemoViewModelUseCase::class),
                    $c->get(\Lebytek\Framework\Application\Pdf\PdfRenderingService::class)
                );
            });
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\ReportesController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\ReportesController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(\Lebytek\Framework\Application\Reporte\ReporteConfigLoader::class),
                    $c->get(\Lebytek\Framework\Domain\Interfaces\ReporteRepositoryInterface::class),
                    $c->get(\Lebytek\Framework\Application\Reporte\GuardarReporteUseCase::class),
                    $c->get(\Lebytek\Framework\Application\Reporte\GenerarReporteUseCase::class),
                    $c->get(\Lebytek\Framework\Application\Reporte\GenerarDocumentoUseCase::class)
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
        
            $container->bind(\Lebytek\Framework\Presentation\Controllers\Admin\SistemaEstadoController::class, function (Container $c) {
                return new \Lebytek\Framework\Presentation\Controllers\Admin\SistemaEstadoController(
                    $c->get(ConfiguracionService::class),
                    $c->get(AdminNavigationMenuService::class),
                    $c->get(DeploymentStatus::class)
                );
            });
    }
}
