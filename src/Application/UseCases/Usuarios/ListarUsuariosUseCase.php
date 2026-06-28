<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\UseCases\Usuarios;

use Lebytek\Framework\Domain\Interfaces\UsuarioRepositoryInterface;
use Lebytek\Framework\Kernel\Constants\AppConstants;
use Lebytek\Framework\Kernel\Helpers\Paginator;

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
