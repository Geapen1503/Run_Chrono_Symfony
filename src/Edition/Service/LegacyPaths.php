<?php

namespace App\Edition\Service;

/**
 * Chemins fichiers legacy (courses/, includes/, load_images/).
 */
final class LegacyPaths
{
    public function __construct(
        private string $legacyRoot,
        private string $loadImagesDir,
        private string $fichierCoursePrefix,
    ) {
    }

    public function legacyRoot(): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->legacyRoot), DIRECTORY_SEPARATOR);
    }

    public function loadImagesDir(): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->loadImagesDir), DIRECTORY_SEPARATOR);
    }

    public function courseDir(string $idHtml, string $year): string
    {
        return $this->legacyRoot().DIRECTORY_SEPARATOR.'courses'.DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.$idHtml;
    }

    public function tableauInscriptionPath(string $idHtml, string $year): string
    {
        return $this->courseDir($idHtml, $year).DIRECTORY_SEPARATOR.'tableau_inscription_param.php';
    }

    public function courseWrapperPath(string $idHtml, string $year): string
    {
        return $this->courseDir($idHtml, $year).DIRECTORY_SEPARATOR.$idHtml.'.php';
    }

    public function includesPath(string $idHtml): string
    {
        return $this->legacyRoot().DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.$idHtml.'.php';
    }

    public function parametreCoursePath(): string
    {
        return $this->legacyRoot().DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'parametre_course.php';
    }

  public function fichierCourseCatalog(string $idHtml, string $year): string
    {
        return sprintf(
            '%s/%s/%s/%s.php',
            trim($this->fichierCoursePrefix, '/'),
            $year,
            $idHtml,
            $idHtml,
        );
    }
}