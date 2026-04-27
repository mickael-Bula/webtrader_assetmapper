<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\LogOrigin;
use App\Entity\Entrypoint;
use App\Service\LogManager;
use App\Form\EntrypointType;
use App\Enum\PositionStatus;
use Doctrine\DBAL\Exception;
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
    public function __construct(private readonly LogManager $logManager) {}

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
        StrategyManager        $strategyManager
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

            // Log des modifications de configuration
            $this->logManager->log(
                sprintf('Paramètres mis à jour : Portefeuille %.2f€, Size %.2f€, Spread %.2f',
                        $user->getTotalPortfolio(),
                        $user->getPositionSize(),
                        $user->getSpread()
                ),
                'update',
                LogOrigin::USER
            );

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
            $deleteMessage = $positionManager->deleteFormerWaitingPositions($user);
            // TODO : Voir s'il est possible d'afficher les flash messages successivement
            // On trace l'information.
            $message = sprintf('Entrypoint %d : %s', $entrypoint->getId(), $deleteMessage);
            if (str_contains($deleteMessage, 'Les anciens ordres en attente ont été supprimés.')) {
                $this->logManager->log($message, 'delete');
            }
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
            $strategyMessage = sprintf(
                'Stratégie activée (Entrypoint: %.2f). Initialisation de 3 positions.',
                $entrypoint->getEntrypoint()
            );
            $this->logManager->log(
                $strategyMessage,
                'create'
            );
            $this->addFlash('success', $strategyMessage);

            return $this->redirectToRoute('app_home');
        }

        return $this->render('settings/index.html.twig', [
            'settingsForm' => $form->createView(),
            'lastLvcPrice' => $lastLvcPrice,
        ]);
    }
}
