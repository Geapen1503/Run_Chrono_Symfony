<?php

namespace App\Edition\Model;

/**
 * Configuration d'une épreuve (nom_course_1..6 + règles dossards).
 */
final class EditionCourseConfig
{
    public function __construct(
        public int $index,
        public string $nomCourse = '',
        public string $distanceMetre = '',
        public string $startTimeDefaut = '09:00:00',
        public string $nomExportOnsinscrit = '',
        public string $dossardLabelHtml = '',
        /** @var list<EditionBibRule> */
        public array $bibRules = [],
    ) {
    }

    public function isUsed(): bool
    {
        return $this->nomCourse !== '';
    }
}