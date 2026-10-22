<?php

namespace App\Controller;

use App\Service\ResultatsService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Console admin live sous /rc/ (équivalent dag/menu/index.html + admin_dag.php).
 */
#[Route('/rc')]
#[IsGranted('ROLE_ADMIN')]
class RcController extends AbstractController
{
    #[Route('', name: 'rc_home', methods: ['GET'])]
    public function home(): Response
    {
        // Contenu par défaut = recherche coureur (legacy info_coureur.php).
        return $this->redirectToRoute('rc_participants_recherche');
    }

    // --- Phase 1 — Import DAG ---

    #[Route('/import/dag', name: 'rc_import_dag', methods: ['GET'])]
    public function importDag(): Response
    {
        return $this->placeholder('Import DAG', '1.1', 'Import fichier détection');
    }

    #[Route('/import/abandon', name: 'rc_import_abandon', methods: ['GET'])]
    public function importAbandon(): Response
    {
        return $this->placeholder('Abandon coureur', '1.2', 'Import fichier détection');
    }

    // --- Phase 2 — Participants ---

    #[Route('/participants/recherche', name: 'rc_participants_recherche', methods: ['GET'])]
    public function participantsRecherche(): Response
    {
        return $this->render('rc/recherche.html.twig');
    }

    #[Route('/participants/liste', name: 'rc_participants_liste', methods: ['GET'])]
    public function participantsListe(): Response
    {
        return $this->placeholder('Liste complète des participants', '2.2', 'Participants');
    }

    #[Route('/participants/heures-depart', name: 'rc_participants_heures_depart', methods: ['GET'])]
    public function participantsHeuresDepart(): Response
    {
        return $this->placeholder('Heures de départ', '2.3', 'Participants');
    }

    #[Route('/participants/delta-temps', name: 'rc_participants_delta_temps', methods: ['GET'])]
    public function participantsDeltaTemps(): Response
    {
        return $this->placeholder('Calcul delta temps', '2.4', 'Participants');
    }

    #[Route('/participants/rgpd', name: 'rc_participants_rgpd', methods: ['GET'])]
    public function participantsRgpd(): Response
    {
        return $this->placeholder('RGPD — Anonymisation', '2.5', 'Participants');
    }

    // --- Phase 3 : Resultats ---

