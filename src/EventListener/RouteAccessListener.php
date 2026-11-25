<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\EventListener;

use Nsmeele\RouteAclBundle\Security\PermissionResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Checks every route against the ACL, right after the firewall. Controllers need no isGranted().
 * Anonymous visitors are sent to the login page by the firewall; logged-in users get a 403.
 */
final readonly class RouteAccessListener
{
    public function __construct(
        private PermissionResolver $resolver,
        private Security $security,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');

        if (!\is_string($route) || $this->resolver->canAccessRoute($this->security->getUser(), $route)) {
            return;
        }

        throw new AccessDeniedException(sprintf('Access to route "%s" denied by the ACL.', $route));
    }
}
