<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testIndexRedirectsToLogin(): void
    {
        // Ici on utilise self à la placed de static car la classe est déclarée `final`
        $client = self::createClient();

        $client->request('GET', '/');

        // Vérifier la redirection HTTP 302 vers /login
        self::assertResponseRedirects('/login');

        // Optionnel : suivre la redirection et vérifier que la page de login s'affiche correctement
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
