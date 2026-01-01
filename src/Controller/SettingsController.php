<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Position;
use App\Entity\Entrypoint;
use App\Form\EntrypointType;
use App\Enum\PositionStatus;
use App\Repository\LvcDailyRepository;
use App\Repository\CacDailyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SettingsController extends AbstractController
{
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

        // 1. Récupérer les derniers prix du CAC et du LVC
        $lastCacData = $cacRepo->createQueryBuilder('c')
            ->orderBy('c.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $lastLvcPrice = (float)$lvcRepo->createQueryBuilder('l')
            ->select('l.high')
            ->orderBy('l.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        if (!$lastCacData) {
            $this->addFlash('error', "Données de marché (CAC40) indisponibles.");
            return $this->redirectToRoute('app_home');
        }

        // 2. Utiliser le formulaire lié à l'entité Entrypoint
        $entrypoint = new Entrypoint();
        $form = $this->createForm(EntrypointType::class, $entrypoint);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $lastCacPrice = (float)$lastCacData->getClose();

            // 1. Validation : La position doit être au moins égale à 1 part de LVC
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

            // 3. Lier l'utilisateur et initialiser
            $entrypoint->setUser($user);
            $entrypoint->setStatus(PositionStatus::WAITING);

            // 4. LOGIQUE DES 3 POSITIONS
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
}
