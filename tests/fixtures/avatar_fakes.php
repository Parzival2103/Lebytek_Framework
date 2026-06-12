<?php

declare(strict_types=1);

use App\Domain\Entities\Usuario;
use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Domain\ValueObjects\Email;

if (!class_exists('FakeUsuarioRepository')) {
    /** Repositorio de usuarios en memoria para tests de avatares/perfil. */
    class FakeUsuarioRepository implements UsuarioRepositoryInterface
    {
        /** @var array<int, Usuario> */
        public array $usuarios = [];
        /** @var list<array{0:int,1:?string}> llamadas a actualizarAvatar */
        public array $avatarUpdates = [];
        /** @var list<string> emails ya tomados por otros usuarios */
        public array $emailsExistentes = [];
        /** @var list<int> usuarios verificados vía marcarEmailVerificado */
        public array $verificados = [];
        public ?Usuario $ultimoUpdate = null;

        public function findById(int $id): ?Usuario
        {
            return $this->usuarios[$id] ?? null;
        }

        public function findByEmail(Email $email): ?Usuario
        {
            foreach ($this->usuarios as $usuario) {
                if ($usuario->email()->equals($email)) {
                    return $usuario;
                }
            }
            return null;
        }

        public function findAll(int $limit = 50, int $offset = 0): array
        {
            return array_values($this->usuarios);
        }

        public function countAll(): int
        {
            return count($this->usuarios);
        }

        public function save(Usuario $usuario): int
        {
            $id = count($this->usuarios) + 1;
            $this->usuarios[$id] = $usuario;
            return $id;
        }

        public function update(Usuario $usuario): void
        {
            $this->ultimoUpdate = $usuario;
            if ($usuario->id() !== null) {
                $this->usuarios[$usuario->id()] = $usuario;
            }
        }

        public function actualizarAvatar(int $usuarioId, ?string $ruta): void
        {
            $this->avatarUpdates[] = [$usuarioId, $ruta];
        }

        public function marcarEmailVerificado(int $usuarioId): void
        {
            $this->verificados[] = $usuarioId;
            if (isset($this->usuarios[$usuarioId])) {
                $this->usuarios[$usuarioId] = $this->usuarios[$usuarioId]->activar();
            }
        }

        public function delete(int $id): void
        {
            unset($this->usuarios[$id]);
        }

        public function emailExists(Email $email, ?int $excludeId = null): bool
        {
            return in_array((string) $email, $this->emailsExistentes, true);
        }
    }
}

if (!function_exists('fake_usuario')) {
    function fake_usuario(int $id, string $email = 'persona@test.local', string $nombre = 'Ana', string $apellido = 'Lopez'): Usuario
    {
        return new Usuario(
            nombre:       $nombre,
            apellido:     $apellido,
            email:        new Email($email),
            passwordHash: 'hash-original',
            activo:       true,
            avatar:       null,
            id:           $id
        );
    }
}
