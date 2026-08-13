<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Fixtures\MarketDataFixture;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationRedirectionTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Initialiser le client pour démarrer le kernel
        $this->client = static::createClient();

        // 2. Charger les fixtures en base de données
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $fixture = new MarketDataFixture($connection);
        $fixture->load();
    }

    public function testRegistrationRedirectsToSettings(): void
    {
        // 1. Aller sur la page d'inscription
        $crawler = $this->client->request('GET', '/register');

        // 2. Remplir le formulaire
        $form = $crawler->selectButton(
            'CRÉER MON COMPTE')->form(
                [
                    'registration_form[email]' => 'test-new-user@example.com',
                    'registration_form[plainPassword]' => 'Password123!',
                    'registration_form[agreeTerms]' => true,
                ]
            );

        $this->client->submit($form);

        // 3. Vérifier que la redirection est déclenchée
        self::assertResponseRedirects();

        // 4. Suivre la redirection (Login automatique → Authenticator → Redirection)
        $this->client->followRedirect();

        // 5. Vérifier que l'URL finale est bien /settings
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Simulateur de Cycle');
        self::assertRouteSame('app_strategy_description');
    }
}
