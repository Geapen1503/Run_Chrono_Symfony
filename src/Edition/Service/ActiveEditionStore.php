<?php

namespace App\Edition\Service;

/**
 * Contexte édition active pour la console /rc (complète parametre_course.php).
 */
final class ActiveEditionStore
{
    public function __construct(
        private string $storagePath,
    ) {
    }

    public function set(string $idHtml): void
    {
        $dir = \dirname($this->storagePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible d\'écrire l\'édition active Symfony.');
        }
        if (file_put_contents($this->storagePath, $idHtml."\n") === false) {
            throw new \RuntimeException('Écriture édition active impossible.');
        }
    }

    public function get(): ?string
    {
        if (!is_file($this->storagePath)) {
            return null;
        }
        $value = trim((string) file_get_contents($this->storagePath));

        return $value !== '' ? $value : null;
    }
}