<?php

namespace App\Enum;

/** Plat principal au choix : le reste du menu (apéritif, fromage, dessert, café) est le même pour tous. */
enum Meal: string
{
    case Lasagne = 'lasagne';
    case AssietteAnglaise = 'assiette_anglaise';

    public function label(): string
    {
        return match ($this) {
            self::Lasagne => 'Lasagnes & salade',
            self::AssietteAnglaise => 'Assiette anglaise',
        };
    }
}
