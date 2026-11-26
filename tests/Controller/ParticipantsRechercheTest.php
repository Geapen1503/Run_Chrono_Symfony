<?php

namespace App\Tests\Controller;

use App\Service\EvenementActifService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels Phase 2.1 — recherche coureur sur table run_* de l'événement actif.
 */
class ParticipantsRechercheTest extends WebTestCase
{
    public function testRechercheNecessiteAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rc/participants/recherche');

        $this->assertResponseRedirects('/login');
    }

    public function testRechercheSansEvenementActifBloqueLaSoumissionVisuelle(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $crawler = $client->request('GET', '/rc/participants/recherche');
        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('.card-body', 'Aucun événement actif');

        $button = $crawler->filter('form button[type="submit"]');
        $this->assertGreaterThan(0, $button->count());
        $this->assertNotNull($button->attr('disabled'));
    }

    public function testRechercheDossardSurTableRunEvenementActif(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $idHtml = '20260919_Trail_Cone_dOr_Avanton';
        /** @var EvenementActifService $evenementActifService */
        $evenementActifService = static::getContainer()->get(EvenementActifService::class);
        if ($evenementActifService->trouverEvenementParId($idHtml) === null) {
            $this->markTestSkipped('Base de test sans événement '.$idHtml);
        }
        $evenementActifService->setActiveEventId($idHtml);

        $crawler = $client->request('GET', '/rc/participants/recherche', ['dossard' => '1']);
        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('code', 'run_');

        $lignes = $crawler->filter('table.table-hover tbody tr');
        if ($lignes->count() > 0) {
            $this->assertSelectorExists('table.table-hover tbody tr td');
        } else {
            $this->assertSelectorExists('.alert-info, .alert-danger');
        }
    }

    public function testRechercheModeLegacyChampQ(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $idHtml = '20260919_Trail_Cone_dOr_Avanton';
        /** @var EvenementActifService $evenementActifService */
        $evenementActifService = static::getContainer()->get(EvenementActifService::class);
        if ($evenementActifService->trouverEvenementParId($idHtml) === null) {
            $this->markTestSkipped('Base de test sans événement '.$idHtml);
        }
        $evenementActifService->setActiveEventId($idHtml);

        $client->request('GET', '/rc/participants/recherche', ['q' => 'DUPONT']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h6.mb-3, .alert-info, .alert-danger');
    }

    private function connecterAdmin(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        $userProvider = static::getContainer()->get('security.user.provider.concrete.app_users');
        $user = $userProvider->loadUserByIdentifier('admin@runchrono.local');
        $client->loginUser($user, 'main');
    }
}