<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Entrypoint;
use App\Form\EntrypointType;
use App\Enum\PositionStatus;
use Doctrine\DBAL\Exception;
use App\Service\PositionManager;
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
        PositionManager        $positionManager
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
        $form = $this->createForm(EntrypointType::class, $entrypoint);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1. Validation : La position doit être au moins égale à une part de LVC
            if ($entrypoint->getPositionSize() < $lastLvcPrice) {
                $this->addFlash('error', "Position trop faible (Min: {$lastLvcPrice}€).");

                return $this->redirectToRoute('app_settings');
            }

            // 2. Validation : Le seuil d'entrée doit être inférieur au plus bas du CAC
            if ($entrypoint->getEntrypoint() > $lastCacPrice) {
                $this->addFlash('error', sprintf(
                    "Le seuil d'entrée (%.2f) est supérieur au cours actuel (%.2f).",
                    $entrypoint->getEntrypoint(),
                    $lastCacPrice
                ));

                return $this->redirectToRoute('app_settings');
            }

            // 3. On s'assure de ne pas dupliquer les positions pour ne pas être surexposé.
            $message = $positionManager->deleteFormerWaitingPositions($user);
            $this->addFlash('info', $message);

            // 4. Lier l'utilisateur et initialiser
            $entrypoint->setUser($user);
            $entrypoint->setStatus(PositionStatus::WAITING);
            $user->setBuyLimit($entrypoint->getEntrypoint());
            $user->setUpperRange($entrypoint->getCalculatedUpperRange());
            $user->setLastCacUpdatedId($cacRepo->findLast()?->getId());

            // 5. Création des trois positions en attente.
            $positionManager->createWaitingPositionsForEntrypoint(
                $entrypoint,
                (float)$entrypoint->getEntrypoint(),
                (float)$lastLvcPrice
            );

            $em->persist($entrypoint);
            $em->flush();

            $this->addFlash('success', "Stratégie activée. Trois positions en attente créées.");

            return $this->redirectToRoute('app_home');
        }

        return $this->render('settings/index.html.twig', [
            'settingsForm' => $form->createView(),
            'lastLvcPrice' => $lastLvcPrice,
        ]);
    }
}
