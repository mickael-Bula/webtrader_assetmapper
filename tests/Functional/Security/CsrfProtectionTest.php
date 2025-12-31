<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CsrfProtectionTest extends WebTestCase
{
    /**
     * Test vérifiant qu'une redirection est faite vers la page de login
     * lors de l'expiration du CSRF Token.
     */
    public function testInvalidCsrfRedirectsToLogin(): void
    {
        $client = static::createClient();

        // 1. On va sur la page de login
        $crawler = $client->request('GET', '/login');

        // 2. On sélectionne le formulaire
        $form = $crawler->selectButton('SE CONNECTER')->form();

        // 3. On altère MANUELLEMENT le jeton CSRF pour le rendre invalide
        $values = $form->getPhpValues();
        $values['_csrf_token'] = 'mauvais_token';

        // 4. On soumet le formulaire avec les valeurs modifiées
        $client->request($form->getMethod(), $form->getUri(), $values);

        // 5. ASSERTION : On vérifie que le Listener a intercepté l'erreur et nous redirige (code 302) vers /login
        self::assertResponseRedirects('/login');

        // On suit la redirection et on vérifie la présence du message flash
        $client->followRedirect();
        self::assertSelectorExists('.text-red-400');
        self::assertSelectorTextContains('.text-red-400', 'CSRF');
    }
}
