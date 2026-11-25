<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Security;

use Nsmeele\RouteAclBundle\Entity\Role;
use Nsmeele\RouteAclBundle\Repository\RoleRepository;
use Nsmeele\RouteAclBundle\Tree\AclTreeProvider;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Works out what a set of Symfony roles may do: every role reachable through the role hierarchy
 * (database parents and security.yaml) contributes its ticked permissions.
 */
final class PermissionResolver implements ResetInterface
{
    /** @var array<string, array{fullAccess: bool, permissions: array<string, list<string>>}> */
    private array $cache = [];
    /** @var array<string, Role>|null */
    private ?array $roles = null;

    public function __construct(
        private readonly AclTreeProvider $treeProvider,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly RoleRepository $roleRepository,
    ) {
    }

    public function hasFullAccess(?UserInterface $user): bool
    {
        return null !== $user && $this->resolve($user->getRoles())['fullAccess'];
    }

    public function isGranted(?UserInterface $user, string $permission): bool
    {
        if (null === $user || !$this->treeProvider->getTree()->hasPermission($permission)) {
            return false;
        }

        $resolved = $this->resolve($user->getRoles());

        return $resolved['fullAccess'] || isset($resolved['permissions'][$permission]);
    }

    /**
     * Mapped routes need their permission; protected routes without a mapping need full access;
     * every other route is not managed by the ACL.
     */
    public function canAccessRoute(?UserInterface $user, string $route): bool
    {
        $tree = $this->treeProvider->getTree();
        $permission = $tree->permissionForRoute($route);

        if (null !== $permission) {
            return $this->isGranted($user, $permission);
        }

        if ($tree->isUnmapped($route)) {
            return $this->hasFullAccess($user);
        }

        return true;
    }

    /**
     * @return list<string> known permission keys the user has
     */
    public function getPermissions(?UserInterface $user): array
    {
        if (null === $user) {
            return [];
        }

        $keys = $this->treeProvider->getTree()->permissionKeys();
        $resolved = $this->resolve($user->getRoles());

        return $resolved['fullAccess']
            ? $keys
            : array_values(array_filter($keys, static fn (string $key): bool => isset($resolved['permissions'][$key])));
    }

    /**
     * What a role gets from its parents: permission key => labels of the roles it comes from.
     *
     * @return array{fullAccess: bool, permissions: array<string, list<string>>}
     */
    public function inheritedBy(Role $role): array
    {
        if (null === $role->getId()) {
            return null === $role->getParent()
                ? ['fullAccess' => false, 'permissions' => []]
                : $this->resolve([$role->getParent()->getSymfonyRole()]);
        }

        return $this->resolve([$role->getSymfonyRole()], exclude: $role->getSymfonyRole());
    }

    /**
     * Everything a role grants, own and inherited.
     *
     * @return array{fullAccess: bool, permissions: array<string, list<string>>}
     */
    public function effectiveFor(Role $role): array
    {
        if (null === $role->getId()) {
            $inherited = $this->inheritedBy($role);
            foreach ($role->getPermissions() as $key) {
                $inherited['permissions'][$key][] = (string) $role->getLabel();
            }
            $inherited['fullAccess'] = $inherited['fullAccess'] || $role->hasFullAccess();

            return $inherited;
        }

        return $this->resolve([$role->getSymfonyRole()]);
    }

    public function reset(): void
    {
        $this->cache = [];
        $this->roles = null;
    }

    /**
     * @param list<string> $symfonyRoles
     *
     * @return array{fullAccess: bool, permissions: array<string, list<string>>}
     */
    private function resolve(array $symfonyRoles, ?string $exclude = null): array
    {
        sort($symfonyRoles);
        $cacheKey = implode(',', $symfonyRoles).'|'.$exclude;

        if (!isset($this->cache[$cacheKey])) {
            $this->roles ??= $this->roleRepository->findAllBySymfonyRole();
            $fullAccess = false;
            $permissions = [];

            foreach ($this->roleHierarchy->getReachableRoleNames($symfonyRoles) as $name) {
                $role = $this->roles[$name] ?? null;
                if (null === $role || $name === $exclude) {
                    continue;
                }
                $fullAccess = $fullAccess || $role->hasFullAccess();
                foreach ($role->getPermissions() as $key) {
                    $permissions[$key][] = (string) $role->getLabel();
                }
            }

            $this->cache[$cacheKey] = ['fullAccess' => $fullAccess, 'permissions' => $permissions];
        }

        return $this->cache[$cacheKey];
    }
}
