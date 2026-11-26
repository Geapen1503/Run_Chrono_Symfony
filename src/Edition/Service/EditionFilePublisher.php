<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;

/**
 * Écrit les fichiers legacy sur disque.
 */
final class EditionFilePublisher
{
    public function __construct(
        private LegacyPaths $paths,
        private TableauInscriptionGenerator $tableauGenerator,
        private IncludesFileGenerator $includesGenerator,
        private CourseWrapperGenerator $wrapperGenerator,
    ) {
    }

    public function publish(EditionPayload $payload): void
    {
        $id = $payload->idHtml();
        $year = $payload->year();
        $courseDir = $this->paths->courseDir($id, $year);

        if (!is_dir($courseDir) && !mkdir($courseDir, 0775, true) && !is_dir($courseDir)) {
            throw new \RuntimeException('Impossible de créer le dossier course : '.$courseDir);
        }

        $this->write($this->paths->tableauInscriptionPath($id, $year), $this->tableauGenerator->generate($payload));
        $this->write($this->paths->courseWrapperPath($id, $year), $this->wrapperGenerator->generate($payload));
        $this->write($this->paths->includesPath($id), $this->includesGenerator->generate($payload));
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Écriture impossible : '.$path);
        }
    }
}