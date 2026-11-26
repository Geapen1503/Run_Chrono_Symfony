<?php

namespace App\Edition\Service;

/**
 * Active une édition dans includes/parametre_course.php (ligne $ficher_de_conf_dossard).
 */
final class ParametreCourseActivator
{
    public function __construct(
        private LegacyPaths $paths,
    ) {
    }

    public function activate(string $idHtml): void
    {
        $path = $this->paths->parametreCoursePath();
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier parametre_course.php introuvable.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Lecture parametre_course.php impossible.');
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $newLine = "\$ficher_de_conf_dossard='{$idHtml}.php';";
        $foundActive = false;
        $includeIndex = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*include\s+\(\s*\$ficher_de_conf_dossard\s*\)\s*;/', $line)) {
                $includeIndex = $index;
            }
            if (preg_match('/^\$ficher_de_conf_dossard=\'[^\']+\';$/', $line)) {
                $lines[$index] = '//'.$line;
                $foundActive = true;
            }
        }

        if ($includeIndex === null) {
            throw new \RuntimeException('Ligne include ($ficher_de_conf_dossard) introuvable.');
        }

        array_splice($lines, $includeIndex, 0, [$newLine]);
        $result = implode("\n", $lines);

        if (file_put_contents($path, $result) === false) {
            throw new \RuntimeException('Écriture parametre_course.php impossible.');
        }

        if (!$foundActive) {
            // Première activation : acceptable.
        }
    }
}