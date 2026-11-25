<?php

namespace App\Tests\Controller;

use App\Service\EvenementActifService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de la page « Liste complète des participants » (/rc/participants/liste)
 * et de la page dédiée « Événement actif » (/rc/participants/evenement-actif).
 */
class ParticipantsListeTest extends WebTestCase
{
    public function testPageListeParticipantsNecessiteAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rc/participants/liste');

        $this->assertResponseRedirects('/login');
    }

    public function testPageEvenementActifNecessiteAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rc/participants/evenement-actif');

        $this->assertResponseRedirects('/login');
    }

    public function testDefinirEvenementActifNecessiteAuthentification(): void
    {
        $client = static::createClient();
        $client->request('POST', '/rc/evenement-actif', ['id_html' => '20260919_Trail_Cone_dOr_Avanton']);

        $this->assertResponseRedirects('/login');
    }

    public function testParDefautAucunEvenementActifSansSelection(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $client->request('GET', '/rc/participants/liste');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('#rcActiveEventDropdown');
        $this->assertAnySelectorTextContains('h5', "Il n'y a pas d'événement actuellement.");
        $this->assertSelectorExists('a[href="/rc/participants/evenement-actif"]');
    }

    public function testAdminSelectionneEvenementPuisConsulteListeComplete(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        // 1. Page dédiée : aucun événement actif par défaut
        $crawler = $client->request('GET', '/rc/participants/evenement-actif');
        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h5', "Il n'y a pas d'événement actuellement.");
        $this->assertSelectorExists('#evenementsActifsTable tbody tr[data-search]');

        $token = $crawler->filter('form[action="/rc/evenement-actif"] input[name="_token"]')->first()->attr('value');
        $this->assertNotEmpty($token);

        // 2. Activation d'Avanton via la page dédiée
        $client->request('POST', '/rc/evenement-actif', [
            '_token' => $token,
            '_redirect' => '/rc/participants/evenement-actif',
            'id_html' => '20260919_Trail_Cone_dOr_Avanton',
        ]);
        $this->assertResponseRedirects('/rc/participants/evenement-actif');

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4.fw-bold', 'Trail du Cone d or d Avanton 2026 (86)');

        // 3. Consultation de la liste complète avec l'événement sélectionné
        $crawlerListe = $client->request('GET', '/rc/participants/liste');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4.fw-bold', 'Trail du Cone d or d Avanton 2026 (86)');
        $this->assertSelectorExists('a[href="/rc/participants/evenement-actif"]');
        $this->assertSelectorExists('#section-litiges');
        $this->assertSelectorTextContains('#section-litiges', '4 litiges');
        $this->assertCount(3, $crawlerListe->filter('.js-course-section'));
        $this->assertCount(98, $crawlerListe->filter('.js-participants-table tbody tr[data-ordre-nom]'));

        // 4. Désactivation depuis la page dédiée -> retour à l'état vide
        $tokenDesactiver = $crawler->filter('#btnDesactiverEvenement')->closest('form')->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/rc/evenement-actif', [
            '_token' => $tokenDesactiver,
            '_redirect' => '/rc/participants/evenement-actif',
            'id_html' => '',
        ]);
        $this->assertResponseRedirects('/rc/participants/evenement-actif');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h5', "Il n'y a pas d'événement actuellement.");
    }

    public function testDefinirEvenementActifRejetteCsrfInvalideEtRedirectionExterne(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        // Jeton CSRF invalide -> 403
        $client->request('POST', '/rc/evenement-actif', [
            '_token' => 'jeton_invalide',
            'id_html' => '20260919_Trail_Cone_dOr_Avanton',
        ]);
        $this->assertResponseStatusCodeSame(403);

        // Redirection externe bloquée -> fallback vers /rc/participants/evenement-actif
        $crawler = $client->request('GET', '/rc/participants/evenement-actif');
        $token = $crawler->filter('form[action="/rc/evenement-actif"] input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/rc/evenement-actif', [
            '_token' => $token,
            '_redirect' => 'https://evil.example/phishing',
            'id_html' => '20260919_Trail_Cone_dOr_Avanton',
        ]);
        $this->assertResponseRedirects('/rc/participants/evenement-actif');
    }

    public function testAdminAfficheMessageSiEvenementIntrouvable(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        /** @var EvenementActifService $evenementActifService */
        $evenementActifService = static::getContainer()->get(EvenementActifService::class);
        $evenementActifService->setActiveEventId('evenement_inexistant_xyz');

        $client->request('GET', '/rc/participants/liste');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h5', "Il n'y a pas d'événement actuellement.");
    }

    private function connecterAdmin(KernelBrowser $client): void
    {
        $userProvider = static::getContainer()->get('security.user.provider.concrete.app_users');
        $user = $userProvider->loadUserByIdentifier('admin@runchrono.local');
        $client->loginUser($user, 'main');
    }
}
