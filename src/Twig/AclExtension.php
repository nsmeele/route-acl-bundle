<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Twig;

use Nsmeele\RouteAclBundle\Security\AclVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class AclExtension
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * {% if can_access('admin_invoice_edit') %}<a href="…">Edit</a>{% endif %}
     */
    #[AsTwigFunction('can_access')]
    public function canAccess(string $route): bool
    {
        return $this->authorizationChecker->isGranted(AclVoter::ROUTE, $route);
    }
}
