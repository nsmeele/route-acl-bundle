<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\EventListener;

use Nsmeele\RouteAclBundle\Security\AclUserInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Makes role changes take effect on the next request, without logging the user out.
 * The token keeps the roles from login time; replace it when the user's current roles differ.
 * (The user entity must implement EquatableInterface without comparing roles, or Symfony logs the user out.)
 */
final readonly class RefreshTokenRolesListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->hasPreviousSession()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$token instanceof PostAuthenticationToken || !$user instanceof AclUserInterface) {
            return;
        }

        $current = $user->getRoles();
        $stored = $token->getRoleNames();
        sort($current);
        sort($stored);

        if ($current !== $stored) {
            $this->tokenStorage->setToken(new PostAuthenticationToken($user, $token->getFirewallName(), $user->getRoles()));
        }
    }
}
