<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ResultatsService
{
    public const MINIMA_10KM_FFA = [
        'CA-H' => '00:38:00', 'CA-F' => '00:47:00',
        'JU-H' => '00:37:00', 'JU-F' => '00:46:00',
        'ES-H' => '00:35:00', 'ES-F' => '00:44:00',
        'SE-H' => '00:34:15', 'SE-F' => '00:43:00',
        'M0-H' => '00:35:00', 'M0-F' => '00:44:00',
        'M1-H' => '00:36:30', 'M1-F' => '00:47:00',
        'M2-H' => '00:37:00', 'M2-F' => '00:49:00',
        'M3-H' => '00:39:30', 'M3-F' => '00:50:00',
        'M4-H' => '00:40:30', 'M4-F' => '00:52:00',
        'M5-H' => '00:45:00', 'M5-F' => '00:53:00',
        'M6-H' => '00:47:00', 'M6-F' => '00:56:00',
        'M7-H' => '00:50:00', 'M7-F' => '01:00:00',
        'M8-H' => '00:53:00', 'M8-F' => '01:04:00',
        'M9-H' => '00:56:00', 'M9-F' => '01:07:00',
        'M10-H' => '01:00:00', 'M10-F' => '01:10:00',
    ];

    public const MINIMA_SEMI_FFA = [
        'JU-H' => '01:21:00', 'JU-F' => '01:55:00',
        'ES-H' => '01:17:00', 'ES-F' => '01:50:00',
        'SE-H' => '01:15:30', 'SE-F' => '01:45:00',
        'M0-H' => '01:17:30', 'M0-F' => '01:47:30',
        'M1-H' => '01:20:30', 'M1-F' => '01:50:00',
        'M2-H' => '01:22:30', 'M2-F' => '01:52:30',
        'M3-H' => '01:27:00', 'M3-F' => '01:55:00',
        'M4-H' => '01:32:00', 'M4-F' => '01:57:30',
        'M5-H' => '01:40:00', 'M5-F' => '02:00:00',
        'M6-H' => '01:44:00', 'M6-F' => '02:05:00',
        'M7-H' => '01:50:00', 'M7-F' => '02:10:00',
        'M8-H' => '01:55:00', 'M8-F' => '02:15:00',
        'M9-H' => '02:00:00', 'M9-F' => '02:20:00',
        'M10-H' => '02:15:00', 'M10-F' => '02:30:00',
    ];

    public function __construct(
        private readonly Connection $db
    ) {}

    public function tableExists(?string $tableName): bool
    {
        if (empty($tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return false;
        }

        try {
            $result = $this->db->fetchOne("SHOW TABLES LIKE :tbl", ['tbl' => $tableName]);
            return (bool)$result;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getEvenements(string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT * FROM param_inscription_manuelle
                WHERE date_evenement BETWEEN :debut AND :fin
                ORDER BY date_evenement DESC";

        $evenements = $this->db->fetchAllAssociative($sql, [
            'debut' => $dateDebut,
            'fin' => $dateFin,
        ]);

        $couleurs = ['success', 'info', 'danger', 'primary', 'warning', 'secondary'];
        $resultat = [];

        foreach ($evenements as $event) {
            $courses = [];
            $tableDag = $event['table_dag'] ?? null;
            $tableExiste = $this->tableExists($tableDag);

            for ($i = 1; $i <= 6; $i++) {
                $nomCourse = $event["nom_course_$i"] ?? null;

                if (!empty($nomCourse)) {
                    $nbArrivants = 0;
                    if ($tableExiste) {
                        try {
                            $nbArrivants = (int)$this->db->fetchOne(
                                "SELECT COUNT(*) FROM `{$tableDag}` WHERE nom_course = :nom AND nb_passage = 'Passage_1'",
                                ['nom' => $nomCourse]
                            );
                        } catch (\Throwable) {
                            $nbArrivants = 0;
                        }
                    }

                    $courses[] = [
                        'nom' => $nomCourse,
                        'heure_depart' => $event["heure_depart_course_$i"] ?? null,
                        'couleur' => $couleurs[($i - 1) % count($couleurs)],
                        'a_des_resultats' => $nbArrivants > 0,
                        'nb_arrivants' => $nbArrivants,
                    ];
                }
            }

            if (!empty($event['custom_resultat'])) {
                $event['custom_resultat'] = $this->nettoyerCustomResultat($event['custom_resultat']);
            }

            $event['courses_nettoyees'] = $courses;
            $resultat[] = $event;
        }

        return $resultat;
    }

    public function getEvenement(string $idHtml): ?array
    {
        $sql = "SELECT * FROM param_inscription_manuelle WHERE id_html = :idHtml LIMIT 1";
        $event = $this->db->fetchAssociative($sql, ['idHtml' => $idHtml]);

        return $event ?: null;
    }

    public function getClubsCourse(string $tableDag, string $nomCourse): array
    {
        if (!$this->tableExists($tableDag)) {
            return [];
        }

        try {
            $sql = "SELECT DISTINCT club 
                    FROM `{$tableDag}` 
                    WHERE nom_course = :nomCourse 
                      AND club IS NOT NULL 
                      AND TRIM(club) != '' 
                    ORDER BY club ASC";

            return $this->db->fetchFirstColumn($sql, ['nomCourse' => $nomCourse]);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getClassementCourse(string $idHtml, string $nomCourse, ?string $clubFiltre = null): ?array
    {
        $event = $this->getEvenement($idHtml);
        if (!$event) {
            return null;
        }

        $tableDag = $event['table_dag'] ?? null;
        if (!$this->tableExists($tableDag)) {
            return null;
        }

        $tableTpsReel = $event['table_depart_tspreel'] ?? null;
        $hasTpsReel = $this->tableExists($tableTpsReel);

        $params = ['nomCourse' => $nomCourse];
        $joinTpsReel = '';
        $selectTpsReel = 'NULL AS chrono_tps_reel, NULL AS tps_reel';

        if ($hasTpsReel) {
            $joinTpsReel = "LEFT JOIN `{$tableTpsReel}` r ON d.dossard = r.dossard";
            $selectTpsReel = "IF(r.tps_reel IS NOT NULL, TIMEDIFF(d.chrono_mysql, r.tps_reel), NULL) AS chrono_tps_reel, r.tps_reel";
        }

        $whereClub = '';
        if (!empty($clubFiltre)) {
            $whereClub = " AND d.club = :club";
            $params['club'] = $clubFiltre;
        }

        $sql = "SELECT d.*, {$selectTpsReel}
                FROM `{$tableDag}` d
                {$joinTpsReel}
                WHERE d.nb_passage = 'Passage_1'
                  AND d.nom_course = :nomCourse
                  {$whereClub}
                ORDER BY d.count_scracth ASC";

        $arrivants = $this->db->fetchAllAssociative($sql, $params);

        $tempsInter = [];
        $tableInter = $event['table_inter'] ?? null;
        if ($this->tableExists($tableInter)) {
            try {
                $sqlInter = "SELECT dossard, chrono_php FROM `{$tableInter}` WHERE nb_passage = 'Passage_1'";
                $rowsInter = $this->db->fetchAllAssociative($sqlInter);
                foreach ($rowsInter as $ri) {
                    $tempsInter[$ri['dossard']] = $ri['chrono_php'];
                }
            } catch (\Throwable) {
            }
        }

        $is10kmFFA = (stripos($nomCourse, '10KM') !== false && stripos($nomCourse, 'FFA') !== false);
        $isSemiFFA = (stripos($nomCourse, 'Semi') !== false && stripos($nomCourse, 'FFA') !== false);
        $isHandi = (stripos($nomCourse, 'Handisport') !== false);

        foreach ($arrivants as &$runner) {
            $licence = trim((string)($runner['licence'] ?? ''));
            $club = trim((string)($runner['club'] ?? ''));
            $runner['licence_nettoyee'] = $licence;
            $runner['club_formate'] = (strlen($licence) > 3)
                ? ($club !== '' ? "{$club} (licence {$licence})" : "Licence {$licence}")
                : ($club !== '' ? $club : 'Individuel');

            $tempsRef = !empty($runner['chrono_tps_reel']) ? $runner['chrono_tps_reel'] : ($runner['chrono_mysql'] ?? null);
            $cat = $runner['categorie_run'] ?? '';

            $qualif = null;
            if ($isHandi) {
                if (strlen($licence) > 3) {
                    $qualif = 'Q';
                }
            } elseif ($is10kmFFA && isset(self::MINIMA_10KM_FFA[$cat]) && $tempsRef) {
                if ($tempsRef <= self::MINIMA_10KM_FFA[$cat]) {
                    $qualif = (strlen($licence) > 3) ? 'Q' : 'q';
                }
            } elseif ($isSemiFFA && isset(self::MINIMA_SEMI_FFA[$cat]) && $tempsRef) {
                if ($tempsRef <= self::MINIMA_SEMI_FFA[$cat]) {
                    $qualif = (strlen($licence) > 0) ? 'Q' : 'q';
                }
            }

            $runner['qualif_ffa'] = $qualif;
            $dossard = $runner['dossard'] ?? null;
            $runner['temps_intermediaire'] = $tempsInter[$dossard] ?? null;
        }
        unset($runner);

        $clubs = $this->getClubsCourse($tableDag, $nomCourse);

        return [
            'evenement' => $event,
            'nom_course' => $nomCourse,
            'arrivants' => $arrivants,
            'clubs' => $clubs,
            'club_filtre' => $clubFiltre,
            'has_tps_reel' => $hasTpsReel,
            'has_temps_inter' => !empty($tempsInter),
            'total_arrivants' => count($arrivants),
        ];
    }

    public function getPodiumCourse(string $idHtml, string $nomCourse): ?array
    {
        $event = $this->getEvenement($idHtml);
        if (!$event) {
            return null;
        }

        $tableDag = $event['table_dag'] ?? null;
        if (!$this->tableExists($tableDag)) {
            return null;
        }

        $podiumNb = !empty($event['podium']) && (int)$event['podium'] > 0 ? (int)$event['podium'] : 3;
        $limitCat = !empty($event['podium_limit']) && (int)$event['podium_limit'] > 0 ? (int)$event['podium_limit'] : 1;
        $colonneCat = ($event['categorie_ffa'] ?? '') === 'ffa_2019' ? 'categorie_2019' : 'categorie_run';

        $sql = "SELECT *
                FROM `{$tableDag}`
                WHERE nb_passage = 'Passage_1'
                  AND nom_course = :nomCourse
                ORDER BY count_scracth ASC";

        $tousArrivants = $this->db->fetchAllAssociative($sql, ['nomCourse' => $nomCourse]);

        $scratchFemmes = [];
        $scratchHommes = [];
        $scratchMixtes = [];
        $categoriesMap = [];

        foreach ($tousArrivants as $runner) {
            $sexe = strtoupper(trim((string)($runner['sexe'] ?? '')));
            $countSexe = (int)($runner['count_sexe'] ?? 0);
            $categorie = $runner[$colonneCat] ?? $runner['categorie_run'] ?? 'Autre';

            if ($sexe === 'F' && count($scratchFemmes) < $podiumNb) {
                $scratchFemmes[] = $runner;
            } elseif ($sexe === 'H' && count($scratchHommes) < $podiumNb) {
                $scratchHommes[] = $runner;
            } elseif ($sexe === 'X' && count($scratchMixtes) < $podiumNb) {
                $scratchMixtes[] = $runner;
            }

            if ($countSexe > $podiumNb) {
                if (!isset($categoriesMap[$categorie])) {
                    $categoriesMap[$categorie] = [];
                }
                if (count($categoriesMap[$categorie]) < $limitCat) {
                    $categoriesMap[$categorie][] = $runner;
                }
            }
        }

        return [
            'evenement' => $event,
            'nom_course' => $nomCourse,
            'podium_nb' => $podiumNb,
            'limit_cat' => $limitCat,
            'colonne_cat' => $colonneCat,
            'scratch_femmes' => $scratchFemmes,
            'scratch_hommes' => $scratchHommes,
            'scratch_mixtes' => $scratchMixtes,
            'categories' => $categoriesMap,
            'total_arrivants' => count($tousArrivants),
        ];
    }

    public function getTopEvenement(string $idHtml, int $top = 3): ?array
    {
        $event = $this->getEvenement($idHtml);
        if (!$event) {
            return null;
        }

        $tableDag = $event['table_dag'] ?? null;
        if (!$this->tableExists($tableDag)) {
            return null;
        }

        $coursesResultats = [];

        for ($i = 1; $i <= 6; $i++) {
            $nomCourse = $event["nom_course_$i"] ?? null;
            if (empty($nomCourse)) {
                continue;
            }

            $podiumData = $this->getPodiumCourse($idHtml, $nomCourse);
            if ($podiumData && $podiumData['total_arrivants'] > 0) {
                $coursesResultats[] = [
                    'nom_course' => $nomCourse,
                    'podium' => $podiumData,
                ];
            }
        }

        return [
            'evenement' => $event,
            'top' => $top,
            'courses' => $coursesResultats,
        ];
    }

    public function getSpecialResultat(string $slug): ?array
    {
        $normalizedSlug = strtolower(trim($slug));

        if (str_contains($normalizedSlug, 'finale_biathrun_hf') || $normalizedSlug === 'biathrun-finale-hf') {
            $table = 'dag_import_biathrun_final2026';
            if (!$this->tableExists($table)) {
                return null;
            }

            $sql = "SELECT nom_prenom, sexe,
                    CASE
                        WHEN annee BETWEEN 2011 AND 2014 THEN 'BiathRun_Ados'
                        WHEN annee BETWEEN 2015 AND 2018 THEN 'BiathRun_Enfants'
                        ELSE 'Finale BiathRun'
                    END AS categorie,
                    MIN(chrono_mysql) AS chrono_final,
                    TIME_TO_SEC(MIN(chrono_mysql)) AS total_seconds
                    FROM `{$table}`
                    WHERE nom_course = 'Final_Dames_Hommes_12h30'
                    GROUP BY nom_prenom, sexe, categorie
                    ORDER BY total_seconds ASC";

            $rows = $this->db->fetchAllAssociative($sql);
            $femmes = [];
            $hommes = [];
            foreach ($rows as $r) {
                if (($r['sexe'] ?? '') === 'F') {
                    $femmes[] = $r;
                } else {
                    $hommes[] = $r;
                }
            }

            return [
                'type' => 'biathrun_finale',
                'titre' => 'Classement Finale BiathRun Hommes Femmes 2026',
                'femmes' => $femmes,
                'hommes' => $hommes,
            ];
        }

        if (str_contains($normalizedSlug, 'qualif_biathrun_jeunes') || $normalizedSlug === 'biathrun-jeunes') {
            $table = 'dag_import_biathrun_qualif2026';
            if (!$this->tableExists($table)) {
                return null;
            }

            $sql = "SELECT nom_prenom, sexe, annee,
                    MIN(CASE WHEN nom_course = 'Course_Qualif_Jeunes_11h15' THEN chrono_mysql END) AS chrono_1,
                    MIN(CASE WHEN nom_course = 'Course_Qualif_Jeunes_11h45' THEN chrono_mysql END) AS chrono_2,
                    SEC_TO_TIME(
                        IFNULL(TIME_TO_SEC(MIN(CASE WHEN nom_course = 'Course_Qualif_Jeunes_11h15' THEN chrono_mysql END)),0) +
                        IFNULL(TIME_TO_SEC(MIN(CASE WHEN nom_course = 'Course_Qualif_Jeunes_11h45' THEN chrono_mysql END)),0)
                    ) AS cumul_chrono
                    FROM `{$table}`
                    GROUP BY nom_prenom, sexe, annee";

            $rows = $this->db->fetchAllAssociative($sql);
            $ados = [];
            $enfants = [];

            foreach ($rows as $r) {
                $annee = (int)($r['annee'] ?? 0);
                if ($annee >= 2011 && $annee <= 2014) {
                    $ados[] = $r;
                } else {
                    $enfants[] = $r;
                }
            }

            $sortFn = function ($a, $b) {
                return strcmp($a['cumul_chrono'] ?? '99:99:99', $b['cumul_chrono'] ?? '99:99:99');
            };
            usort($ados, $sortFn);
            usort($enfants, $sortFn);

            return [
                'type' => 'biathrun_jeunes',
                'titre' => 'Classement Qualifications BiathRun Jeunes 2026',
                'ados' => $ados,
                'enfants' => $enfants,
            ];
        }

        if (str_contains($normalizedSlug, 'qualifies_biathrun_finale') || $normalizedSlug === 'biathrun-qualifies') {
            $table = 'dag_import_biathrun_qualif2026';
            if (!$this->tableExists($table)) {
                return null;
            }

            $sql = "SELECT nom, prenom, annee, club, nom_prenom, sexe,
                    COUNT(chrono_mysql) AS nb_courses,
                    SEC_TO_TIME(
                        IFNULL(TIME_TO_SEC(MIN(chrono_mysql)),0) +
                        IFNULL(TIME_TO_SEC(MAX(chrono_mysql)),0)
                    ) AS cumul_chrono
                    FROM `{$table}`
                    WHERE nom_prenom NOT LIKE 'Non attribue'
                    GROUP BY nom_prenom, sexe";

            $rows = $this->db->fetchAllAssociative($sql);
            usort($rows, function ($a, $b) {
                if ($a['nb_courses'] == $b['nb_courses']) {
                    return strcmp($a['cumul_chrono'] ?? '', $b['cumul_chrono'] ?? '');
                }
                return ($b['nb_courses'] <=> $a['nb_courses']);
            });

            $femmes = [];
            $hommes = [];
            foreach ($rows as $r) {
                if (($r['sexe'] ?? '') === 'F') {
                    $r['qualif'] = (count($femmes) < 10) ? 'QUALIF' : '';
                    $femmes[] = $r;
                } else {
                    $r['qualif'] = (count($hommes) < 20) ? 'QUALIF' : '';
                    $hommes[] = $r;
                }
            }

            return [
                'type' => 'biathrun_qualifies',
                'titre' => 'Qualifiés Finale BiathRun Hommes Femmes 2026',
                'femmes' => $femmes,
                'hommes' => $hommes,
            ];
        }

        if (str_contains($normalizedSlug, 'qualif_biathrun_hommes_femmes') || $normalizedSlug === 'biathrun-qualif-hf') {
            $table = 'dag_import_biathrun_qualif2026';
            if (!$this->tableExists($table)) {
                return null;
            }

            $sql = "SELECT nom_prenom, sexe,
                    MIN(chrono_mysql) AS chrono_1,
                    IF(COUNT(*) > 1, MAX(chrono_mysql), NULL) AS chrono_2,
                    SEC_TO_TIME(SUM(TIME_TO_SEC(chrono_mysql))) AS cumul_chrono,
                    COUNT(*) AS nb_courses
                    FROM `{$table}`
                    GROUP BY nom_prenom, sexe";

            $rows = $this->db->fetchAllAssociative($sql);
            usort($rows, function ($a, $b) {
                if ($a['nb_courses'] == $b['nb_courses']) {
                    return strcmp($a['cumul_chrono'] ?? '', $b['cumul_chrono'] ?? '');
                }
                return ($b['nb_courses'] <=> $a['nb_courses']);
            });

            $femmes = [];
            $hommes = [];
            foreach ($rows as $r) {
                if (($r['sexe'] ?? '') === 'F') {
                    $r['qualif'] = (count($femmes) < 10) ? 'QUALIF' : '';
                    $femmes[] = $r;
                } else {
                    $r['qualif'] = (count($hommes) < 20) ? 'QUALIF' : '';
                    $hommes[] = $r;
                }
            }

            return [
                'type' => 'biathrun_qualif_hf',
                'titre' => 'Résultats Qualifications BiathRun 2026',
                'femmes' => $femmes,
                'hommes' => $hommes,
            ];
        }

        if (str_contains($normalizedSlug, 'challenge_entreprises') || $normalizedSlug === 'technotrail-entreprises') {
            $table = 'dag_import_technotrail_2026';
            if (!$this->tableExists($table)) {
                return null;
            }

            $sql = "SELECT club, COUNT(*) AS nb_coureurs,
                    CASE
                        WHEN COUNT(DISTINCT sexe) = 1 AND MAX(sexe) = 'F' THEN 'F'
                        WHEN COUNT(DISTINCT sexe) = 1 AND MAX(sexe) = 'H' THEN 'H'
                        ELSE 'X'
                    END AS sexe_club,
                    SEC_TO_TIME(SUM(TIME_TO_SEC(chrono_mysql))) AS chrono_total,
                    SUM(TIME_TO_SEC(chrono_mysql)) AS chrono_total_sec,
                    GROUP_CONCAT(
                        CONCAT(nom_prenom, ' (', TIME_FORMAT(chrono_mysql,'%H:%i:%s'), ')')
                        ORDER BY chrono_mysql
                        SEPARATOR '||'
                    ) AS coureurs
                    FROM `{$table}`
                    WHERE club LIKE 'Eq %'
                    GROUP BY club
                    HAVING COUNT(*) = 4
                    ORDER BY chrono_total_sec ASC";

            $rows = $this->db->fetchAllAssociative($sql);

            return [
                'type' => 'challenge_entreprises',
                'titre' => 'Classement Challenge Entreprises Technotrail 2026',
                'equipes' => $rows,
            ];
        }

        if (str_contains($normalizedSlug, 'relais_des_asperges') || $normalizedSlug === 'relais-asperges') {
            $tablePassage = 'passage_relais_asperge_2026';
            $tableRun = 'run_relais_asperge_2026';
            if (!$this->tableExists($tablePassage) || !$this->tableExists($tableRun)) {
                return null;
            }

            $sql = "SELECT p.NUM_DOSSARD AS dossard, 
                    MAX(p.nb_passage) AS nb_passage, 
                    MAX(p.TIME_DOSSARD) AS heure_dernier_passage, 
                    r.nom_prenom, r.nom, r.prenom, r.sexe, r.categorie, r.annee, r.naissance, r.club, r.licence, r.distance
                    FROM `{$tablePassage}` p  
                    LEFT JOIN `{$tableRun}` r ON (p.NUM_DOSSARD = r.dossard) 
                    WHERE (r.abandon = 0 OR r.abandon IS NULL) AND p.nb_passage > 0 AND p.nb_passage <= 6
                    GROUP BY p.NUM_DOSSARD 
                    ORDER BY MAX(p.nb_passage) DESC, MAX(p.id_dossard) ASC";

            $rows = $this->db->fetchAllAssociative($sql);

            foreach ($rows as &$r) {
                $nb = (int)($r['nb_passage'] ?? 0);
                $r['distance_km'] = match ($nb) {
                    1 => 2.5,
                    2 => 5.0,
                    3 => 7.5,
                    4 => 10.0,
                    5 => 12.5,
                    6 => 15.0,
                    default => 0.0,
                };
            }
            unset($r);

            return [
                'type' => 'relais',
                'titre' => 'Suivi Live du Relais des Asperges 2026',
                'lignes' => $rows,
            ];
        }

        if (str_contains($normalizedSlug, 'vetathlon') || $normalizedSlug === 'vetathlon') {
            $tablePassage = 'passage_vetathlon_police_2026';
            $tableRun = 'run_vetathlon_police_2026';
            if (!$this->tableExists($tablePassage) || !$this->tableExists($tableRun)) {
                return null;
            }

            $sql = "SELECT p.NUM_DOSSARD AS dossard, 
                    MAX(p.nb_passage) AS nb_passage, 
                    MAX(p.TIME_DOSSARD) AS heure_dernier_passage, 
                    r.nom_prenom, r.nom, r.prenom, r.sexe, r.categorie, r.annee, r.naissance, r.club, r.licence, r.distance
                    FROM `{$tablePassage}` p  
                    LEFT JOIN `{$tableRun}` r ON (p.NUM_DOSSARD = r.dossard) 
                    WHERE (r.abandon = 0 OR r.abandon IS NULL) AND p.nb_passage > 0
                    GROUP BY p.NUM_DOSSARD 
                    ORDER BY MAX(p.nb_passage) DESC, MAX(p.id_dossard) ASC";

            $rows = $this->db->fetchAllAssociative($sql);

            return [
                'type' => 'vetathlon',
                'titre' => 'Résultat du Vétathlon 2026',
                'lignes' => $rows,
            ];
        }

        if (str_contains($normalizedSlug, 'ekiden') || $normalizedSlug === 'ekiden') {
            $tablePassage = 'passage_ekiden_2026';
            $tableRun = 'run_ekiden_poitiers_2026';
            if (!$this->tableExists($tablePassage) || !$this->tableExists($tableRun)) {
                return null;
            }

            $sql = "SELECT p.NUM_DOSSARD AS dossard, 
                    MAX(p.nb_passage) AS nb_passage, 
                    MAX(p.TIME_DOSSARD) AS heure_dernier_passage, 
                    r.nom_prenom, r.nom, r.prenom, r.sexe, r.categorie, r.annee, r.naissance, r.club, r.licence
                    FROM `{$tablePassage}` p  
                    LEFT JOIN `{$tableRun}` r ON (p.NUM_DOSSARD = r.dossard) 
                    WHERE p.nb_passage > 0 
                    GROUP BY p.NUM_DOSSARD 
                    ORDER BY MAX(p.nb_passage) DESC, MAX(p.id_dossard) ASC";

            $rows = $this->db->fetchAllAssociative($sql);

            return [
                'type' => 'ekiden',
                'titre' => 'Suivi Live et Résultats de l\'Ekiden de Poitiers 2026',
                'lignes' => $rows,
            ];
        }

        return null;
    }

    private function nettoyerCustomResultat(string $html): string
    {
        $corruptions = [
            'R??«sultats' => 'Résultats',
            'R??«sultat' => 'Résultat',
            'R?®sultats' => 'Résultats',
            'R?®sultat' => 'Résultat',
            '??í partir de' => 'à partir de',
            'Qualifi??«s' => 'Qualifiés',
            'Qualifi??«' => 'Qualifié',
            'Deuxi???me ??«tape' => 'Deuxième étape',
            ' Deuxi???me' => ' Deuxième',
            '??«tape' => 'étape',
        ];
        $html = str_replace(array_keys($corruptions), array_values($corruptions), $html);

        $html = preg_replace(
            '/href=[\'"][^\'"]*classement_finale_biathrun_hf_2026\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/biathrun-finale-hf"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*classement_qualif_biathrun_jeunes_2026\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/biathrun-jeunes"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*qualifies_biathrun_finale_hommes_femmes_2026\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/biathrun-qualifies"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*classement_qualif_biathrun_hommes_femmes_2026\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/biathrun-qualif-hf"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*20260514_Relais_Des_Asperges[^\'"]*race_detail\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/relais-asperges"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*20260521_Vetathlon[^\'"]*race_detail\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/vetathlon"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*resultat_challenge_entreprises\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/technotrail-entreprises"',
            $html
        );
        $html = preg_replace(
            '/href=[\'"][^\'"]*20260628_Ekiden_Poitiers[^\'"]*race_detail\.php[^\'"]*[\'"]/i',
            'href="/rc/resultats/special/ekiden"',
            $html
        );

        return $html;
    }
}