    #[Route('/resultats/classements', name: 'rc_resultats_classements', methods: ['GET'])]
    public function resultatsClassements(Request $request, ResultatsService $resultatsService): Response
    {
        $dateDebut = $request->query->get('debut', '2025-01-01');
        $dateFin = $request->query->get('fin', '2026-12-31');

        $evenementsFormates = $resultatsService->getEvenements($dateDebut, $dateFin);

        return $this->render('rc/resultats_classements.html.twig', [
            'evenements' => $evenementsFormates,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);
    }

    #[Route('/resultats/classement/{idHtml}/{nomCourse}', name: 'rc_resultats_classement_course', methods: ['GET'])]
    public function classementCourse(
        string $idHtml,
        string $nomCourse,
        Request $request,
        ResultatsService $resultatsService
    ): Response {
        $club = $request->query->get('club');
        $data = $resultatsService->getClassementCourse($idHtml, $nomCourse, $club);

        if (!$data) {
            $this->addFlash('error', "Impossible de charger le classement pour cette course ou la table de résultats n'existe pas.");
            return $this->redirectToRoute('rc_resultats_classements');
        }

        return $this->render('rc/classement_course.html.twig', $data);
    }

    #[Route('/resultats/podium/{idHtml}/{nomCourse}', name: 'rc_resultats_podium_course', methods: ['GET'])]
    public function podiumCourse(
        string $idHtml,
        string $nomCourse,
        ResultatsService $resultatsService
    ): Response {
        $data = $resultatsService->getPodiumCourse($idHtml, $nomCourse);

        if (!$data) {
            $this->addFlash('error', "Impossible de charger le podium pour cette course ou la table de résultats n'existe pas.");
            return $this->redirectToRoute('rc_resultats_classements');
        }

        return $this->render('rc/podium_course.html.twig', $data);
    }

    #[Route('/resultats/top/{idHtml}', name: 'rc_resultats_top_evenement', methods: ['GET'])]
    public function topEvenement(
        string $idHtml,
        Request $request,
        ResultatsService $resultatsService
    ): Response {
        $top = (int)$request->query->get('n', 3);
        if ($top < 1 || $top > 20) {
            $top = 3;
        }

        $data = $resultatsService->getTopEvenement($idHtml, $top);

        if (!$data) {
            $this->addFlash('error', "Impossible de charger le Top pour cet événement.");
            return $this->redirectToRoute('rc_resultats_classements');
        }

        return $this->render('rc/top_evenement.html.twig', $data);
    }

    #[Route('/resultats/special/{slug}', name: 'rc_resultats_special', methods: ['GET'])]
    public function resultatsSpecial(string $slug, ResultatsService $resultatsService): Response
    {
        $data = $resultatsService->getSpecialResultat($slug);
        if (!$data) {
            $this->addFlash('error', "Impossible de charger les résultats spécifiques demandés.");
            return $this->redirectToRoute('rc_resultats_classements');
        }

        return $this->render('rc/resultats_special.html.twig', $data);
    }

    #[Route('/resultats/courses/{path}', name: 'rc_resultats_courses_legacy', requirements: ['path' => '.*'], methods: ['GET'])]
    #[Route('/courses/{path}', name: 'rc_courses_legacy', requirements: ['path' => '.*'], methods: ['GET'])]
    public function coursesLegacy(string $path, ResultatsService $resultatsService): Response
    {
        $data = $resultatsService->getSpecialResultat($path);
        if (!$data) {
            $this->addFlash('error', "Résultat introuvable.");
            return $this->redirectToRoute('rc_resultats_classements');
        }

        return $this->render('rc/resultats_special.html.twig', $data);
    }

    #[Route('/resultats/podiums', name: 'rc_resultats_podiums', methods: ['GET'])]
    public function resultatsPodiums(Request $request, ResultatsService $resultatsService): Response
    {
        return $this->resultatsClassements($request, $resultatsService);
    }

    #[Route('/resultats/top', name: 'rc_resultats_top', methods: ['GET'])]
    public function resultatsTop(Request $request, ResultatsService $resultatsService): Response
    {
        return $this->resultatsClassements($request, $resultatsService);
    }

    // --- Phase 4 — Ajout / modif ---

    #[Route('/inscriptions/solo', name: 'rc_inscriptions_solo', methods: ['GET'])]
    public function inscriptionsSolo(): Response
    {
        return $this->placeholder('Ajout / modification coureur', '4.1', 'Inscriptions');
    }

    #[Route('/inscriptions/duo', name: 'rc_inscriptions_duo', methods: ['GET'])]
    public function inscriptionsDuo(): Response
    {
        return $this->placeholder('Ajout / modification DUO', '4.2', 'Inscriptions');
    }

    #[Route('/inscriptions/trio', name: 'rc_inscriptions_trio', methods: ['GET'])]
    public function inscriptionsTrio(): Response
    {
        return $this->placeholder('Ajout / modification TRIO', '4.3', 'Inscriptions');
    }

    // --- Phase 5 — Infos diverses ---

    #[Route('/infos/clubs', name: 'rc_infos_clubs', methods: ['GET'])]
    public function infosClubs(): Response
    {
        return $this->placeholder('Clubs représentés', '5.1', 'Informations diverses');
    }

    #[Route('/infos/non-arrives', name: 'rc_infos_non_arrives', methods: ['GET'])]
    public function infosNonArrives(): Response
    {
        return $this->placeholder('Dossards non arrivés', '5.2', 'Informations diverses');
    }

    #[Route('/infos/tirage', name: 'rc_infos_tirage', methods: ['GET'])]
    public function infosTirage(): Response
    {
        return $this->placeholder('Tirage au sort', '5.3', 'Informations diverses');
    }

    // --- Phase 6 — Actions diverses ---

    #[Route('/actions/publier', name: 'rc_actions_publier', methods: ['GET'])]
    public function actionsPublier(): Response
    {
        return $this->placeholder('Envoi des résultats', '6.1', 'Actions diverses');
    }

    #[Route('/actions/ipconfig', name: 'rc_actions_ipconfig', methods: ['GET'])]
    public function actionsIpconfig(): Response
    {
        return $this->placeholder('Ipconfig', '6.2', 'Actions diverses');
    }

    #[Route('/actions/update-dossards', name: 'rc_actions_update_dossards', methods: ['GET'])]
    public function actionsUpdateDossards(): Response
    {
        return $this->placeholder('Update dossards Excel', '6.3', 'Actions diverses');
    }

    #[Route('/actions/backup-dag', name: 'rc_actions_backup_dag', methods: ['GET'])]
    public function actionsBackupDag(): Response
    {
        return $this->placeholder('Contrôle backup DAG', '6.4', 'Actions diverses');
    }

    #[Route('/actions/mails', name: 'rc_actions_mails', methods: ['GET'])]
    public function actionsMails(): Response
    {
        return $this->placeholder('Liste diffusion résultats', '6.5', 'Actions diverses');
    }

    // --- Phase 7 — Tablettes ---

    #[Route('/tablettes/reset', name: 'rc_tablettes_reset', methods: ['GET'])]
    public function tablettesReset(): Response
    {
        return $this->placeholder('Reset tablettes et abandons', '7.1', 'Tablettes');
    }

    #[Route('/tablettes/annonce', name: 'rc_tablettes_annonce', methods: ['GET'])]
    public function tablettesAnnonce(): Response
    {
        return $this->placeholder('Tablette annonce coureur', '7.2', 'Tablettes');
    }

    #[Route('/tablettes/formulaire', name: 'rc_tablettes_formulaire', methods: ['GET'])]
    public function tablettesFormulaire(): Response
    {
        return $this->placeholder('Tablette formulaire annonce', '7.3', 'Tablettes');
    }

    /**
     * Page stub Phase 0 — contenu métier dans les phases suivantes.
     */
    private function placeholder(string $titre, string $sousPhase, string $section): Response
    {
        return $this->render('rc/placeholder.html.twig', [
            'titre_page' => $titre,
            'sous_phase' => $sousPhase,
            'section' => $section,
        ]);
    }
}
