<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use App\Service\PositionManager;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class MarketSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security           $security,
        private CacDailyRepository $cacRepository,
        private PositionManager    $positionManager
    ) {}

    /**
     * @throws Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // On ne traite que la requête principale (pas les appels Twig {{ render(...) }})
        if (!$event->isMainRequest()) {

            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();

        // On vérifie que l'utilisateur est connecté et possède un capital initialisé
        if (!$user || $user->getTotalPortfolio() === null) {
            return;
        }

        // On récupère le dernier CAC disponible en base
        $latestCacDto = $this->cacRepository->findLast();

        // Si l'ID diffère, on lance la mise à jour globale
        if ($latestCacDto && $user->getLastCacUpdatedId() !== $latestCacDto->getId()) {
            $this->positionManager->checkAndUpdatePositions($user, $latestCacDto);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }
}
