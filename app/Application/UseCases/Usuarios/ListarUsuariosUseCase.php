<?php

declare(strict_types=1);

namespace App\Application\UseCases\Usuarios;

use App\Domain\Interfaces\UsuarioRepositoryInterface;
use App\Kernel\Constants\AppConstants;
use App\Kernel\Helpers\Paginator;

final class ListarUsuariosUseCase
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepo
    ) {}

    public function execute(int $pagina = 1, int $porPagina = AppConstants::PER_PAGE_DEFAULT): array
    {
        $total    = $this->usuarioRepo->countAll();
        $paginator = new Paginator($total, $porPagina, $pagina, '');
        $usuarios  = $this->usuarioRepo->findAll($porPagina, $paginator->offset());

        return [
            'usuarios'  => $usuarios,
            'paginator' => $paginator,
            'total'     => $total,
        ];
    }
}
