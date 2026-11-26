<?php

namespace App\Edition\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Dépose affiche / règlement dans load_images/ (legacy).
 */
final class EditionMediaStorage
{
    public function __construct(
        private LegacyPaths $paths,
    ) {
    }

    public function storeUploaded(UploadedFile $file): string
    {
        $original = $file->getClientOriginalName();
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?? 'fichier';
        $targetDir = $this->paths->loadImagesDir();
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Dossier load_images inaccessible.');
        }
        $file->move($targetDir, $safe);

        return $safe;
    }
}