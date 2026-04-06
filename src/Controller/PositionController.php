<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Position;
use App\Form\PositionType;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PositionController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route('/position/create', name: 'app_position_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        // 1. Récupération des données du formulaire
        $quantity = $request->request->get('quantity');
        $buyPriceCac = $request->request->get('buy_price_cac');
        $buyPriceLvc = $request->request->get('buy_price_lvc');
        $targetPriceCac = $request->request->get('target_price_cac');
        $targetPriceLvc = $request->request->get('target_price_lvc');
        $validityDate = $request->request->get('validity_date');

        // 2. Création de l'entité
        $position = new Position();
//        $position->setQuantity((int)$quantity);
        $position->setBuyPrice((string)$buyPriceCac);
        $position->setLvcBuyPrice((string)$buyPriceLvc);
        $position->setTargetPrice((string)$targetPriceCac);
        $position->setLvcTargetPrice((string)$targetPriceLvc);

        // Gestion de la date
//        if ($validityDate) {
//            $position->setValidityDate(new \DateTime($validityDate));
//        }

        // Il faut définir le statut de la position en fonction du type créé
        // $position->setStatus('waiting');

        // 3. Sauvegarde
        $entityManager->persist($position);
        $entityManager->flush();

        $this->addFlash('success', 'La nouvelle position a été enregistrée avec succès.');

        // 4. Redirection vers le dashboard
        return $this->redirectToRoute('app_home');
    }

    #[Route('/position/{id}/edit', name: 'app_position_edit', methods: ['GET', 'POST'])]
    public function edit(Position $position, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PositionType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                $html = $this->renderView('_partials/_position_drawer.html.twig', [
                    'position' => $position,
                ]);

                return new Response($html);
            }

            $this->addFlash('success', 'La position a été modifiée avec succès.');

            return $this->redirectToRoute('app_home');
        }

        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            $html = $this->renderView('_partials/_position_edit_modal.html.twig', [
                'position' => $position,
                'form' => $form->createView(),
            ]);

            return new Response($html);
        }

        return $this->render('_partials/_position_edit_modal.html.twig', [
            'position' => $position,
            'form' => $form,
        ]);
    }
}
