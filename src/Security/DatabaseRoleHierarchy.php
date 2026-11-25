<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Security;

use Nsmeele\RouteAclBundle\Repository\RoleRepository;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the parent roles set in the admin to Symfony's role hierarchy, on top of
 * the role_hierarchy in security.yaml. is_granted('ROLE_…'), access_control and the ACL all use it.
 */
#[AsDecorator('security.role_hierarchy')]
final class DatabaseRoleHierarchy implements RoleHierarchyInterface, ResetInterface
{
    /** @var array<string, string>|null ROLE_CHILD => ROLE_PARENT */
    private ?array $parents = null;

    public function __construct(
        #[AutowireDecorated]
        private readonly RoleHierarchyInterface $inner,
        private readonly RoleRepository $roles,
    ) {
    }

    public function getReachableRoleNames(array $roles): array
    {
        $parents = $this->parents();
        $reachable = [];
        $queue = $roles;

        while ([] !== $queue) {
            $next = [];
            foreach ($this->inner->getReachableRoleNames($queue) as $role) {
                if (isset($reachable[$role])) {
                    continue;
                }
                $reachable[$role] = true;

                $parent = $parents[$role] ?? null;
                if (null !== $parent && !isset($reachable[$parent])) {
                    $next[] = $parent;
                }
            }
            $queue = $next;
        }

        return array_keys($reachable);
    }

    public function reset(): void
    {
        $this->parents = null;
    }

    /**
     * @return array<string, string>
     */
    private function parents(): array
    {
        if (null === $this->parents) {
            $this->parents = [];
            foreach ($this->roles->findAllOrdered() as $role) {
                if (null !== $role->getParent()) {
                    $this->parents[$role->getSymfonyRole()] = $role->getParent()->getSymfonyRole();
                }
            }
        }

        return $this->parents;
    }
}
