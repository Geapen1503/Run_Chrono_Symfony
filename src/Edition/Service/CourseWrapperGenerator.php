<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;

/**
 * Génère courses/YEAR/id_html/id_html.php (wrapper inscription publique).
 */
final class CourseWrapperGenerator
{
    public function __construct(
        private LegacyPaths $paths,
    ) {
    }

    public function generate(EditionPayload $payload): string
    {
        $fichierCourse = $this->paths->fichierCourseCatalog($payload->idHtml(), $payload->year());

        return <<<PHP
<?php
include dirname(__FILE__).'/tableau_inscription_param.php';
\$site_chronometrage=\$tableau_inscription_param['site_inscription'];
\$affiche=\$tableau_inscription_param['nom_fichier_affiche'];
\$tarif=\$tableau_inscription_param['nom_fichier_affiche_tarif'];
\$fichier_reglement = \$tableau_inscription_param['fichier_reglement'];
\$liste_des_inscris=\$tableau_inscription_param['liste_inscription'];
\$id_html=\$tableau_inscription_param['id_html'];
\$evenement=\$tableau_inscription_param['nom_evenement'];
\$evenement_date=\$tableau_inscription_param['libelle_evenement_date'];
\$date_evenement=\$tableau_inscription_param['date_evenement'];
\$nom_course_1 = \$tableau_inscription_param['nom_course_1'];
\$nom_course_2 = \$tableau_inscription_param['nom_course_2'];
\$nom_course_3 = \$tableau_inscription_param['nom_course_3'];
\$nom_course_4 = \$tableau_inscription_param['nom_course_4'];
\$nom_course_5 = \$tableau_inscription_param['nom_course_5'];
\$nom_course_6 = \$tableau_inscription_param['nom_course_6'];
\$table_dag = \$tableau_inscription_param['table_dag_import'];
\$table_run =  \$tableau_inscription_param['table_run'];
\$categorie_ffa =  \$tableau_inscription_param['categorie_ffa'];
\$fichier_course = '{$fichierCourse}';
include 'courses/2022/presentation_course.php';
?>
PHP;
    }
}