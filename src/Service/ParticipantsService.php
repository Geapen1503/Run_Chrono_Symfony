<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Service métier pour la gestion et la synthèse des participants (tables run_*).
 */
class ParticipantsService
{
    private const STATUTS_PPS_MANQUANTS = ['Aucun fichier', 'Date non valide', 'En relecture'];
    private const STATUTS_PPS_NON_CONFORMES = ['Manque une mention', 'Illisible'];
    private const STATUTS_MINEUR_LITIGE = ['Aucun fichier', 'En relecture', 'Manque une mention'];

    private const ORDRE_CATEGORIES_FFA_2020 = [
        'MI-F', 'MI-H', 'CA-F', 'CA-H', 'JU-F', 'JU-H', 'ES-F', 'ES-H',
        'SE-F', 'SE-H', 'M0-F', 'M0-H', 'M1-F', 'M1-H', 'M2-F', 'M2-H',
        'M3-F', 'M3-H', 'M4-F', 'M4-H', 'M5-F', 'M5-H', 'M6-F', 'M6-H',
        'M7-F', 'M7-H', 'M8-F', 'M8-H', 'M9-F', 'M9-H', 'M10-F', 'M10-H',
    ];

    private const ORDRE_CATEGORIES_FFA_2019 = [
        'MI-F', 'MI-H', 'CA-F', 'CA-H', 'JU-F', 'JU-H', 'ES-F', 'ES-H',
        'SE/M0-F', 'SE/M0-H', 'M1/M2-F', 'M1/M2-H', 'M3/M4-F', 'M3/M4-H',
        'M5/M6-F', 'M5/M6-H', 'M7/M8-F', 'M7/M8-H', 'M9/M10-F', 'M9/M10-H',
    ];

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Construit l'ensemble des données de la page « Liste complète des participants »
     * pour un événement donné issu de param_inscription_manuelle.
     *
     * @param array<string, mixed> $evenement
     * @return array<string, mixed>
     */
    public function getListeComplete(array $evenement): array
    {
        $tableRun = $evenement['table_run'] ?? null;
        $tableDag = $evenement['table_dag'] ?? null;
        $categorieFfa = (string) ($evenement['categorie_ffa'] ?? 'ffa_2020');

        if (!$this->tableExists($tableRun)) {
            return [
                'table_run_existe' => false,
                'table_dag_existe' => $this->tableExists($tableDag),
                'has_pps_schema' => false,
                'mode_categorie_regroupee' => !in_array($categorieFfa, ['ffa_2020', 'ffa_2020_q'], true),
                'tri_par_sexe_nom' => $categorieFfa === 'ffa_2020_q',
                'total_inscrits' => 0,
                'totaux' => $this->creerCompteurSexeVide(),
                'courses_stats' => [],
                'categories_matrice' => ['colonnes' => [], 'lignes' => [], 'totaux' => []],
                'homonymes' => [],
                'doublons_dossards' => [],
                'litiges' => [],
                'abandons' => [],
                'rgpd_liste' => [],
                'courses_participants' => [],
                'has_partants' => false,
            ];
        }

        $colonnes = $this->getColonnesTable($tableRun);
        $hasPpsSchema = $this->possedeColonnesPps($colonnes);
        $modeRegroupe = !in_array($categorieFfa, ['ffa_2020', 'ffa_2020_q'], true);
        $triParSexeNom = ($categorieFfa === 'ffa_2020_q');

        // 1. Chargement unique de tous les participants de la table run_*
        $lignesBrutes = $this->db->fetchAllAssociative("SELECT * FROM `{$tableRun}`");
        $participants = [];
        foreach ($lignesBrutes as $ligne) {
            $participants[] = $this->enrichirParticipant($ligne, $hasPpsSchema, $modeRegroupe);
        }

        // 2. Liste ordonnée des courses (paramètres + éventuelles distances imprévues en base)
        $coursesOrdonnees = $this->extraireCoursesOrdonnees($evenement, $participants);

        // 3. Données complémentaires DAG (arrivants + abandons) et départs (import_dag_depart)
        $tableDagExiste = $this->tableExists($tableDag);
        $dagStats = $tableDagExiste ? $this->chargerStatsDag($tableDag) : ['arrivants' => [], 'abandons' => []];
        $departsParCourse = $this->chargerStatsDeparts($tableRun);
        $hasPartants = array_sum($departsParCourse) > 0;

        // 4. Construction des statistiques par course et des totaux globaux
        $statsParCourse = $this->construireStatsParCourse(
            $coursesOrdonnees,
            $participants,
            $dagStats['arrivants'],
            $departsParCourse,
            $evenement
        );

        // 5. Fusion des abandons (table_dag + colonne abandon=1 dans table_run)
        $abandons = $this->fusionnerAbandons($dagStats['abandons'], $participants);

        // 6. Matrice des catégories FFA
        $categoriesMatrice = $this->construireMatriceCategories($coursesOrdonnees, $participants, $modeRegroupe);

        // 7. Homonymes, doublons de dossards, litiges et RGPD
        $homonymes = $this->detecterHomonymes($participants);
        $doublonsDossards = $this->detecterDoublonsDossards($participants);
        $litiges = $this->extraireLitiges($participants, $coursesOrdonnees);
        $rgpdListe = array_values(array_filter($participants, static fn (array $p): bool => $p['rgpd']));

        // 8. Listes des participants groupées par course avec indices de tri (Nom & Dossard)
        $coursesParticipants = $this->grouperEtTrierParticipants($coursesOrdonnees, $participants, $triParSexeNom);

        $totaux = $this->creerCompteurSexeVide();
        foreach ($participants as $p) {
            $totaux['total']++;
            if ($p['sexe'] === 'F') {
                $totaux['F']++;
            } elseif ($p['sexe'] === 'H') {
                $totaux['H']++;
            } else {
                $totaux['X']++;
            }
        }
        $totaux['arrivants'] = array_sum(array_column($statsParCourse, 'arrivants'));
        $totaux['partants'] = array_sum(array_column($statsParCourse, 'partants'));

        return [
            'table_run_existe' => true,
            'table_dag_existe' => $tableDagExiste,
            'has_pps_schema' => $hasPpsSchema,
            'mode_categorie_regroupee' => $modeRegroupe,
            'tri_par_sexe_nom' => $triParSexeNom,
            'total_inscrits' => count($participants),
            'totaux' => $totaux,
            'courses_stats' => $statsParCourse,
            'categories_matrice' => $categoriesMatrice,
            'homonymes' => $homonymes,
            'doublons_dossards' => $doublonsDossards,
            'litiges' => $litiges,
            'abandons' => $abandons,
            'rgpd_liste' => $rgpdListe,
            'courses_participants' => $coursesParticipants,
            'has_partants' => $hasPartants,
        ];
    }

