<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionPayload;
use App\Edition\Support\LegacyStringHelper;
use Doctrine\DBAL\Connection;

/**
 * UPSERT catalogue param_inscription_manuelle (comme alwaysdata_sql / localhost_sql).
 */
final class ParamInscriptionCatalogSync
{
    public function __construct(
        private Connection $connection,
        private LegacyPaths $paths,
    ) {
    }

    public function sync(EditionPayload $payload): void
    {
        $idHtml = $payload->idHtml();
        $fichierCourse = $this->paths->fichierCourseCatalog($idHtml, $payload->year());

        $this->connection->executeStatement(
            'DELETE FROM param_inscription_manuelle WHERE id_html = :id',
            ['id' => $idHtml],
        );

        $courses = $payload->courses;
        $nom = static function (int $i) use ($courses): string {
            foreach ($courses as $course) {
                if ($course->index === $i) {
                    return $course->nomCourse;
                }
            }

            return '';
        };

        $this->connection->executeStatement(
            'INSERT INTO param_inscription_manuelle (
                id_html, table_depart_tspreel, date_evenement, fichier_course,
                lib_date_evenement, evenement,
                nom_course_1, nom_course_2, nom_course_3, nom_course_4, nom_course_5, nom_course_6,
                table_dag, table_run, categorie_ffa,
                arg_1, arg_2, arg_3, arg_4, arg_5, arg_6,
                podium, podium_limit, diplome,
                heure_depart_course_1, heure_depart_course_2, heure_depart_course_3,
                heure_depart_course_4, heure_depart_course_5, heure_depart_course_6
            ) VALUES (
                :id_html, :table_depart_tspreel, :date_evenement, :fichier_course,
                :lib_date, :evenement,
                :c1, :c2, :c3, :c4, :c5, :c6,
                :table_dag, :table_run, :categorie_ffa,
                :arg_1, :arg_2, :arg_3, :arg_4, :arg_5, :arg_6,
                :podium, :podium_limit, :diplome,
                :h1, :h2, :h3, :h4, :h5, :h6
            )',
            [
                'id_html' => $idHtml,
                'table_depart_tspreel' => $payload->tableDepartTspreel,
                'date_evenement' => $payload->eventDate,
                'fichier_course' => $fichierCourse,
                'lib_date' => LegacyStringHelper::strToNoAccent($payload->libelleDate),
                'evenement' => LegacyStringHelper::strToNoAccent($payload->eventName),
                'c1' => $nom(1),
                'c2' => $nom(2),
                'c3' => $nom(3),
                'c4' => $nom(4),
                'c5' => $nom(5),
                'c6' => $nom(6),
                'table_dag' => $payload->tableDag,
                'table_run' => $payload->tableRun,
                'categorie_ffa' => $payload->categorieFfa,
                'arg_1' => $payload->siteInscription,
                'arg_2' => $payload->afficheFilename,
                'arg_3' => ' ',
                'arg_4' => $payload->afficheTarifFilename,
                'arg_5' => $payload->reglementFilename,
                'arg_6' => $payload->listeInscription,
                'podium' => $payload->podium,
                'podium_limit' => $payload->podiumLimit,
                'diplome' => $payload->diplome,
                'h1' => $payload->heuresDepart[0] ?? '',
                'h2' => $payload->heuresDepart[1] ?? '',
                'h3' => $payload->heuresDepart[2] ?? '',
                'h4' => $payload->heuresDepart[3] ?? '',
                'h5' => $payload->heuresDepart[4] ?? '',
                'h6' => $payload->heuresDepart[5] ?? '',
            ],
        );
    }
}