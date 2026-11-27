<?php

namespace App\Controller;

use App\Form\CoureurSoloType;
use App\Model\CoureurSoloData;
use App\Service\DossardResolverService;
use App\Service\EvenementActifService;
use App\Service\InscriptionSoloService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des inscriptions sur place et modifications à la volée (/rc/inscriptions/*).
 */
#[Route('/rc/inscriptions')]
#[IsGranted('ROLE_ADMIN')]
class RcInscriptionController extends AbstractController
{
    #[Route('/solo', name: 'rc_inscriptions_solo', methods: ['GET', 'POST'])]
    public function solo(
        Request $request,
        EvenementActifService $evenementActifService,
        InscriptionSoloService $inscriptionSoloService,
        DossardResolverService $dossardResolver,
    ): Response {
        $evenement = $evenementActifService->getEvenementActif();

        if ($evenement === null) {
            return $this->render('rc/inscriptions/solo.html.twig', [
                'evenement' => null,
                'contexte' => null,
                'form' => null,
                'dossard_recherche' => null,
                'fiche_existante' => null,
                'course_detectee' => null,
            ]);
        }

        $contexte = $inscriptionSoloService->getContexte($evenement);
        $dossardParam = trim((string) $request->query->get('dossard', ''));
        $dossardRecherche = ctype_digit($dossardParam) ? (int) $dossardParam : null;

        $dto = new CoureurSoloData();
        $ficheExistante = null;
        $courseDetectee = null;

        if ($dossardRecherche !== null && $dossardRecherche > 0) {
            $ficheExistante = $inscriptionSoloService->rechercherParDossard($evenement, $dossardRecherche);
            if ($ficheExistante !== null) {
                $dto = $inscriptionSoloService->hydraterDtoDepuisFiche($ficheExistante);
            } else {
                $dto->dossard = $dossardRecherche;
            }
            $courseDetectee = $dossardResolver->resoudreCoursePourDossard($evenement, $dossardRecherche);
        }

        $form = $this->createForm(CoureurSoloType::class, $dto, [
            'courses_disponibles' => $contexte['courses_noms'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($dto->dossard !== null && $dto->dossard > 0) {
                $ficheExistante = $inscriptionSoloService->rechercherParDossard($evenement, $dto->dossard);
                $courseDetectee = $dossardResolver->resoudreCoursePourDossard($evenement, $dto->dossard);
            }

            if ($form->isValid() && $contexte['table_run_existe']) {
                $operateur = $this->getUser()?->getUserIdentifier() ?? 'admin';
                $resultat = $inscriptionSoloService->enregistrerCoureur($evenement, $dto, $operateur);

                $verbe = $resultat['action'] === 'insert' ? 'ajouté' : 'mis à jour';
                $this->addFlash('success', sprintf(
                    'Dossard #%d (%s) %s sur %s (%s). Total : %d participants. Prise en compte dans les classements au prochain import DAG.',
                    $resultat['dossard'],
                    $resultat['nom_prenom'],
                    $verbe,
                    $resultat['distance'],
                    $resultat['categorie'],
                    $resultat['total_participants']
                ));

                if ($resultat['hors_plage']) {
                    $this->addFlash('warning', sprintf(
                        'Le dossard #%d est hors des plages codées pour cet événement (enregistré sur « %s »).',
                        $resultat['dossard'],
                        $resultat['distance']
                    ));
                }

                return $this->redirectToRoute('rc_inscriptions_solo');
            }

            if (!$contexte['table_run_existe']) {
                $this->addFlash('error', sprintf("La table '%s' est absente en base locale.", $evenement['table_run'] ?? ''));
            }
        }

        return $this->render('rc/inscriptions/solo.html.twig', [
            'evenement' => $evenement,
            'contexte' => $contexte,
            'form' => $form->createView(),
            'dossard_recherche' => $dossardRecherche,
            'fiche_existante' => $ficheExistante,
            'course_detectee' => $courseDetectee,
        ]);
    }

    #[Route('/solo/lookup', name: 'rc_inscriptions_solo_lookup', methods: ['GET'])]
    public function lookupDossard(
        Request $request,
        EvenementActifService $evenementActifService,
        InscriptionSoloService $inscriptionSoloService,
        DossardResolverService $dossardResolver,
    ): JsonResponse {
        $evenement = $evenementActifService->getEvenementActif();
        if ($evenement === null) {
            return $this->json(['error' => 'Aucun événement actif'], Response::HTTP_BAD_REQUEST);
        }

        $dossardParam = trim((string) $request->query->get('dossard', ''));
        if (!ctype_digit($dossardParam) || (int) $dossardParam <= 0) {
            return $this->json([
                'found' => false,
                'coureur' => null,
                'course_resolue' => null,
                'hors_plage' => false,
            ]);
        }

        $dossard = (int) $dossardParam;
        $coureur = $inscriptionSoloService->rechercherParDossard($evenement, $dossard);
        $courseResolue = $dossardResolver->resoudreCoursePourDossard($evenement, $dossard);
        $reglesActives = $dossardResolver->possedeReglesDossards($evenement);

        return $this->json([
            'found' => $coureur !== null,
            'coureur' => $coureur,
            'course_resolue' => $courseResolue,
            'hors_plage' => $reglesActives && $courseResolue === null,
        ]);
    }
}
