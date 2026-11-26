<?php

namespace App\Edition\Service;

use App\Edition\Model\EditionBibRule;
use App\Edition\Model\EditionCourseConfig;
use App\Edition\Model\EditionPayload;
use Doctrine\DBAL\Connection;

/**
 * Liste / charge les éditions depuis param_inscription_manuelle + fichiers legacy.
 */
final class EditionCatalogReader
{
    public function __construct(
        private Connection $connection,
        private LegacyPaths $paths,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 80): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id_html, date_evenement, evenement, table_run, table_dag
             FROM param_inscription_manuelle
             ORDER BY date_evenement DESC
             LIMIT '.$limit,
        );
    }

    public function loadForEdit(string $idHtml): EditionPayload
    {
        if (!preg_match('/^20\d{6}_[A-Za-z0-9_]+$/', $idHtml)) {
            throw new \InvalidArgumentException('id_html invalide.');
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM param_inscription_manuelle WHERE id_html = :id LIMIT 1',
            ['id' => $idHtml],
        );

        if (!$row) {
            throw new \RuntimeException('Édition introuvable dans le catalogue.');
        }

        $year = substr((string) $row['date_evenement'], 0, 4);
        $slug = preg_replace('/^20\d{6}_/', '', $idHtml) ?? $idHtml;

        $payload = new EditionPayload(
            eventDate: (string) $row['date_evenement'],
            slug: $slug,
            eventName: (string) ($row['evenement'] ?? ''),
            libelleDate: (string) ($row['lib_date_evenement'] ?? ''),
            siteInscription: (string) ($row['arg_1'] ?? ''),
            afficheFilename: (string) ($row['arg_2'] ?? ''),
            afficheTarifFilename: (string) ($row['arg_4'] ?? ''),
            reglementFilename: (string) ($row['arg_5'] ?? ''),
            listeInscription: (string) ($row['arg_6'] ?? ''),
            categorieFfa: (string) ($row['categorie_ffa'] ?? 'ffa_2020_q'),
            tableRun: (string) ($row['table_run'] ?? ''),
            tableDag: (string) ($row['table_dag'] ?? ''),
            tableDepartTspreel: (string) ($row['table_depart_tspreel'] ?? ''),
            podium: (int) ($row['podium'] ?? 3),
            podiumLimit: (int) ($row['podium_limit'] ?? $row['limit'] ?? 1),
            diplome: (string) ($row['diplome'] ?? ''),
            heuresDepart: [
                (string) ($row['heure_depart_course_1'] ?? ''),
                (string) ($row['heure_depart_course_2'] ?? ''),
                (string) ($row['heure_depart_course_3'] ?? ''),
                (string) ($row['heure_depart_course_4'] ?? ''),
                (string) ($row['heure_depart_course_5'] ?? ''),
                (string) ($row['heure_depart_course_6'] ?? ''),
            ],
        );

        $courses = [];
        for ($i = 1; $i <= 6; ++$i) {
            $courses[] = new EditionCourseConfig(
                index: $i,
                nomCourse: (string) ($row['nom_course_'.$i] ?? ''),
            );
        }
        $payload->courses = $courses;

        $tableauPath = $this->paths->tableauInscriptionPath($idHtml, $year);
        if (is_file($tableauPath)) {
            $this->mergeTableauFile($tableauPath, $payload);
        }

        return $payload;
    }

    private function mergeTableauFile(string $path, EditionPayload $payload): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        if (preg_match('/\$RUNCHRONO_EDITION_META = (\{.*?\});/s', $content, $m)) {
            $meta = json_decode($m[1], true);
            if (\is_array($meta) && isset($meta['courses']) && \is_array($meta['courses'])) {
                $courses = [];
                foreach ($meta['courses'] as $item) {
                    $rules = [];
                    foreach ($item['bibRules'] ?? [] as $rule) {
                        $rules[] = new EditionBibRule(
                            type: (string) ($rule['type'] ?? 'range'),
                            min: isset($rule['min']) ? (int) $rule['min'] : null,
                            max: isset($rule['max']) ? (int) $rule['max'] : null,
                            single: isset($rule['single']) ? (int) $rule['single'] : null,
                        );
                    }
                    $courses[] = new EditionCourseConfig(
                        index: (int) ($item['index'] ?? 1),
                        nomCourse: (string) ($item['nomCourse'] ?? ''),
                        distanceMetre: (string) ($item['distanceMetre'] ?? ''),
                        startTimeDefaut: (string) ($item['startTimeDefaut'] ?? '09:00:00'),
                        nomExportOnsinscrit: (string) ($item['nomExportOnsinscrit'] ?? ''),
                        dossardLabelHtml: (string) ($item['dossardLabelHtml'] ?? ''),
                        bibRules: $rules,
                    );
                }
                if ($courses !== []) {
                    $payload->courses = $courses;
                }
            }
        }

        $map = [
            'texte_presentation' => 'textePresentation',
            'contact_nom_organisateur' => 'contactNom',
            'contact_email_organisateur' => 'contactEmail',
            'contact_telephone_organisateur' => 'contactTel',
            'contact_lieu' => 'contactLieu',
            'site_web_orga' => 'siteOrga',
            'site_web_nom' => 'siteOrgaNom',
            'classement_precedent' => 'classementPrecedent',
            'table_intermediaire' => 'tableIntermediaire',
        ];
        foreach ($map as $key => $prop) {
            if (preg_match("/'".preg_quote($key, '/')."'\\s*=>\\s*'((?:\\\\'|[^'])*)'/", $content, $match)) {
                $payload->{$prop} = stripcslashes($match[1]);
            }
        }
    }
}