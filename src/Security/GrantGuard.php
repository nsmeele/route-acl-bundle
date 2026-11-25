<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Security;

use Nsmeele\RouteAclBundle\Entity\Role;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Prevents privilege escalation: someone without full access can only hand out
 * permissions and roles they hold themselves, can only edit roles and users that do not
 * go beyond their own permissions, and cannot change the full access or protected flags.
 */
final readonly class GrantGuard
{
    public function __construct(
        private Security $security,
        private PermissionResolver $resolver,
    ) {
    }

    public function hasFullAccess(): bool
    {
        return $this->resolver->hasFullAccess($this->security->getUser());
    }

    public function canGrantPermission(string $key): bool
    {
        return $this->resolver->isGranted($this->security->getUser(), $key);
    }

    public function canAssignRole(Role $role): bool
    {
        if ($this->hasFullAccess()) {
            return true;
        }

        $effective = $this->resolver->effectiveFor($role);

        if ($effective['fullAccess']) {
            return false;
        }

        foreach (array_keys($effective['permissions']) as $key) {
            if (!$this->canGrantPermission($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the current user may edit $user: every role $user has must be one they could assign.
     * Otherwise they could, for instance, reset an administrator's password and log in as them.
     */
    public function canManageUser(AclUserInterface $user): bool
    {
        foreach ($user->getAclRoles() as $role) {
            if (!$this->canAssignRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Keeps the previous state of locked permissions and takes the submitted state of the rest.
     *
     * @param list<string> $before
     * @param list<string> $submitted
     * @param list<string> $locked
     *
     * @return list<string>
     */
    public function merge(array $before, array $submitted, array $locked): array
    {
        $kept = array_intersect($before, $locked);
        $changed = array_diff($submitted, $locked);

        return array_values(array_unique([...$kept, ...$changed]));
    }
}
