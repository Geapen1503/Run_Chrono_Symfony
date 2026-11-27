<?php

namespace App\Service;

use App\Model\CoureurSoloData;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Service métier pour l'inscription et la modification à la volée d'un coureur solo (tables run_*).
 * Équivalent modernisé et sécurisé de dag/15_GET_run_form_ajout.php + 15R_run_form_ajout_COUREUR_SOLO.php.
 */
final class InscriptionSoloService
{
    public function __construct(
        private readonly Connection $db,
        private readonly ParticipantsService $participantsService,
        private readonly DossardResolverService $dossardResolver,
        private readonly CategorieFfaCalculator $categorieCalculator,
        private readonly ClubNormalizer $clubNormalizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Charge le contexte complet d'un événement actif pour l'écran Inscription > Coureur (solo).
     *
     * @param array<string, mixed> $evenement
     * @return array<string, mixed>
     */
    public function getContexte(array $evenement): array
    {
        $tableRun = trim((string) ($evenement['table_run'] ?? ''));
        $tableExiste = $this->participantsService->tableExists($tableRun);
        $coursesConfig = $this->dossardResolver->getCoursesConfig($evenement);
        $coursesNoms = array_values(array_map(static fn ($c): string => $c->nomCourse, $coursesConfig));

        if (!$tableExiste) {
            return [
                'table_run_existe' => false,
                'regles_dossards_actives' => $this->dossardResolver->possedeReglesDossards($evenement),
                'courses_noms' => $coursesNoms,
                'stats_dossards' => $this->dossardResolver->calculerStatsDossards($evenement, []),
                'total_participants' => 0,
                'clubs_existants' => [],
                'participants_rapides' => [],
            ];
        }

        $lignes = $this->db->fetchAllAssociative("SELECT * FROM `{$tableRun}`");
        $participants = [];
        $clubsMap = [];
        $dossardsDistincts = [];

        foreach ($lignes as $ligne) {
            $dossardStr = trim((string) ($ligne['dossard'] ?? ''));
            $dossardNum = ctype_digit($dossardStr) ? (int) $dossardStr : (int) preg_replace('/\D+/', '', $dossardStr);
            $club = trim((string) ($ligne['club'] ?? ''));

            if ($dossardStr !== '') {
                $dossardsDistincts[$dossardStr] = true;
            }
            if ($club !== '') {
                $clubsMap[$club] = true;
            }

            $participants[] = [
                'id_dossard' => isset($ligne['id_dossard']) ? (int) $ligne['id_dossard'] : 0,
                'dossard' => $dossardStr,
                'dossard_num' => $dossardNum,
                'nom' => trim((string) ($ligne['nom'] ?? '')),
                'prenom' => trim((string) ($ligne['prenom'] ?? '')),
                'nom_prenom' => trim((string) ($ligne['nom_prenom'] ?? '')),
                'sexe' => strtoupper(trim((string) ($ligne['sexe'] ?? ''))),
                'annee' => trim((string) ($ligne['annee'] ?? '')),
                'categorie' => trim((string) ($ligne['categorie'] ?? '')),
                'club' => $club,
                'licence' => trim((string) ($ligne['licence'] ?? '')),
                'distance' => trim((string) ($ligne['distance'] ?? '')),
            ];
        }

        // Tri des plus récemment saisis en premier pour l'accès rapide jour J
        usort($participants, static fn (array $a, array $b): int => $b['id_dossard'] <=> $a['id_dossard']);

        $clubsExistants = array_keys($clubsMap);
        sort($clubsExistants, \SORT_NATURAL | \SORT_FLAG_CASE);

        return [
            'table_run_existe' => true,
            'regles_dossards_actives' => $this->dossardResolver->possedeReglesDossards($evenement),
            'courses_noms' => $coursesNoms,
            'stats_dossards' => $this->dossardResolver->calculerStatsDossards($evenement, $participants),
            'total_participants' => count($dossardsDistincts),
            'clubs_existants' => $clubsExistants,
            'participants_rapides' => $participants,
        ];
    }

    /**
     * Recherche un coureur par son numéro de dossard dans la table run_* active.
     *
     * @param array<string, mixed> $evenement
     * @return array<string, mixed>|null
     */
    public function rechercherParDossard(array $evenement, int $dossard): ?array
    {
        $tableRun = trim((string) ($evenement['table_run'] ?? ''));
        if ($dossard <= 0 || !$this->participantsService->tableExists($tableRun)) {
            return null;
        }

        $row = $this->db->fetchAssociative(
            "SELECT * FROM `{$tableRun}` WHERE dossard = :dossard LIMIT 1",
            ['dossard' => (string) $dossard]
        );

        if (!$row) {
            return null;
        }

        $nom = trim((string) ($row['nom'] ?? ''));
        $prenom = trim((string) ($row['prenom'] ?? ''));
        $nomPrenom = trim((string) ($row['nom_prenom'] ?? ''));
        if ($nom === '' && $nomPrenom !== '') {
            $parties = preg_split('/\s+/', $nomPrenom, 2) ?: [];
            $nom = $parties[0] ?? '';
            $prenom = $prenom !== '' ? $prenom : ($parties[1] ?? '');
        }

        return [
            'id_dossard' => isset($row['id_dossard']) ? (int) $row['id_dossard'] : null,
            'dossard' => (int) ($row['dossard'] ?? $dossard),
            'nom' => $nom,
            'prenom' => $prenom,
            'nom_prenom' => $nomPrenom !== '' ? $nomPrenom : trim("{$nom} {$prenom}"),
            'sexe' => strtoupper(trim((string) ($row['sexe'] ?? ''))),
            'annee' => is_numeric($row['annee'] ?? null) ? (int) $row['annee'] : null,
            'categorie' => trim((string) ($row['categorie'] ?? '')),
            'club' => trim((string) ($row['club'] ?? '')),
            'licence' => trim((string) ($row['licence'] ?? '')),
            'distance' => trim((string) ($row['distance'] ?? '')),
        ];
    }

    /**
     * Pré-remplit un DTO CoureurSoloData depuis une fiche existante trouvée en base.
     *
     * @param array<string, mixed> $fiche
     */
    public function hydraterDtoDepuisFiche(array $fiche): CoureurSoloData
    {
        $dto = new CoureurSoloData();
        $dto->dossard = isset($fiche['dossard']) ? (int) $fiche['dossard'] : null;
        $dto->nom = (string) ($fiche['nom'] ?? '');
        $dto->prenom = (string) ($fiche['prenom'] ?? '');
        $dto->sexe = in_array($fiche['sexe'] ?? '', ['H', 'F'], true) ? (string) $fiche['sexe'] : '';
        $dto->annee = isset($fiche['annee']) && $fiche['annee'] !== null ? (int) $fiche['annee'] : null;
        $dto->club = ($fiche['club'] ?? '') !== '' ? (string) $fiche['club'] : null;
        $dto->licence = ($fiche['licence'] ?? '') !== '' ? (string) $fiche['licence'] : null;

        return $dto;
    }

    /**
     * Insère ou met à jour un coureur solo dans la table run_* de l'événement actif.
     *
     * @param array<string, mixed> $evenement
     * @return array<string, mixed>
     */
    public function enregistrerCoureur(array $evenement, CoureurSoloData $data, string $operateur = 'admin'): array
    {
        $tableRun = trim((string) ($evenement['table_run'] ?? ''));
        if (!$this->participantsService->tableExists($tableRun)) {
            throw new \RuntimeException(sprintf("La table '%s' est introuvable en base.", $tableRun));
        }

        $colonnes = $this->participantsService->getColonnesTable($tableRun);
        if ($colonnes === []) {
            throw new \RuntimeException(sprintf("Impossible de lire les colonnes de '%s'.", $tableRun));
        }

        $dossard = (int) $data->dossard;
        $nom = $this->clubNormalizer->normaliserIdentite($data->nom);
        $prenom = $this->clubNormalizer->normaliserIdentite($data->prenom);
        $nomPrenom = trim("{$nom} {$prenom}");
        $sexe = strtoupper(trim($data->sexe));
        $annee = (int) $data->annee;
        $club = $this->clubNormalizer->normaliserClub($data->club);
        $licence = $this->clubNormalizer->normaliserLicence($data->licence);

        $categories = $this->categorieCalculator->calculerAvecSexe($annee, $sexe);
        $courseResolue = $this->dossardResolver->resoudreCoursePourDossard($evenement, $dossard);

        $horsPlage = false;
        if ($courseResolue !== null) {
            $distance = $courseResolue;
        } elseif ($data->distanceManuelle !== null && trim($data->distanceManuelle) !== '') {
            $distance = trim($data->distanceManuelle);
            $horsPlage = $this->dossardResolver->possedeReglesDossards($evenement);
        } else {
            $distance = DossardResolverService::COURSE_NON_CODE;
            $horsPlage = true;
        }

        $donnees = [
            'dossard' => (string) $dossard,
            'nom_prenom' => $nomPrenom,
            'annee' => (string) $annee,
            'categorie' => $categories['categorie'],
            'sexe' => $sexe,
            'club' => $club,
            'licence' => $licence,
            'distance' => $distance,
            'nom' => $nom,
            'prenom' => $prenom,
            'categorie_2019' => $categories['categorie_2019'],
        ];

        $action = $this->db->transactional(function () use ($tableRun, $dossard, $donnees, $colonnes): string {
            $existant = $this->db->fetchOne(
                "SELECT id_dossard FROM `{$tableRun}` WHERE dossard = :dossard LIMIT 1",
                ['dossard' => (string) $dossard]
            );

            $colonnesMap = array_flip($colonnes);
            $payload = array_intersect_key($donnees, $colonnesMap);

            if ($existant === false) {
                // Valeurs par défaut explicites pour les colonnes NOT NULL sans défaut (mode strict MySQL 8)
                foreach (['naissance' => '', 'abandon' => 0, 'rgpd' => 0] as $colDefaut => $valDefaut) {
                    if (isset($colonnesMap[$colDefaut]) && !array_key_exists($colDefaut, $payload)) {
                        $payload[$colDefaut] = $valDefaut;
                    }
                }
                $this->db->insert("`{$tableRun}`", $payload);

                return 'insert';
            }

            $this->db->update("`{$tableRun}`", $payload, ['dossard' => (string) $dossard]);

            return 'update';
        });

        $totalParticipants = (int) $this->db->fetchOne("SELECT COUNT(DISTINCT dossard) FROM `{$tableRun}`");

        $this->logger->info('Inscription coureur solo enregistrée', [
            'evenement' => $evenement['id_html'] ?? '',
            'table_run' => $tableRun,
            'action' => $action,
            'dossard' => $dossard,
            'nom_prenom' => $nomPrenom,
            'distance' => $distance,
            'categorie' => $categories['categorie'],
            'operateur' => $operateur,
        ]);

        return [
            'action' => $action,
            'dossard' => $dossard,
            'nom_prenom' => $nomPrenom,
            'distance' => $distance,
            'hors_plage' => $horsPlage,
            'categorie' => $categories['categorie'],
            'total_participants' => $totalParticipants,
        ];
    }
}
