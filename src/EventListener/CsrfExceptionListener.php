<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @noinspection PhpUnused
 */
class CsrfExceptionListener
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Si l'erreur est spécifiquement un problème de jeton CSRF
        if ($exception instanceof InvalidCsrfTokenException || str_contains($exception->getMessage(), 'CSRF')) {

            // Récupération de la session depuis la requête
            $request = $event->getRequest();
            $session = $request->getSession();

            // On affiche un message flash pour avertir l'utilisateur.
            $session->getFlashBag()->add(
                'error',
                'Votre session a expiré suite à un problème technique. Veuillez vous reconnecter.'
            );
            $response = new RedirectResponse($this->router->generate('app_login'));
            $event->setResponse($response);
        }
    }
}
