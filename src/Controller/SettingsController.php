<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Position;
use App\Entity\Entrypoint;
use App\Form\EntrypointType;
use App\Enum\PositionStatus;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\EntrypointRepository;
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
        LvcDailyRepository     $lvcRepo
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
            $message = $this->deleteFormerWaitingPositions($em);
            $this->addFlash('info', $message);

            // 4. Lier l'utilisateur et initialiser
            $entrypoint->setUser($user);
            $entrypoint->setStatus(PositionStatus::WAITING);
            $user->setBuyLimit($entrypoint->getEntrypoint());
            $user->setUpperRange($entrypoint->getCalculatedUpperRange());
            $user->setLastCacUpdatedId($cacRepo->findLast()?->getId());

            // 5. LOGIQUE DES 3 POSITIONS
            $seuilCacInitial = (float)$entrypoint->getEntrypoint();

            for ($rank = 1; $rank <= 3; $rank++) {
                $position = new Position();
                $position->setEntrypoint($entrypoint);
                $position->setRank($rank);
                $position->setStatus(PositionStatus::WAITING);

                // Calcul du seuil CAC pour ce rang
                $percentDropCac = ($rank - 1) * 0.02; // 0, 0.02, 0.04
                $cacTargetForRank = $seuilCacInitial * (1 - $percentDropCac);
                $position->setBuyPrice((string)$cacTargetForRank);

                // CALCUL LVC THÉORIQUE
                // 1. On calcule la baisse du CAC par rapport au cours actuel
                $cacDiffPercent = ($cacTargetForRank / $lastCacPrice) - 1;

                // 2. On applique le levier x2 pour trouver le prix LVC estimé
                $lvcDiffPercent = $cacDiffPercent * 2;
                $estimatedLvcBuy = $lastLvcPrice * (1 + $lvcDiffPercent);

                $position->setLvcBuyPrice((string)round($estimatedLvcBuy, 2));

                $em->persist($position);
            }

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

    /**
     * Supprime les positions en attente de tous les entrypoints actifs de l'utilisateur.
     * Marque tous les entrypoints sans positions en cours comme inactifs.
     */
    private function deleteFormerWaitingPositions(EntityManagerInterface $em): string
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var EntrypointRepository $entrypointRepo */
        $entrypointRepo = $em->getRepository(Entrypoint::class);

        // On récupère la totalité et le dernier des entrypoints actifs de l'utilisateur
        $activeEntrypoints = $entrypointRepo->findActiveEntrypoints($user);
        $latestEntrypoint = $activeEntrypoints[0] ?? null;

        if (!$latestEntrypoint) {
            return '';
        }

        // Si des ordres en cours existent, on les conserve.
        $startMessage = '';
        if ($latestEntrypoint->isLocked()) {
            $startMessage = 'Une ou plusieurs positions en cours existent et ont été conservées. ';
        }

        // On supprime les positions en attente de tous les entrypoints actifs de l'utilisateur.
        foreach ($activeEntrypoints as $entrypoint) {
            foreach ($entrypoint->getPositions() as $position) {
                if ($position->getStatus() === PositionStatus::WAITING) {
                    $entrypoint->removePosition($position);
                    $em->remove($position);
                }
            }
            // On change le statut des entrypoints précédents et sans positions 'en cours' pour les rendre 'inactifs'.
            if (!$entrypoint->isLocked()) {
                $entrypoint->setStatus(PositionStatus::CLOSED);
                $em->flush();
            }
        }

        return $startMessage . 'Les anciens ordres en attente ont été supprimés.';
    }
}
