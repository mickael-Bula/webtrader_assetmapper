<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\LogOrigin;
use App\Entity\Position;
use App\Form\PositionType;
use App\Entity\Entrypoint;
use App\Service\LogManager;
use App\Enum\PositionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PositionController extends AbstractController
{
    public function __construct(private readonly LogManager $logManager)
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/position/create', name: 'app_position_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupère le repository des Entrypoints
        $entrypointRepo = $entityManager->getRepository(Entrypoint::class);

        $buyPriceCac = $request->request->get('buy_price_cac');
        $buyPriceLvc = $request->request->get('buy_price_lvc');
        $targetPriceCac = $request->request->get('target_price_cac');
        $targetPriceLvc = $request->request->get('target_price_lvc');
        $quantity = (int)$request->request->get('quantity');
        $validityDate = $request->request->get('validity_date');
        $isActive = $request->request->getBoolean('is_active');

        if ($isActive) {
            // On désactive tous les autres entrypoints de l'utilisateur
            $entrypointRepo->updatePreviousEntrypoints($user);
        }

        // Le statut est transmis depuis le template Twig en utilisant l'id du tableau de positions
        $statusValue = $request->request->get('status', 'waiting');
        $status = PositionStatus::tryFrom($statusValue) ?? PositionStatus::WAITING;

        if ($targetPriceLvc <= $buyPriceLvc || $targetPriceCac <= $buyPriceCac) {
            $this->addFlash('error', 'La cible de revente doit être supérieure au prix d’achat.');
            return $this->redirectToRoute('app_home');
        }

        // --- GESTION DE L'ENTRYPOINT ---
        // On cherche si un Entrypoint existe déjà à ce prix pour cet utilisateur
        $entrypoint = $entrypointRepo->findOneBy(
            [
                'entrypoint' => $buyPriceCac,
                'user' => $user,
            ]);

        if (!$entrypoint) {
            $entrypoint = new Entrypoint();
            $entrypoint->setEntrypoint($buyPriceCac);
            $entrypoint->setUser($user);
            $entrypoint->setIsActive($isActive);
            $entrypoint->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($entrypoint);
        }

        $position = new Position();
        $position->setEntrypoint($entrypoint);
        $position->setStatus($status);
        $position->setRank(1);

        // Remplissage des données
        $position->setQuantity($quantity);
        $position->setBuyPrice($buyPriceCac);
        $position->setLvcBuyPrice($buyPriceLvc);
        $position->setTargetPrice($targetPriceCac);
        $position->setLvcTargetPrice($targetPriceLvc);

        if ($validityDate) {
            $position->setCreatedAt(new \DateTimeImmutable());
        }

        $entityManager->persist($position);
        $entityManager->flush();

        // Ajout du log de création
        $this->logManager->log(
            "Entrypoint #{$position->getEntrypoint()?->getId()} ({$position->getEntrypoint()?->getEntrypoint()} pts) : position #{$position->getRank()} achetée à {$buyPriceCac} pts",
            'create',
            LogOrigin::USER
        );

        $this->addFlash('success', 'Position enregistrée avec succès.');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/position/{id}/edit', name: 'app_position_edit', methods: ['GET', 'POST'])]
    public function edit(Position $position, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PositionType::class, $position);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // TODO : Il faudrait ici ajouter une validation des données avant enregistrement.
            // Validation métier manuelle avant le flush
            if ($position->getLvcTargetPrice() <= $position->getLvcBuyPrice()) {
                $this->addFlash('error', 'L’objectif LVC doit être supérieur au prix d’achat.');
                // En cas d'erreur AJAX, on pourrait renvoyer une erreur '400'
            } else {
                $entityManager->flush();

                // Ajout du log de modification
                $this->logManager->log(
                    "Entrypoint #{$position->getEntrypoint()?->getId()} ({$position->getEntrypoint()?->getEntrypoint()} pts) : position #{$position->getRank()} modifiée à {$position->getBuyPrice()} pts",
                    'create',
                    LogOrigin::USER
                );

                $this->addFlash('success', 'La position a été modifiée avec succès.');
            }

            if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse([
                                            'position' => [
                                                'id' => $position->getId(),
                                                'quantity' => $position->getQuantity(),
                                                'lvcBuyPrice' => $position->getLvcBuyPrice(),
                                                'buyPrice' => $position->getBuyPrice(),
                                                'lvcTargetPrice' => $position->getLvcTargetPrice(),
                                                'targetPrice' => $position->getTargetPrice(),
                                            ],
                                        ]);
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

    #[Route('/position/{id}/delete', name: 'app_position_delete', methods: ['DELETE'])]
    public function delete(
        Position                  $position,
        Request                   $request,
        EntityManagerInterface    $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response
    {
        // Ici la gestion du token CSRF est nécessaire, car l'appel de la route est fait en AJAX
        if (!$this->isCsrfTokenValid('delete_position_' . $position->getId(), $request->headers->get('X-CSRF-TOKEN'))) {
            return new JsonResponse(['error' => 'Action non autorisée'], 403);
        }

        $entityManager->remove($position);
        $entityManager->flush();

        // Ajout du log de suppression
        $this->logManager->log(
            "Entrypoint #{$position->getEntrypoint()?->getId()} ({$position->getEntrypoint()?->getEntrypoint()} pts) : position #{$position->getRank()} supprimée",
            'delete',
            LogOrigin::USER
        );

        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse(['success' => true, 'id' => $position->getId()]);
        }

        $this->addFlash('success', 'La position a été supprimée avec succès.');

        return $this->redirectToRoute('app_home');
    }
}
