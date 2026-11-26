<?php

namespace App\Twig;

use App\Service\EvenementActifService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose l'événement actif global de la console /rc dans les templates Twig.
 */
class EvenementActifExtension extends AbstractExtension
{
    public function __construct(
        private readonly EvenementActifService $evenementActifService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rc_evenement_actif', [$this->evenementActifService, 'getEvenementActif']),
        ];
    }
}
