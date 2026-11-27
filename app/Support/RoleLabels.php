<?php

namespace App\Support;

/**
 * Nombres visibles de los roles. Internamente se conservan los nombres originales
 * (admin, empleado, cliente) para no romper permisos existentes.
 */
final class RoleLabels
{
    public const MAP = [
        'admin' => 'Administrador',
        'empleado' => 'Recepción',
        'cliente' => 'Cliente',
    ];

    public static function label(?string $role): string
    {
        return self::MAP[$role] ?? (string) $role;
    }
}
