<?php

declare(strict_types=1);

use App\Domain\Entities\AuthToken;
use App\Domain\Entities\Rol;
use App\Domain\Interfaces\AuthTokenRepositoryInterface;
use App\Domain\Interfaces\RolRepositoryInterface;
use App\Domain\ValueObjects\Slug;

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
    class FakeMailer implements \App\Domain\Interfaces\MailerInterface
    {
        /** @var list<\App\Application\DTO\Mail\MensajeCorreo> */
        public array $enviados = [];
        public ?\Throwable $falla = null;

        public function enviar(\App\Application\DTO\Mail\MensajeCorreo $mensaje): void
        {
            if ($this->falla !== null) {
                throw $this->falla;
            }
            $this->enviados[] = $mensaje;
        }
    }
}
