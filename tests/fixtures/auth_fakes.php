<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\AuthToken;
use Lebytek\Framework\Domain\Entities\Rol;
use Lebytek\Framework\Domain\Interfaces\AuthTokenRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\RolRepositoryInterface;
use Lebytek\Framework\Domain\ValueObjects\Slug;

require_once __DIR__ . '/avatar_fakes.php';

if (!class_exists('FakeAuthTokenRepository')) {
    /** Repositorio de tokens en memoria; replica el contrato del repo PDO. */
    class FakeAuthTokenRepository implements AuthTokenRepositoryInterface
    {
        /** @var array<int, AuthToken> */
        public array $tokens = [];
        private int $nextId = 1;

        public function guardar(AuthToken $token): int
        {
            $id = $this->nextId++;
            $this->tokens[$id] = AuthToken::desdeFila([
                'id'         => $id,
                'usuario_id' => $token->usuarioId(),
                'tipo'       => $token->tipo(),
                'token_hash' => $token->tokenHash(),
                'expira_en'  => $token->expiraEn(),
                'usado_en'   => $token->usadoEn(),
                'created_at' => $token->createdAt() ?? date('Y-m-d H:i:s'),
            ]);
            return $id;
        }

        public function buscarVigentePorHash(string $hash, string $tipo): ?AuthToken
        {
            foreach ($this->tokens as $token) {
                if ($token->tokenHash() === $hash && $token->tipo() === $tipo && $token->estaVigente()) {
                    return $token;
                }
            }
            return null;
        }

        public function marcarUsado(int $id): void
        {
            if (isset($this->tokens[$id])) {
                $this->tokens[$id] = $this->tokens[$id]->marcarUsado();
            }
        }

        public function invalidarDeUsuario(int $usuarioId, string $tipo): void
        {
            foreach ($this->tokens as $id => $token) {
                if ($token->usuarioId() === $usuarioId && $token->tipo() === $tipo && $token->usadoEn() === null) {
                    $this->tokens[$id] = $token->marcarUsado();
                }
            }
        }

        public function contarRecientes(int $usuarioId, string $tipo, int $minutos): int
        {
            $desde = date('Y-m-d H:i:s', time() - $minutos * 60);
            $n = 0;
            foreach ($this->tokens as $token) {
                if ($token->usuarioId() === $usuarioId
                    && $token->tipo() === $tipo
                    && ($token->createdAt() ?? '') >= $desde) {
                    $n++;
                }
            }
            return $n;
        }
    }
}

if (!class_exists('FakeRolRepository')) {
    /** Repositorio de roles en memoria; registra asignaciones para asserts. */
    class FakeRolRepository implements RolRepositoryInterface
    {
        /** @var array<string, Rol> indexado por slug */
        public array $roles = [];
        /** @var list<array{0:int,1:int}> pares (usuarioId, rolId) asignados */
        public array $asignaciones = [];

        public function conRol(string $slug, int $id, string $nombre = 'Rol'): self
        {
            $this->roles[$slug] = new Rol($nombre, new Slug($slug), '', true, $id);
            return $this;
        }

        public function findById(int $id): ?Rol
        {
            foreach ($this->roles as $rol) {
                if ($rol->id() === $id) {
                    return $rol;
                }
            }
            return null;
        }

        public function findBySlug(string $slug): ?Rol
        {
            return $this->roles[$slug] ?? null;
        }

        public function findAll(): array
        {
            return array_values($this->roles);
        }

        public function save(Rol $rol): int { return 0; }
        public function update(Rol $rol): void {}
        public function delete(int $id): void {}
        public function buscarPorUsuarioId(int $usuarioId): array { return []; }

        public function asignarRolAUsuario(int $usuarioId, int $rolId): void
        {
            $this->asignaciones[] = [$usuarioId, $rolId];
        }

        public function revocarRolDeUsuario(int $usuarioId, int $rolId): void {}
        public function sincronizarRolesDeUsuario(int $usuarioId, array $rolIds): void {}
    }
}

if (!class_exists('FakeMailer')) {
    /** Mailer espía: acumula los mensajes enviados; puede simular fallo. */
    class FakeMailer implements \Lebytek\Framework\Domain\Interfaces\MailerInterface
    {
        /** @var list<\Lebytek\Framework\Application\DTO\Mail\MensajeCorreo> */
        public array $enviados = [];
        public ?\Throwable $falla = null;

        public function enviar(\Lebytek\Framework\Application\DTO\Mail\MensajeCorreo $mensaje): void
        {
            if ($this->falla !== null) {
                throw $this->falla;
            }
            $this->enviados[] = $mensaje;
        }
    }
}

