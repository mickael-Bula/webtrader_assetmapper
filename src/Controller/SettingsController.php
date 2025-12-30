<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\PortfolioSettingsType;
use App\Repository\LvcDailyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function index(Request $request, EntityManagerInterface $em, LvcDailyRepository $lvcRepo): Response
    {
        $user = $this->getUser();

        // Récupère la dernière valeur du LVC en base.
        $lastLvcPrice = (float) $lvcRepo->createQueryBuilder('l')
            ->select('l.high') // On ne sélectionne QUE le champ high
            ->orderBy('l.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        $form = $this->createForm(PortfolioSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $allocation = $data['total_portfolio'];
            $posSize = $data['position_size'];

            // Validation du minimum (Valeur d'un LVC)
            if ($posSize < $lastLvcPrice) {
                $this->addFlash(
                    'error',
                    "La position est trop faible (Minimum: valeur d'un LVC, soit {$lastLvcPrice}€)."
                );

                return $this->redirectToRoute('app_settings');
            }

            // Enregistrement des réglages Utilisateur (Profil)
//            $user->setHighestLocal($data['highest_local']);
//            $user->setTotalAllocation($allocation);
//            $user->setPositionSize($posSize);
//
//            // 4. Initialisation de la première BuyLimit
//            $buyLimit = new BuyLimit();
//            $buyLimit->setUser($user);
//            $buyLimit->setLimitValue($data['highest_local'] * 0.95); // Exemple: -5% du plus haut
//            // On lie au CAC et LVC du jour via votre repository
//            // $buyLimit->setCacReference($marketRepo->findCurrentCac());
//
//            $em->persist($user);
//            $em->persist($buyLimit);
//            $em->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('settings/index.html.twig', [
            'settingsForm' => $form->createView(),
        ]);
    }
}
