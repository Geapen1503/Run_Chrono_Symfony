<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Tests fonctionnels pour la consultation et recherche des années précédentes.
 */
class ResultatsAnneesPrecedentesTest extends WebTestCase
{
    /**
     * Vérifie que la page est protégée et redirige vers /login pour un visiteur non authentifié.
     */
    public function testPageAnneesPrecedentesNecessiteAuthentification(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rc/resultats/annees-precedentes');

        $this->assertResponseRedirects('/login');
    }

    /**
     * Vérifie que l'admin accède à la liste des années disponibles.
     */
    public function testAdminAccedeListeAnnees(): void
    {
        $client = static::createClient();
        $userProvider = static::getContainer()->get('security.user.provider.concrete.app_users');
        $user = $userProvider->loadUserByIdentifier('admin@runchrono.local');
        $client->loginUser($user, 'main');

        $crawler = $client->request('GET', '/rc/resultats/annees-precedentes');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h4', 'Archives des résultats');
        $this->assertSelectorExists('a[href*="/rc/resultats/annees-precedentes/"]');
    }

    /**
     * Vérifie qu'on peut consulter les résultats d'une année spécifique (ex: 2024).
     */
    public function testAdminAccedeResultatsAnnee(): void
    {
        $client = static::createClient();
        $userProvider = static::getContainer()->get('security.user.provider.concrete.app_users');
        $user = $userProvider->loadUserByIdentifier('admin@runchrono.local');
        $client->loginUser($user, 'main');

        $crawler = $client->request('GET', '/rc/resultats/annees-precedentes/2024');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h5', 'Éditions 2024');
        $this->assertSelectorExists('#eventSearchInput');
    }

    /**
     * Vérifie le fonctionnement du formulaire de recherche globale.
     */
    public function testRechercheGlobale(): void
    {
        $client = static::createClient();
        $userProvider = static::getContainer()->get('security.user.provider.concrete.app_users');
        $user = $userProvider->loadUserByIdentifier('admin@runchrono.local');
        $client->loginUser($user, 'main');

        $client->request('GET', '/rc/resultats/annees-precedentes?q=Trail');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-header', 'Résultats pour la recherche : « Trail »');
    }
}
