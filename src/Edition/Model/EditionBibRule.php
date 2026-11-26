<?php

namespace App\Edition\Model;

/**
 * Règle d'attribution dossard → épreuve (plage ou numéro isolé).
 */
final class EditionBibRule
{
    public function __construct(
        public string $type,
        public ?int $min = null,
        public ?int $max = null,
        public ?int $single = null,
    ) {
    }
}