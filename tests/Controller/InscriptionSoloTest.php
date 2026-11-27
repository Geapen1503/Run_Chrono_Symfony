<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de la page « Inscription > Coureur (solo) » (/rc/inscriptions/solo).
 */
class InscriptionSoloTest extends WebTestCase
{
    public function testPageEtLookupNecessitentAuthentification(): void
    {
        $client = static::createClient();

        $client->request('GET', '/rc/inscriptions/solo');
        $this->assertResponseRedirects('/login');

        $client->request('GET', '/rc/inscriptions/solo/lookup?dossard=336');
        $this->assertResponseRedirects('/login');
    }

    public function testAfficheEtatVideSansEvenementActif(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        $client->request('GET', '/rc/inscriptions/solo');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h5', "Il n'y a pas d'événement actuellement.");
        $this->assertSelectorExists('a[href="/rc/participants/evenement-actif"]');
    }

    public function testLookupAjoutEtModificationCoureurSoloSurEvenementActif(): void
    {
        $client = static::createClient();
        $this->connecterAdmin($client);

        // Activation de l'événement Avanton en session
        $crawlerEvt = $client->request('GET', '/rc/participants/evenement-actif');
        $tokenEvt = $crawlerEvt->filter('form[action="/rc/evenement-actif"] input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/rc/evenement-actif', [
            '_token' => $tokenEvt,
            '_redirect' => '/rc/inscriptions/solo',
            'id_html' => '20260919_Trail_Cone_dOr_Avanton',
        ]);
        $this->assertResponseRedirects('/rc/inscriptions/solo');

        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $tableRun = 'run_trail_avanton_2026';
        $dossardTest = 9999;

        $db->delete($tableRun, ['dossard' => (string) $dossardTest]);

        try {
            // 1. Affichage du formulaire avec l'événement actif
            $crawler = $client->followRedirect();
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('h4.fw-bold', 'Trail du Cone d or d Avanton 2026 (86)');
            $this->assertSelectorExists('#coureurSoloForm');

            // 2. Endpoint lookup JSON sur un dossard libre
            $client->request('GET', '/rc/inscriptions/solo/lookup?dossard='.$dossardTest);
            $this->assertResponseIsSuccessful();
            $json = json_decode((string) $client->getResponse()->getContent(), true);
            $this->assertFalse($json['found']);

            // 3. Ajout d'un nouveau coureur (INSERT)
            $form = $crawler->selectButton('Enregistrer le coureur')->form([
                'coureur_solo[dossard]' => (string) $dossardTest,
                'coureur_solo[nom]' => 'Testé',
                'coureur_solo[prenom]' => 'Élodie',
                'coureur_solo[sexe]' => 'F',
                'coureur_solo[annee]' => '1995',
                'coureur_solo[club]' => 'EPA86',
                'coureur_solo[licence]' => '123456',
                'coureur_solo[distanceManuelle]' => 'Course 6KM',
            ]);
            $client->submit($form);
            $this->assertResponseRedirects('/rc/inscriptions/solo');

            $insere = $db->fetchAssociative(
                "SELECT * FROM `{$tableRun}` WHERE dossard = :dossard LIMIT 1",
                ['dossard' => (string) $dossardTest]
            );
            $this->assertIsArray($insere);
            $this->assertSame('TESTE', $insere['nom']);
            $this->assertSame('ELODIE', $insere['prenom']);
            $this->assertSame('TESTE ELODIE', $insere['nom_prenom']);
            $this->assertSame('ENTENTE POITIERS ATHLE 86', $insere['club']);
            $this->assertSame('F', $insere['sexe']);

            // 4. Chargement via ?dossard=9999 puis modification (UPDATE)
            $crawlerModif = $client->request('GET', '/rc/inscriptions/solo?dossard='.$dossardTest);
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('#etatModeBadge', 'Mode modification');

            $formModif = $crawlerModif->selectButton('Mettre à jour le coureur')->form([
                'coureur_solo[dossard]' => (string) $dossardTest,
                'coureur_solo[nom]' => 'Testé-Modif',
                'coureur_solo[prenom]' => 'Élodie',
                'coureur_solo[sexe]' => 'F',
                'coureur_solo[annee]' => '1995',
                'coureur_solo[club]' => 'CA Pictave',
                'coureur_solo[distanceManuelle]' => 'Course 6KM',
            ]);
            $client->submit($formModif);
            $this->assertResponseRedirects('/rc/inscriptions/solo');

            $maj = $db->fetchAssociative(
                "SELECT * FROM `{$tableRun}` WHERE dossard = :dossard LIMIT 1",
                ['dossard' => (string) $dossardTest]
            );
            $this->assertIsArray($maj);
            $this->assertSame('TESTE-MODIF', $maj['nom']);
            $this->assertSame('CA PICTAVE', $maj['club']);
        } finally {
            $db->delete($tableRun, ['dossard' => (string) $dossardTest]);
        }
    }

    private function connecterAdmin(KernelBrowser $client): void
    {
        $userProvider = static::getContainer()->get('security.user.provider.concrete.app_users');
        $user = $userProvider->loadUserByIdentifier('admin@runchrono.local');
        $client->loginUser($user, 'main');
    }
}
