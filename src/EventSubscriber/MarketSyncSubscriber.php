<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\MarketData\CacDailyRepository;
use App\Service\PositionManager;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class MarketSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private LoggerInterface $tradingLogger,
        private CacDailyRepository $cacRepository,
        private PositionManager $positionManager,
    ) {
    }

    /**
     * Cette méthode lance la mise à jour automatique des positions dès qu'une nouvelle cotation CAC est disponible.
     * Appelé à chaque requête pour vérifier si l'ID du dernier CAC traité diffère du dernier CAC disponible.
     *
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
        if (!$user || null === $user->getTotalPortfolio()) {
            return;
        }

        // On récupère le dernier CAC disponible en base
        $latestCacDto = $this->cacRepository->findLast();

        // Si le dernier Cac disponible diffère de celui enregistré, on vérifie si les positions ont été touchées.
        if ($latestCacDto && $user->getLastCacUpdatedId() !== $latestCacDto->getId()) {
            try {
                $this->positionManager->checkAndUpdatePositions($user, $latestCacDto);
            } catch (\Exception $e) {
                // On ajoute un message flash pour signaler l'erreur
                $this->requestStack->getSession()
                    ->getFlashBag()
                    ->add('error', 'Les données de marché sont momentanément indisponibles.');

                // On enregistre l'erreur dans le journal de trading (disponible dans le fichier var/log/trading.log).
                $this->tradingLogger->error($e->getMessage(), ['exception' => $e]);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }
}
