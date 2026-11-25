<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gère l'événement actif global de la console live (/rc).
 * Priorité de résolution : surcharge explicite (tests) > session admin > variable d'environnement (.env).
 */
class EvenementActifService
{
    private const SESSION_KEY = 'rc_active_event_id';

    private bool $charge = false;
    private ?array $evenementCache = null;
    private ?string $overrideEventId = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $selectionCache = null;

    public function __construct(
        private readonly Connection $db,
        private readonly RequestStack $requestStack,
        #[Autowire('%env(default::RUNCHRONO_ACTIVE_EVENT_ID)%')]
        private ?string $defaultActiveEventId = null,
    ) {
    }

    /**
     * Retourne les paramètres complets de l'événement actif, ou null si aucun événement n'est configuré/trouvé.
     *
     * @return array<string, mixed>|null
     */
    public function getEvenementActif(): ?array
    {
        if ($this->charge) {
            return $this->evenementCache;
        }

        $this->charge = true;
        $idHtml = $this->getActiveEventId();

        if ($idHtml === '') {
            $this->evenementCache = null;

            return null;
        }

        $event = $this->trouverEvenementParId($idHtml);
        if ($event === null) {
            $this->evenementCache = null;

            return null;
        }

        // Extraction normalisée des 6 créneaux de courses éventuels
        $courses = [];
        for ($i = 1; $i <= 6; $i++) {
            $nomCourse = trim((string) ($event["nom_course_{$i}"] ?? ''));
            if ($nomCourse !== '') {
                $courses[] = [
                    'numero' => $i,
                    'nom' => $nomCourse,
                    'heure_depart' => $event["heure_depart_course_{$i}"] ?? null,
                ];
            }
        }

        $event['courses_actives'] = $courses;
        $this->evenementCache = $event;

        return $this->evenementCache;
    }

    /**
     * Recherche un événement par son id_html dans param_inscription_manuelle.
     *
     * @return array<string, mixed>|null
     */
    public function trouverEvenementParId(string $idHtml): ?array
    {
        $idHtml = trim($idHtml);
        if ($idHtml === '') {
            return null;
        }

        try {
            $event = $this->db->fetchAssociative(
                'SELECT * FROM param_inscription_manuelle WHERE id_html = :idHtml LIMIT 1',
                ['idHtml' => $idHtml]
            );

            return $event ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retourne la liste des événements sélectionnables sur la page dédiée « Événement actif ».
     *
     * @return list<array<string, mixed>>
     */
    public function getEvenementsSelectionnables(int $limit = 300): array
    {
        if ($this->selectionCache !== null) {
            return $this->selectionCache;
        }

        $limit = max(1, min(500, $limit));

        try {
            $rows = $this->db->fetchAllAssociative(
                "SELECT id_html, evenement, date_evenement, lib_date_evenement, table_run, table_dag, categorie_ffa
                 FROM param_inscription_manuelle
                 WHERE date_evenement IS NOT NULL
                   AND id_html != ''
                 ORDER BY date_evenement DESC, id DESC
                 LIMIT {$limit}"
            );

            $tablesExistantes = array_flip($this->db->fetchFirstColumn("SHOW TABLES LIKE 'run_%'"));
        } catch (\Throwable) {
            return $this->selectionCache = [];
        }

        foreach ($rows as &$row) {
            $tableRun = trim((string) ($row['table_run'] ?? ''));
            $row['table_run_existe'] = $tableRun !== '' && isset($tablesExistantes[$tableRun]);
        }
        unset($row);

        return $this->selectionCache = $rows;
    }

    /**
     * Enregistre (ou efface si vide) l'événement actif en session pour la console /rc.
     */
    public function definirEvenementActifEnSession(string $idHtml): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, trim($idHtml));
        }

        $this->overrideEventId = null;
        $this->reinitialiserCache();
    }

    /**
     * Permet de surcharger l'identifiant d'événement actif (utile notamment dans les tests).
     */
    public function setActiveEventId(string $idHtml): void
    {
        $this->overrideEventId = $idHtml;
        $this->reinitialiserCache();
    }

    private function getActiveEventId(): string
    {
        if ($this->overrideEventId !== null) {
            return trim($this->overrideEventId);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->hasSession()) {
            $session = $request->getSession();
            if ($session->has(self::SESSION_KEY)) {
                return trim((string) $session->get(self::SESSION_KEY));
            }
        }

        return trim((string) $this->defaultActiveEventId);
    }

    private function reinitialiserCache(): void
    {
        $this->charge = false;
        $this->evenementCache = null;
    }
}
