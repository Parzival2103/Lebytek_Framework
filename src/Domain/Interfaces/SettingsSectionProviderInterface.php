<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

/**
 * Sección extensible de la pantalla de Ajustes. Un módulo registra una o varias
 * implementaciones (vía config/settings_sections.php) y AjustesController las
 * renderiza/persiste sin conocer el módulo concreto.
 */
interface SettingsSectionProviderInterface
{
    public function clave(): string;
    public function titulo(): string;
    public function icono(): string;
    /** Slug RBAC requerido para ver/editar esta sección. */
    public function permiso(): string;

    /**
     * Definiciones declarativas de campos.
     * @return list<array{name:string,label:string,type:string,group?:string,secret?:bool,default?:string,options?:array<string,string>,help?:string}>
     */
    public function campos(): array;
    /** Ruta de vista custom para secciones no declarativas; null = sección de campos normal. */
    public function vista(): ?string;
}