if (!class_exists('FakeConfiguracionRepository')) {
    class FakeConfiguracionRepository implements \Lebytek\Framework\Domain\Interfaces\ConfiguracionRepositoryInterface
    {
        /** @param array<string, mixed> $datos */
        public function __construct(private array $datos = [])
        {
        }

        public function all(): array
        {
            return $this->datos;
        }

        public function get(string $clave, mixed $default = null): mixed
        {
            return $this->datos[$clave] ?? $default;
        }

        public function set(string $clave, mixed $valor): void
        {
            $this->datos[$clave] = $valor;
        }

        public function setMultiple(array $datos): void
        {
            foreach ($datos as $clave => $valor) {
                $this->datos[$clave] = $valor;
            }
        }
    }
}

if (!class_exists('FakePermisoRepository')) {
    /** Permisos en memoria; slugs vacíos para tests de login. */
    class FakePermisoRepository implements \Lebytek\Framework\Domain\Interfaces\PermisoRepositoryInterface
    {
        public function findById(int $id): ?\Lebytek\Framework\Domain\Entities\Permiso { return null; }
        public function findBySlug(string $slug): ?\Lebytek\Framework\Domain\Entities\Permiso { return null; }
        public function findAll(): array { return []; }
        public function findAllActivosOrdenadosPorModuloSlug(): array { return []; }
        public function buscarPorRolId(int $rolId): array { return []; }
        public function slugsPorUsuarioId(int $usuarioId): array { return []; }
        public function filterExistingPermisoIds(array $permisoIds, bool $soloActivos = false): array { return []; }
        public function listarTodosLosSlugs(): array { return []; }
        public function mapSlugActivo(): array { return []; }
        public function sincronizarPermisosDeRol(int $rolId, array $permisoIds): void {}
        public function save(\Lebytek\Framework\Domain\Entities\Permiso $permiso): int { return 0; }
        public function update(\Lebytek\Framework\Domain\Entities\Permiso $permiso): void {}
        public function delete(int $id): void {}
    }
}

if (!class_exists('FakeLoginIntentoRepository')) {
    /** Repositorio de intentos de login en memoria; replica el contrato PDO. */
    class FakeLoginIntentoRepository implements \Lebytek\Framework\Domain\Interfaces\LoginIntentoRepositoryInterface
    {
        /** @var list<array{dimension:string,clave:string,created_at:string}> */
        public array $filas = [];

        public function contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int
        {
            $desde = date('Y-m-d H:i:s', time() - $ventanaMin * 60);
            $n = 0;
            foreach ($this->filas as $fila) {
                if ($fila['dimension'] === $dimension
                    && $fila['clave'] === $clave
                    && $fila['created_at'] >= $desde) {
                    $n++;
                }
            }
            return $n;
        }

        public function registrarFallo(string $ip, string $emailNormalizado): void
        {
            $ahora = date('Y-m-d H:i:s');
            $this->filas[] = ['dimension' => 'ip',    'clave' => $ip,                 'created_at' => $ahora];
            $this->filas[] = ['dimension' => 'email', 'clave' => $emailNormalizado,   'created_at' => $ahora];
        }

        public function limpiarPara(string $ip, string $emailNormalizado): void
        {
            $this->filas = array_values(array_filter(
                $this->filas,
                fn(array $fila): bool => !(
                    ($fila['dimension'] === 'ip'    && $fila['clave'] === $ip)
                    || ($fila['dimension'] === 'email' && $fila['clave'] === $emailNormalizado)
                )
            ));
        }

        public function purgarAntiguos(int $ventanaMin): void
        {
            $limite = date('Y-m-d H:i:s', time() - $ventanaMin * 2 * 60);
            $this->filas = array_values(array_filter(
                $this->filas,
                fn(array $fila): bool => $fila['created_at'] >= $limite
            ));
        }
    }
}

function fake_correo_auth_service(?FakeMailer $mailer = null, array $config = []): \Lebytek\Framework\Application\Services\CorreoAuthService
{
    $mailer ??= new FakeMailer();
    $configService = new \Lebytek\Framework\Application\Services\ConfiguracionService(
        new FakeConfiguracionRepository($config)
    );

    return new \Lebytek\Framework\Application\Services\CorreoAuthService($mailer, $configService, 'https://app.test');
}
