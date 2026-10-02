<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    // --- Phase 3 — Résultats ---

    #[Route('/resultats/classements', name: 'rc_resultats_classements', methods: ['GET'])]
    public function resultatsClassements(): Response
    {
        return $this->placeholder('Classements web', '3.1', 'Résultats');
    }

    #[Route('/resultats/podiums', name: 'rc_resultats_podiums', methods: ['GET'])]
    public function resultatsPodiums(): Response
    {
        return $this->placeholder('Podiums', '3.2', 'Résultats');
    }

    #[Route('/resultats/top', name: 'rc_resultats_top', methods: ['GET'])]
    public function resultatsTop(): Response
    {
        return $this->placeholder('Classement TOP N', '3.3', 'Résultats');
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
