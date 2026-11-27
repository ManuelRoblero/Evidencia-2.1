<?php

namespace App\Support;

/**
 * Interruptores de funciones por fase (ver config/tuequilibrio.php).
 */
final class Features
{
    public static function store(): bool
    {
        return (bool) config('tuequilibrio.features.store', false);
    }

    public static function clientPortal(): bool
    {
        return (bool) config('tuequilibrio.features.client_portal', false);
    }
}
