<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * Paleta compartida para paneles Filament (dashboard empleados + portal clientes).
 * TuEquilibrio: verde azulado #2f7f73 con neutros fríos (slate).
 */
final class FilamentBrandColors
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function panelColors(): array
    {
        return [
            'primary' => Color::Teal,
            'gray' => Color::Slate,
        ];
    }
}
