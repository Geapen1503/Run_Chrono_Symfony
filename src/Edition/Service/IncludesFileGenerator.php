<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionCourseConfig;
use App\Edition\Model\EditionPayload;

/**
 * Génère includes/{id_html}.php (tab_param + boilerplate legacy).
 */
final class IncludesFileGenerator
{
    public function __construct(
        private string $boilerplatePath,
    ) {
    }

    public function generate(EditionPayload $payload): string
    {
        $id = $payload->idHtml();
        $year = $payload->year();
        $head = $this->buildHead($id, $year);
        $tabParam = $this->buildTabParamFunction($payload);
        $tail = $this->loadBoilerplate();

        return $head."\n".$tabParam."\n".$tail;
    }

    private function buildHead(string $id, string $year): string
    {
        return <<<PHP
<?php
/*
// on importe le fichier de paramètre qui contient le tableau \$tableau_inscription_param\t
*/

\$ficher_param_course=dirname(__FILE__).'./../courses/{$year}/'.basename(__FILE__, '.php').'/tableau_inscription_param.php';
\$dossier_param_course=dirname(__FILE__).'./../courses/{$year}/'.basename(__FILE__, '.php');
include  (\$ficher_param_course);
include 'time.php';

\$id_html=\$tableau_inscription_param['id_html'];

PHP;
    }

    private function buildTabParamFunction(EditionPayload $payload): string
    {
        $parts = [];
        $parts[] = 'function tab_param($num_dossard){';

        foreach ($payload->courses as $course) {
            if (!$course->isUsed()) {
                continue;
            }
            $idx = $course->index;
            $label = $this->q($course->dossardLabelHtml !== '' ? $course->dossardLabelHtml : $course->nomCourse);
            $parts[] = "\t\$tab_param_course_{$idx}=array(";
            $parts[] = "\t\t'le_dossard_existe' => '{$label}',";
            $parts[] = "\t\t'nom_course' => '".$this->q($course->nomCourse)."',";
            $parts[] = "\t\t'distance_metre' => '".$this->q($course->distanceMetre)."',";
            $parts[] = "\t\t'start_time_defaut' => '".$this->q($course->startTimeDefaut)."',";
            $parts[] = "\t\t'nom_export_chronometrage' => '',";
            $parts[] = "\t\t'nom_export_distance_onsincrit' => '".$this->q($course->nomExportOnsinscrit)."'";
            $parts[] = "\t);";
        }

        $parts[] = "\t\$tab_param_else=array(";
        $parts[] = "\t\t'le_dossard_existe'=>  'DOSSARD_NON_CODE',";
        $parts[] = "\t\t'nom_course' => 'COURSE_NON_CODE',";
        $parts[] = "\t\t'distance_metre' => '15000',";
        $parts[] = "\t\t'start_time_defaut' => '09:30:00'";
        $parts[] = "\t);";
        $parts[] = "\tinclude 'time.php';";

        for ($i = 1; $i <= 6; ++$i) {
            $parts[] = "\tif(isset(\$tab_param_course_{$i}['start_time_defaut'])) {\$tab_param_course_{$i}['start_time_defaut']   = \$heure_depart_course_{$i};}";
        }

        $parts[] = $this->buildDossardRules($payload->courses);

        for ($i = 1; $i <= 6; ++$i) {
            $parts[] = "\telseif (\$num_dossard==-{$i}) { return \$tab_param_course_{$i}; }";
        }
        $parts[] = "\telse return \$tab_param_else;";
        $parts[] = '}';

        return implode("\n", $parts);
    }

    /**
     * @param list<EditionCourseConfig> $courses
     */
    private function buildDossardRules(array $courses): string
    {
        $lines = [];
        $first = true;
        foreach ($courses as $course) {
            if (!$course->isUsed()) {
                continue;
            }
            foreach ($course->bibRules as $rule) {
                if ($rule->type === 'single' && $rule->single !== null) {
                    $keyword = $first ? 'if' : 'elseif';
                    $lines[] = sprintf(
                        "\t%s (\$num_dossard==%d ) { return \$tab_param_course_%d; }",
                        $keyword,
                        $rule->single,
                        $course->index,
                    );
                    $first = false;
                }
                if ($rule->type === 'range' && $rule->min !== null && $rule->max !== null) {
                    $keyword = $first ? 'if' : 'elseif';
                    $lines[] = sprintf(
                        "\t%s (\$num_dossard>=%d AND \$num_dossard<=%d) { return \$tab_param_course_%d; }",
                        $keyword,
                        $rule->min,
                        $rule->max,
                        $course->index,
                    );
                    $first = false;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function q(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    private function loadBoilerplate(): string
    {
        if (!is_file($this->boilerplatePath)) {
            throw new \RuntimeException('Boilerplate includes manquant.');
        }
        $content = file_get_contents($this->boilerplatePath);

        return $content === false ? '' : $content;
    }
}