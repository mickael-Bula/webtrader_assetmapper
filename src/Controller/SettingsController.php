<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Entrypoint;
use App\Form\EntrypointType;
use App\Enum\PositionStatus;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use App\Service\PositionManager;
use App\Service\StrategyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\LvcDailyRepository;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SettingsController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/settings', name: 'app_settings')]
    public function index(
        Request                $request,
        EntityManagerInterface $em,
        CacDailyRepository     $cacRepo,
        LvcDailyRepository     $lvcRepo,
        PositionManager        $positionManager,
        StrategyManager        $strategyManager,
        LoggerInterface        $tradingLogger
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1. Récupérer (sous forme de DTO) les derniers prix du CAC et du LVC
        $lastCacPrice = $cacRepo->findLast()?->getClose();
        $lastLvcPrice = $lvcRepo->findLast()?->getClose();

        if (!$lastCacPrice) {
            $this->addFlash('error', "Données de marché (CAC40) indisponibles.");
            return $this->redirectToRoute('app_home');
        }

        // 2. Utiliser le formulaire lié à l'entité Entrypoint
        $entrypoint = new Entrypoint();
        $form = $this->createForm(EntrypointType::class, $entrypoint, ['user_data' => $user]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO : déplacer toute cette logique dans le service StrategyManager
            // 1. On met à jour l'entité User avec les données du formulaire
            $user->setTotalPortfolio((string)$form->get('totalPortfolio')->getData());
            $user->setPositionSize((string)$form->get('positionSize')->getData());
            $user->setSpread($form->get('spread')->getData());

            // 2. On lie l'entrypoint à son user
            $entrypoint->setUser($user);

            // 3. Validation : La position doit être au moins égale à une part de LVC
            if ($user->getPositionSize() < $lastLvcPrice) {
                $this->addFlash('error', "Position trop faible (Min: {$lastLvcPrice}€).");

                return $this->redirectToRoute('app_settings');
            }

            // 4. Validation : Le seuil d'entrée doit être inférieur au plus bas du CAC
            if ($entrypoint->getEntrypoint() > $lastCacPrice) {
                $this->addFlash('error', sprintf(
                    "Le seuil d'entrée (%.2f) est supérieur au cours actuel (%.2f).",
                    $entrypoint->getEntrypoint(),
                    $lastCacPrice
                ));

                return $this->redirectToRoute('app_settings');
            }

            // 5. On s'assure de ne pas dupliquer les positions pour ne pas être surexposé.
            $message = $positionManager->deleteFormerWaitingPositions($user);

            // On trace l'information.
            $tradingLogger->info(sprintf('Entrypoint %d : %s', $entrypoint->getId(), $message));
            $this->addFlash('success', $message);

            // 6. Lie l'utilisateur et initialise
            $entrypoint->setUser($user);
            $entrypoint->setStatus(PositionStatus::WAITING);
            $user->setBuyLimit($entrypoint->getEntrypoint());
            $user->setUpperRange($strategyManager->calculateUpperRange($entrypoint));
            $user->setLastCacUpdatedId($cacRepo->findLast()?->getId());

            // On enregistre en mémoire l'entrypoint.
            $em->persist($entrypoint);

            // 7. Création des trois positions en attente, la première créée au niveau de l'entrypoint.
            $positionManager->createWaitingPositionsForInitialEntrypoint($entrypoint, $lastCacPrice, $lastLvcPrice);

            // On sauvegarde en base de données.
            $em->flush();

            // 8. On trace l'initialisation des positions en attente dans le journal.
            $message = 'Stratégie activée. Trois positions en attente créées.';
            $tradingLogger->info(sprintf('Entrypoint %d : %s', $entrypoint->getId(), $message));
            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_home');
        }

        return $this->render('settings/index.html.twig', [
            'settingsForm' => $form->createView(),
            'lastLvcPrice' => $lastLvcPrice,
        ]);
    }
}
