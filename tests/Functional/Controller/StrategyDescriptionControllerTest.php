<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Fixtures\MarketDataFixture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StrategyDescriptionControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        // Récupérer l'EntityManager
        $this->entityManager = $container->get('doctrine')->getManager();

        // Récupération de la fixture depuis le conteneur de test et exécution
        $fixture = static::getContainer()->get(MarketDataFixture::class);
        $fixture->load();
    }

    public function testPageDescription(): void
    {
        // 3. Créer un utilisateur de test en mémoire/base de test
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password123'); // Mettez les setters requis par votre entité
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // 4. Authentifier l'utilisateur dans le client HTTP
        $this->client->loginUser($user);

        // 5. Exécuter la requête
        $this->client->request('GET', '/strategy/description');

        $this->assertResponseIsSuccessful();
    }
}
