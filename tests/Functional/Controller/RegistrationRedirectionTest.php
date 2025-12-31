<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationRedirectionTest extends WebTestCase
{
    public function testRegistrationRedirectsToSettings(): void
    {
        $client = static::createClient();

        // 1. Aller sur la page d'inscription
        $crawler = $client->request('GET', '/register');

        // 2. Remplir le formulaire
        $form = $crawler->selectButton(
            'CRÉER MON COMPTE')->form(
                [
                    'registration_form[email]' => 'test-new-user@example.com',
                    'registration_form[plainPassword]' => 'Password123!',
                    'registration_form[agreeTerms]' => true,
                ]
        );

        $client->submit($form);

        // 3. Vérifier que la redirection est déclenchée
        self::assertResponseRedirects();

        // 4. Suivre la redirection (Login automatique -> Authenticator -> Redirection)
        $client->followRedirect();

        // 5. Vérifier que l'URL finale est bien /settings
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Configuration du Portefeuille');
        self::assertRouteSame('app_settings');
    }
}
