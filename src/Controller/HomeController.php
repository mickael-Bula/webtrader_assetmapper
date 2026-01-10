<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use App\Enum\PositionStatus;
use App\Dto\MarketData\CacDailyDto;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/', name: 'app_home')]
    public function index(
        CacDailyRepository $cacRepository,
        PositionRepository $positionRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $latestCacDto = $cacRepository->findLast();

        // Si le dernier Cac disponible diffère de celui enregistré, on vérifie si les positions ont été touchées.
        if ($latestCacDto && $user->getLastCacUpdatedId() !== $latestCacDto->getId()) {

            $this->checkPositionsTargets($user, $latestCacDto, $entityManager);
        }

        $runningPositions = $positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user);
        $waitingPositions = $positionRepository->findByStatusAndUser(PositionStatus::WAITING, $user);

        $cacQuotes = $cacRepository->findLastQuotesWithLvc();
        $lastQuote = $cacQuotes[0]->getcacClose();
        $lastHigh = $user->getUpperRange();
        $buyLimit = $user->getBuyLimit();

        return $this->render('home/index.html.twig', [
            'runningPositions' => $runningPositions,
            'waitingPositions' => $waitingPositions,
            'cacQuotes' => $cacQuotes,
            'lastQuote' => $lastQuote,
            'lastHigh' => $lastHigh,
            'buyLimit' => $buyLimit,
        ]);
    }

    private function checkPositionsTargets(
        User $user,
        CacDailyDto $latestCacDto,
        EntityManagerInterface $entityManager
    ): void
    {
        // On récupère toutes les cotations depuis la dernière visite de l'utilisateur, triées des plus anciennes au plus récentes.
        // On récupère les positions en cours et les positions en attente de l'utilisateur.
        // On boucle sur les cotations.
        // Pour chaque position en cours, triée par rang, on vérifie si la sellTarget est touchée par le plus haut du Cac courant.
        // Si c'est le cas, on passe la position en closed.
        // Ensuite, pour chaque position en attente, triée par rang, on vérifie si la buyTarget est touchée par le plus bas du Cac courant.
        // Si c'est le cas, on passe la position en running.
        // Si cette position est de rank=1, on crée un nouvel entrypoint pour l'utilisateur et on crée trois nouvelles positions en attente, en supprimant toutes les autres positions en attente.
        // À voir s'il faut à nouveau boucler sur ces positions nouvellement créées pour vérifier si le plsu bas du Cac les a touchées.

        // On met à jour le Cac de l'utilisateur.
//        $user->setLastCacUpdatedId($latestCacDto->getId());
//        $entityManager->flush();
    }
}
