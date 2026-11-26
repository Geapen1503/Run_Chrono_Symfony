<?php

namespace App\Controller;

use App\Edition\Model\EditionCourseConfig;
use App\Edition\Service\EditionCatalogReader;
use App\Edition\Service\EditionMediaStorage;
use App\Edition\Service\EditionNamingService;
use App\Edition\Service\EditionPayloadFactory;
use App\Edition\Service\EditionWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogue éditions — procédure legacy simplifiée (étapes 2+5).
 */
#[Route('/rc/editions')]
#[IsGranted('ROLE_ADMIN')]
class RcEditionController extends AbstractController
{
    public function __construct(
        private EditionCatalogReader $catalogReader,
        private EditionWorkflow $workflow,
        private EditionPayloadFactory $payloadFactory,
        private EditionMediaStorage $mediaStorage,
        private EditionNamingService $naming,
    ) {
    }

    #[Route('', name: 'rc_editions_list', methods: ['GET'])]
    public function list(): Response
    {
        try {
            $editions = $this->catalogReader->listRecent();
        } catch (\Throwable $e) {
            $editions = [];
            $this->addFlash('warning', 'Impossible de lire param_inscription_manuelle : '.$e->getMessage());
        }

        return $this->render('rc/editions/list.html.twig', [
            'editions' => $editions,
        ]);
    }

    #[Route('/nouvelle', name: 'rc_editions_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->handleForm($request, null);
    }

    #[Route('/{idHtml}/modifier', name: 'rc_editions_edit', methods: ['GET', 'POST'])]
    public function edit(string $idHtml, Request $request): Response
    {
        return $this->handleForm($request, $idHtml);
    }

    private function handleForm(Request $request, ?string $idHtml): Response
    {
        $isEdit = $idHtml !== null;
        $payload = null;
        $suggest = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edition_save', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');

                return $this->redirectToRoute($isEdit ? 'rc_editions_edit' : 'rc_editions_new', $isEdit ? ['idHtml' => $idHtml] : []);
            }

            try {
                $payload = $this->payloadFactory->fromRequest($request);
                if ($isEdit && $payload->idHtml() !== $idHtml) {
                    throw new \InvalidArgumentException('id_html verrouillé en édition.');
                }

                $affiche = $request->files->get('affiche_upload');
                if ($affiche) {
                    $payload->afficheFilename = $this->mediaStorage->storeUploaded($affiche);
                }
                $reglement = $request->files->get('reglement_upload');
                if ($reglement) {
                    $payload->reglementFilename = $this->mediaStorage->storeUploaded($reglement);
                }

                $result = $this->workflow->save($payload);
                foreach ($result['messages'] as $message) {
                    $this->addFlash('success', $message);
                }
                foreach ($result['warnings'] as $warning) {
                    $this->addFlash('warning', $warning);
                }

                return $this->redirectToRoute('rc_editions_edit', ['idHtml' => $payload->idHtml()]);
            } catch (\Throwable $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        if ($payload === null) {
            if ($isEdit) {
                try {
                    $payload = $this->catalogReader->loadForEdit($idHtml);
                } catch (\Throwable $e) {
                    $this->addFlash('danger', $e->getMessage());

                    return $this->redirectToRoute('rc_editions_list');
                }
            } else {
                $payload = new \App\Edition\Model\EditionPayload();
                for ($i = 1; $i <= 6; ++$i) {
                    $payload->courses[] = new EditionCourseConfig(index: $i);
                }
                $suggest = $this->naming->suggestTableNames('nouvelle_course', (string) date('Y'));
            }
        }

        return $this->render('rc/editions/form.html.twig', [
            'payload' => $payload,
            'is_edit' => $isEdit,
            'suggest' => $suggest,
        ]);
    }
}