    /**
     * Vérifie de manière sécurisée qu'une table SQL existe dans la base courante.
     */
    public function tableExists(?string $tableName): bool
    {
        if (empty($tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return false;
        }

        try {
            return (bool) $this->db->fetchOne('SHOW TABLES LIKE :tbl', ['tbl' => $tableName]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Liste les colonnes réelles d'une table MySQL donnée.
     *
     * @return list<string>
     */
    public function getColonnesTable(string $tableName): array
    {
        if (empty($tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return [];
        }

        try {
            $rows = $this->db->fetchAllAssociative("SHOW COLUMNS FROM `{$tableName}`");

            return array_values(array_filter(array_map(
                static fn (array $r): string => (string) ($r['Field'] ?? ''),
                $rows
            )));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Équivalent modernisé de describe_pps() : vérifie la présence des colonnes de suivi PPS/litiges.
     *
     * @param list<string> $colonnes
     */
    private function possedeColonnesPps(array $colonnes): bool
    {
        foreach (['pps', 'att_mineur', 'anotations', 'email', 'phone'] as $requise) {
            if (!in_array($requise, $colonnes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalise et enrichit une ligne brute de la table run_*.
     *
     * @param array<string, mixed> $ligne
     * @return array<string, mixed>
     */
    private function enrichirParticipant(array $ligne, bool $hasPpsSchema, bool $modeRegroupe): array
    {
        $dossardStr = trim((string) ($ligne['dossard'] ?? ''));
        $nom = trim((string) ($ligne['nom'] ?? ''));
        $prenom = trim((string) ($ligne['prenom'] ?? ''));
        $nomPrenom = trim((string) ($ligne['nom_prenom'] ?? ''));

        if ($nomPrenom === '') {
            $nomPrenom = trim("{$nom} {$prenom}");
        }
        if ($nom === '' && $nomPrenom !== '') {
            $nom = $nomPrenom;
        }

        $sexe = strtoupper(trim((string) ($ligne['sexe'] ?? '')));
        if (!in_array($sexe, ['F', 'H', 'X'], true)) {
            $sexe = $sexe !== '' ? $sexe : 'X';
        }

        $categorie2020 = trim((string) ($ligne['categorie'] ?? ''));
        $categorie2019 = trim((string) ($ligne['categorie_2019'] ?? ''));
        $categorieAffichee = $modeRegroupe && $categorie2019 !== '' ? $categorie2019 : ($categorie2020 !== '' ? $categorie2020 : $categorie2019);

        $club = trim((string) ($ligne['club'] ?? ''));
        $licence = trim((string) ($ligne['licence'] ?? ''));

        $litige = $hasPpsSchema
            ? $this->evaluerLitige($ligne)
            : ['est_en_litige' => false, 'motif' => null, 'annotation' => null, 'type' => null];

        return [
            'id_dossard' => isset($ligne['id_dossard']) ? (int) $ligne['id_dossard'] : null,
            'dossard' => $dossardStr,
            'dossard_num' => ctype_digit($dossardStr) ? (int) $dossardStr : (int) preg_replace('/\D+/', '', $dossardStr),
            'nom' => $nom,
            'prenom' => $prenom,
            'nom_complet' => $nomPrenom,
            'distance' => trim((string) ($ligne['distance'] ?? '')),
            'sexe' => $sexe,
            'categorie' => $categorie2020,
            'categorie_2019' => $categorie2019,
            'categorie_affichee' => $categorieAffichee !== '' ? $categorieAffichee : 'Non renseignée',
            'club' => $club,
            'licence' => $licence,
            'annee' => trim((string) ($ligne['annee'] ?? '')),
            'abandon' => (int) ($ligne['abandon'] ?? 0) === 1,
            'rgpd' => (int) ($ligne['rgpd'] ?? 0) === 1,
            'pps' => trim((string) ($ligne['pps'] ?? '')),
            'att_mineur' => trim((string) ($ligne['att_mineur'] ?? '')),
            'anotations' => trim((string) ($ligne['anotations'] ?? '')),
            'email' => trim((string) ($ligne['email'] ?? '')),
            'phone' => trim((string) ($ligne['phone'] ?? '')),
            'litige' => $litige,
        ];
    }

    /**
     * Reproduit exactement les règles métier legacy de détection des litiges PPS / mineur / annotations.
     *
     * @param array<string, mixed> $ligne
     * @return array{est_en_litige: bool, motif: ?string, annotation: ?string, type: ?string}
     */
    private function evaluerLitige(array $ligne): array
    {
        $pps = trim((string) ($ligne['pps'] ?? ''));
        $attMineur = trim((string) ($ligne['att_mineur'] ?? ''));
        $annotation = trim((string) ($ligne['anotations'] ?? ''));

        $motif = null;
        $type = null;

        if (in_array($pps, self::STATUTS_PPS_MANQUANTS, true)) {
            $motif = "PPS ou licence FFA : {$pps}";
            $type = 'pps';
        } elseif (in_array($pps, self::STATUTS_PPS_NON_CONFORMES, true)) {
            $motif = "PPS ou licence FFA : Document fourni non conforme ({$pps})";
            $type = 'pps';
        } elseif (in_array($attMineur, self::STATUTS_MINEUR_LITIGE, true)) {
            $motif = "Attestation mineur : {$attMineur}";
            $type = 'mineur';
        } elseif ($annotation !== '') {
            $motif = 'Annotation organisateur';
            $type = 'annotation';
        }

        return [
            'est_en_litige' => $motif !== null,
            'motif' => $motif,
            'annotation' => $annotation !== '' ? $annotation : null,
            'type' => $type,
        ];
    }

    /**
     * @param array<string, mixed> $evenement
     * @param list<array<string, mixed>> $participants
     * @return list<string>
     */
    private function extraireCoursesOrdonnees(array $evenement, array $participants): array
    {
        $courses = [];
        for ($i = 1; $i <= 6; $i++) {
            $nom = trim((string) ($evenement["nom_course_{$i}"] ?? ''));
            if ($nom !== '' && !in_array($nom, $courses, true)) {
                $courses[] = $nom;
            }
        }

        foreach ($participants as $p) {
            if ($p['distance'] !== '' && !in_array($p['distance'], $courses, true)) {
                $courses[] = $p['distance'];
            }
        }

        return $courses;
    }

    /**
     * Charge en 2 requêtes max les arrivants par course et les abandons depuis table_dag.
     *
     * @return array{arrivants: array<string, int>, abandons: list<array<string, mixed>>}
     */
    private function chargerStatsDag(string $tableDag): array
    {
        $arrivants = [];
        $abandons = [];
        $colonnesDag = $this->getColonnesTable($tableDag);
        $filtrePassage = in_array('nb_passage', $colonnesDag, true)
            ? " AND (nb_passage = 'Passage_1' OR nb_passage = '' OR nb_passage IS NULL)"
            : '';

        try {
            $rows = $this->db->fetchAllAssociative(
                "SELECT nom_course, COUNT(*) AS nb
                 FROM `{$tableDag}`
                 WHERE nom_course != 'ABANDON_COURSE' {$filtrePassage}
                 GROUP BY nom_course"
            );
            foreach ($rows as $r) {
                $course = trim((string) ($r['nom_course'] ?? ''));
                if ($course !== '') {
                    $arrivants[$course] = (int) $r['nb'];
                }
            }
        } catch (\Throwable) {
        }

        try {
            $abandonsRows = $this->db->fetchAllAssociative(
                "SELECT dossard, nom_prenom, club FROM `{$tableDag}` WHERE nom_course = 'ABANDON_COURSE' ORDER BY CAST(dossard AS UNSIGNED) ASC"
            );
            foreach ($abandonsRows as $r) {
                $abandons[] = [
                    'dossard' => trim((string) ($r['dossard'] ?? '')),
                    'nom_complet' => trim((string) ($r['nom_prenom'] ?? '')),
                    'club' => trim((string) ($r['club'] ?? '')),
                    'source' => 'DAG',
                ];
            }
        } catch (\Throwable) {
        }

        return ['arrivants' => $arrivants, 'abandons' => $abandons];
    }

    /**
     * @return array<string, int>
     */
    private function chargerStatsDeparts(string $tableRun): array
    {
        if (!$this->tableExists('import_dag_depart')) {
            return [];
        }

        try {
            $rows = $this->db->fetchAllAssociative(
                "SELECT r.distance, COUNT(DISTINCT d.dossard) AS nb
                 FROM import_dag_depart d
                 INNER JOIN `{$tableRun}` r ON r.dossard = d.dossard
                 GROUP BY r.distance"
            );
            $result = [];
            foreach ($rows as $r) {
                $result[trim((string) ($r['distance'] ?? ''))] = (int) $r['nb'];
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<string> $coursesOrdonnees
     * @param list<array<string, mixed>> $participants
     * @param array<string, int> $arrivantsParCourse
     * @param array<string, int> $departsParCourse
     * @param array<string, mixed> $evenement
     * @return list<array<string, mixed>>
     */
    private function construireStatsParCourse(
        array $coursesOrdonnees,
        array $participants,
        array $arrivantsParCourse,
        array $departsParCourse,
        array $evenement,
    ): array {
        $heuresDepart = [];
        for ($i = 1; $i <= 6; $i++) {
            $nom = trim((string) ($evenement["nom_course_{$i}"] ?? ''));
            $heure = trim((string) ($evenement["heure_depart_course_{$i}"] ?? ''));
            if ($nom !== '' && $heure !== '' && $heure !== '00:00:00') {
                $heuresDepart[$nom] = substr($heure, 0, 5);
            }
        }

        $stats = [];
        foreach ($coursesOrdonnees as $course) {
            $parCourse = array_values(array_filter(
                $participants,
                static fn (array $p): bool => $p['distance'] === $course
            ));

            $f = 0;
            $h = 0;
            $x = 0;
            $litiges = 0;
            $minDossard = null;
            $maxDossard = null;
            $maxId = -1;
            $dossardDernierId = null;

            foreach ($parCourse as $p) {
                if ($p['sexe'] === 'F') {
                    $f++;
                } elseif ($p['sexe'] === 'H') {
                    $h++;
                } else {
                    $x++;
                }

                if ($p['litige']['est_en_litige']) {
                    $litiges++;
                }

                if ($p['dossard_num'] > 0) {
                    $minDossard = $minDossard === null ? $p['dossard_num'] : min($minDossard, $p['dossard_num']);
                    $maxDossard = $maxDossard === null ? $p['dossard_num'] : max($maxDossard, $p['dossard_num']);

                    if ($p['id_dossard'] !== null && $p['id_dossard'] > $maxId) {
                        $maxId = $p['id_dossard'];
                        $dossardDernierId = $p['dossard_num'];
                    }
                }
            }

            $inscrits = count($parCourse);
            $arrivants = $arrivantsParCourse[$course] ?? 0;

            $stats[] = [
                'course' => $course,
                'heure_depart' => $heuresDepart[$course] ?? null,
                'inscrits' => $inscrits,
                'femmes' => $f,
                'hommes' => $h,
                'mixtes' => $x,
                'partants' => $departsParCourse[$course] ?? 0,
                'arrivants' => $arrivants,
                'non_arrives' => max(0, $inscrits - $arrivants),
                'litiges' => $litiges,
                'min_dossard' => $minDossard,
                'max_dossard' => $maxDossard,
                'prochain_dossard' => $maxDossard !== null ? $maxDossard + 1 : null,
                'prochain_dossard_par_id' => $dossardDernierId !== null ? $dossardDernierId + 1 : null,
            ];
        }

        return $stats;
    }

    /**
     * @param list<array<string, mixed>> $abandonsDag
     * @param list<array<string, mixed>> $participants
     * @return list<array<string, mixed>>
     */
    private function fusionnerAbandons(array $abandonsDag, array $participants): array
    {
        $parCle = [];
        foreach ($abandonsDag as $a) {
            $cle = $a['dossard'] !== '' ? $a['dossard'] : $a['nom_complet'];
            $parCle[$cle] = $a;
        }

        foreach ($participants as $p) {
            if ($p['abandon']) {
                $cle = $p['dossard'] !== '' ? $p['dossard'] : $p['nom_complet'];
                if (!isset($parCle[$cle])) {
                    $parCle[$cle] = [
                        'dossard' => $p['dossard'],
                        'nom_complet' => $p['nom_complet'],
                        'club' => $p['club'],
                        'source' => 'Inscriptions',
                    ];
                }
            }
        }

        return array_values($parCle);
    }

    /**
     * Construit le tableau croisé (course × catégorie FFA) dans l'ordre canonique FFA.
     *
     * @param list<string> $coursesOrdonnees
     * @param list<array<string, mixed>> $participants
     * @return array{colonnes: list<string>, lignes: list<array<string, mixed>>, totaux: array<string, int>}
     */
    private function construireMatriceCategories(array $coursesOrdonnees, array $participants, bool $modeRegroupe): array
    {
        $ordreCanonique = $modeRegroupe ? self::ORDRE_CATEGORIES_FFA_2019 : self::ORDRE_CATEGORIES_FFA_2020;

        $comptages = [];
        $categoriesPresentes = [];

        foreach ($participants as $p) {
            $course = $p['distance'];
            $cat = $p['categorie_affichee'];
            $comptages[$course][$cat] = ($comptages[$course][$cat] ?? 0) + 1;
            $categoriesPresentes[$cat] = true;
        }

        // Colonnes triées selon l'ordre FFA officiel, puis éventuelles catégories non standard
        $colonnes = [];
        foreach ($ordreCanonique as $cat) {
            if (isset($categoriesPresentes[$cat])) {
                $colonnes[] = $cat;
                unset($categoriesPresentes[$cat]);
            }
        }
        $extras = array_keys($categoriesPresentes);
        sort($extras);
        foreach ($extras as $cat) {
            $colonnes[] = $cat;
        }

        $lignes = [];
        $totauxColonnes = array_fill_keys($colonnes, 0);
        foreach ($coursesOrdonnees as $course) {
            $valeurs = [];
            $totalLigne = 0;
            foreach ($colonnes as $cat) {
                $nb = $comptages[$course][$cat] ?? 0;
                $valeurs[$cat] = $nb;
                $totauxColonnes[$cat] += $nb;
                $totalLigne += $nb;
            }
            $lignes[] = [
                'course' => $course,
                'valeurs' => $valeurs,
                'total' => $totalLigne,
            ];
        }

        return [
            'colonnes' => $colonnes,
            'lignes' => $lignes,
            'totaux' => $totauxColonnes,
        ];
    }

    /**
     * @param list<array<string, mixed>> $participants
     * @return list<array{nom: string, nombre: int, occurrences: list<array<string, mixed>>}>
     */
    private function detecterHomonymes(array $participants): array
    {
        $groupes = [];
        foreach ($participants as $p) {
            $cle = mb_strtoupper(preg_replace('/\s+/', ' ', $p['nom_complet']));
            if ($cle === '') {
                continue;
            }
            $groupes[$cle][] = $p;
        }

        $homonymes = [];
        foreach ($groupes as $items) {
            if (count($items) > 1) {
                $homonymes[] = [
                    'nom' => $items[0]['nom_complet'],
                    'nombre' => count($items),
                    'occurrences' => $items,
                ];
            }
        }

        usort($homonymes, static fn (array $a, array $b): int => strcmp($a['nom'], $b['nom']));

        return $homonymes;
    }

    /**
     * Détecte d'éventuels numéros de dossard attribués à plusieurs participants.
     *
     * @param list<array<string, mixed>> $participants
     * @return list<array{dossard: string, nombre: int, occurrences: list<array<string, mixed>>}>
     */
    private function detecterDoublonsDossards(array $participants): array
    {
        $groupes = [];
        foreach ($participants as $p) {
            if ($p['dossard'] === '') {
                continue;
            }
            $groupes[$p['dossard']][] = $p;
        }

        $doublons = [];
        foreach ($groupes as $dossard => $items) {
            if (count($items) > 1) {
                $doublons[] = [
                    'dossard' => (string) $dossard,
                    'nombre' => count($items),
                    'occurrences' => $items,
                ];
            }
        }

        return $doublons;
    }

    /**
     * @param list<array<string, mixed>> $participants
     * @param list<string> $coursesOrdonnees
     * @return list<array<string, mixed>>
     */
    private function extraireLitiges(array $participants, array $coursesOrdonnees): array
    {
        $litiges = array_values(array_filter(
            $participants,
            static fn (array $p): bool => $p['litige']['est_en_litige']
        ));

        $ordreCourses = array_flip($coursesOrdonnees);
        usort($litiges, function (array $a, array $b) use ($ordreCourses): int {
            $posA = $ordreCourses[$a['distance']] ?? 999;
            $posB = $ordreCourses[$b['distance']] ?? 999;
            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return $this->comparerChainesFr($a['nom'], $b['nom']);
        });

        return $litiges;
    }

    /**
     * Groupe les participants par course et calcule les rangs de tri par Nom et par Dossard.
     *
     * @param list<string> $coursesOrdonnees
     * @param list<array<string, mixed>> $participants
     * @return list<array<string, mixed>>
     */
    private function grouperEtTrierParticipants(
        array $coursesOrdonnees,
        array $participants,
        bool $triParSexeNom,
    ): array {
        $resultat = [];

        foreach ($coursesOrdonnees as $index => $course) {
            $liste = array_values(array_filter(
                $participants,
                static fn (array $p): bool => $p['distance'] === $course
            ));

            foreach ($liste as $idx => &$item) {
                $item['_idx'] = $idx;
            }
            unset($item);

            // Tri de référence par Dossard (numérique croissant)
            $parDossardIdx = $liste;
            usort($parDossardIdx, function (array $a, array $b): int {
                if ($a['dossard_num'] !== $b['dossard_num']) {
                    return $a['dossard_num'] <=> $b['dossard_num'];
                }

                return $this->comparerChainesFr($a['nom_complet'], $b['nom_complet']);
            });
            $ordreDossard = [];
            foreach ($parDossardIdx as $rang => $p) {
                $ordreDossard[$p['_idx']] = $rang;
            }

            // Tri principal par Nom (ou Sexe + Nom si ffa_2020_q)
            usort($liste, function (array $a, array $b) use ($triParSexeNom): int {
                if ($triParSexeNom && $a['sexe'] !== $b['sexe']) {
                    return strcmp($a['sexe'], $b['sexe']);
                }

                $cmpNom = $this->comparerChainesFr($a['nom'], $b['nom']);
                if ($cmpNom !== 0) {
                    return $cmpNom;
                }

                return $this->comparerChainesFr($a['prenom'], $b['prenom']);
            });

            foreach ($liste as $rangNom => &$p) {
                $p['ordre_nom'] = $rangNom;
                $p['ordre_dossard'] = $ordreDossard[$p['_idx']] ?? $rangNom;
                unset($p['_idx']);
            }
            unset($p);

            $resultat[] = [
                'slug' => 'course-' . ($index + 1),
                'course' => $course,
                'total' => count($liste),
                'participants' => $liste,
            ];
        }

        return $resultat;
    }

    private function comparerChainesFr(string $a, string $b): int
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('fr_FR');
            $collator->setStrength(\Collator::PRIMARY);
            $res = $collator->compare($a, $b);
            if ($res !== false) {
                return $res;
            }
        }

        return strcmp(mb_strtolower($a), mb_strtolower($b));
    }

    /**
     * @return array{total: int, F: int, H: int, X: int, partants: int, arrivants: int}
     */
    private function creerCompteurSexeVide(): array
    {
        return ['total' => 0, 'F' => 0, 'H' => 0, 'X' => 0, 'partants' => 0, 'arrivants' => 0];
    }
}
