<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResultatsController extends AbstractController
{
    /**
     * Affiche la page principale des résultats.
     * Cette méthode récupère les événements récents en base de données,
     * les passe à la méthode de préparation, puis renvoie la vue Twig.
     */
    #[Route('/resultats', name: 'app_resultats')]
    public function index(Connection $db): Response
    {
        $dateDebut = '2025-12-01';
        $dateFin = date('Y-m-d');

        $sql = "SELECT * FROM param_inscription_manuelle
                WHERE date_evenement BETWEEN :debut AND :fin
                ORDER BY date_evenement DESC";

        $evenements = $db->fetchAllAssociative($sql, [
            'debut' => $dateDebut,
            'fin' => $dateFin
        ]);

        $evenementsFormates = $this->preparerEvenements($evenements, $db);

        return $this->render('resultats/index.html.twig', [
            'evenements' => $evenementsFormates,
        ]);
    }

    /**
     * Nettoie et restructure les données des événements.
     * Transforme les 6 colonnes "nom_course_X" en un seul tableau propre,
     * et vérifie pour chaque course si des résultats sont disponibles.
     */
    private function preparerEvenements(array $evenements, Connection $db): array
    {
        $couleurs = ['btn-green', 'btn-givre', 'btn-red', 'btn-green', 'btn-givre', 'btn-red'];
        $resultat = [];

        foreach ($evenements as $event) {
            $courses = [];

            for ($i = 1; $i <= 6; $i++) {
                $nomCourse = $event["nom_course_$i"] ?? null;

                if (!empty($nomCourse)) {
                    $nbArrivants = $db->fetchOne(
                        "SELECT COUNT(*) FROM {$event['table_dag']} WHERE nom_course = :nom",
                        ['nom' => $nomCourse]
                    );

                    $courses[] = [
                        'nom' => $nomCourse,
                        'couleur' => $couleurs[$i - 1],
                        'a_des_resultats' => $nbArrivants > 0,
                    ];
                }
            }
            $event['courses_nettoyees'] = $courses;
            $resultat[] = $event;
        }

        return $resultat;
    }
}
