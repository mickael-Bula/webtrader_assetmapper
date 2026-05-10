<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\LogOrigin;
use App\Enum\LogAction;
use App\Entity\Position;
use App\Enum\LogContext;
use App\Form\PositionType;
use App\Entity\Entrypoint;
use App\Service\LogManager;
use App\Enum\PositionStatus;
use App\Service\PositionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class PositionController extends AbstractController
{
    public function __construct(private readonly LogManager $logManager, private readonly PositionManager $positionManager)
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

        $meta = $this->positionManager->getLogMetadata($status);

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
            "Entrypoint #{$position->getEntrypoint()?->getId()} : "
                ."position #{$position->getRank()} {$meta['verb']} à {$buyPriceCac} pts",
            actionType: $meta['action'],
            origin: LogOrigin::USER,
            context: $meta['label']
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
            $entityManager->flush();

            // 1. Logique métier post-save
            $meta = $this->positionManager->getLogMetadata($position->getStatus());
            $this->logManager->log(
                "Entrypoint #{$position->getEntrypoint()?->getId()} : "
                    ."position #{$position->getRank()} modifiée à {$position->getBuyPrice()} pts",
                actionType: $meta['action'],
                origin: LogOrigin::USER,
                context: $meta['context']
            );

            // 2. Gestion AJAX (Succès)
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }

            // 3. Gestion Classique (Succès)
            $this->addFlash('success', 'La position a été modifiée avec succès.');

            return $this->redirectToRoute('app_home');
        }

        // --- Si on arrive ici, soit le formulaire n'est pas soumis, soit il est invalide ---
        // On détermine le status HTTP : 400 si soumis, mais invalide, sinon 200
        $status = ($form->isSubmitted() && !$form->isValid()) ? 400 : 200;

        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('_partials/_position_edit_modal.html.twig', [
                'position' => $position,
                'form' => $form->createView(),
            ]);

            return new Response($html, $status);
        }

        return $this->render('_partials/_position_edit_modal.html.twig', [
            'position' => $position,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/position/{id}/delete', name: 'app_position_delete', methods: ['DELETE'])]
    public function delete(Position $position, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Ici la gestion du token CSRF est nécessaire, car l'appel de la route est fait en AJAX
        if (!$this->isCsrfTokenValid('delete_position_' . $position->getId(), $request->headers->get('X-CSRF-TOKEN'))) {
            return new JsonResponse(['error' => 'Action non autorisée'], 403);
        }

        $meta = $this->positionManager->getLogMetadata($position->getStatus());

        // Ajout du log de suppression
        $this->logManager->log(
            "Entrypoint #{$position->getEntrypoint()?->getId()} : position #{$position->getRank()} supprimée",
            actionType: $meta['action'],
            origin: LogOrigin::USER,
            context: $meta['context']
        );

        $entityManager->remove($position);
        $entityManager->flush();

        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse(['success' => true, 'id' => $position->getId()]);
        }

        $this->addFlash('success', 'La position a été supprimée avec succès.');

        return $this->redirectToRoute('app_home');
    }

    /**
     * Mise à jour de la position clôturée manuellement
     */
    #[Route('/position/{id}/sell-now', name: 'app_position_sell_now', methods: ['POST'])]
    public function sellNow(Position $position, Request $request, EntityManagerInterface $entityManager): Response
    {
        // 1. On récupère le token depuis le POST (formulaire)
        $submittedToken = $request->request->get('_token');

        // 2. On valide avec l'ID 'close' correspondant au template
        if (!$this->isCsrfTokenValid('close' . $position->getId(), $submittedToken)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Action non autorisée'], 403);
            }
            $this->addFlash('error', 'Le jeton de sécurité est invalide.');

            return $this->redirectToRoute('app_home');
        }

        // 3. Récupération du prix de vente
        $sellLvcPrice = $request->request->get('final_lvc_price');
        $sellCacPrice = $request->request->get('final_cac_price');

        if (!($sellLvcPrice && is_numeric($sellLvcPrice)) || !($sellCacPrice && is_numeric($sellCacPrice))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Les prix de vente CAC et LVC sont requis'], 400);
            }
            $this->addFlash('error', 'Le prix de vente LVC saisi est invalide.');

            return $this->redirectToRoute('app_home');
        }

        // 4. Enregistrement du nouveau prix de vente de la position et mise à jour du statut
        $position->setStatus(PositionStatus::CLOSED);
        $position->setLvcTargetPrice((string) $sellLvcPrice);
        $position->setTargetPrice((string) $sellCacPrice);

        $entityManager->flush();

        $this->logManager->log(
            sprintf(
                "Position #%d clôturée : CAC %s pts / LVC %s €",
                $position->getId(),
                $sellCacPrice,
                $sellLvcPrice
            ),
            actionType: LogAction::SELL,
            origin: LogOrigin::USER,
            context: LogContext::CLOSED
        );

        $this->addFlash('success', 'Position clôturée avec succès.');

        return $this->redirectToRoute('app_home');
    }
}
