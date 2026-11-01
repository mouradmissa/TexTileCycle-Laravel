<?php

namespace App\Modules\Ateliers\Exceptions;

use RuntimeException;

/**
 * La recherche dans les fiches conseils n'a pas pu se faire (service Python arrêté, jeton absent…).
 * Le message est destiné au citoyen, sans détail technique.
 */
class AssistantIndisponibleException extends RuntimeException
{
    public const MESSAGE = 'L\'assistant de réparation est indisponible pour le moment. Vous pouvez essayer le diagnostic par photo ou chercher un atelier.';

    public static function indisponible(): self
    {
        return new self(self::MESSAGE);
    }
}
