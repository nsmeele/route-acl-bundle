<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Security;

use Nsmeele\RouteAclBundle\Tree\AclTreeProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * isGranted('ACL_ROUTE', 'admin_invoice_edit') checks a route;
 * isGranted('App\Controller\InvoiceController::read') checks a permission key.
 *
 * @extends Voter<string, mixed>
 */
final class AclVoter extends Voter
{
    public const ROUTE = 'ACL_ROUTE';

    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AclTreeProvider $treeProvider,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::ROUTE === $attribute) {
            return \is_string($subject);
        }

        return str_contains($attribute, '::') && $this->treeProvider->getTree()->hasPermission($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $user = $user instanceof UserInterface ? $user : null;

        $granted = self::ROUTE === $attribute
            ? $this->resolver->canAccessRoute($user, $subject)
            : $this->resolver->isGranted($user, $attribute);

        if (!$granted) {
            $vote?->addReason(self::ROUTE === $attribute
                ? sprintf('None of the user\'s roles may open route "%s".', $subject)
                : sprintf('None of the user\'s roles has "%s".', $attribute));
        }

        return $granted;
    }
}
