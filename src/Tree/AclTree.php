<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Tree;

/**
 * The permission tree, built from #[AclNode] and #[AclAction] attributes on the routed controllers.
 *
 * @phpstan-type Node array{label: string, parent: ?string, children: list<string>, actions: list<string>}
 * @phpstan-type Permission array{node: string, label: string, routes: list<string>}
 */
final readonly class AclTree
{
    /**
     * @param array<string, Node>       $nodes          keyed by class name
     * @param list<string>              $roots          class names of the top-level nodes
     * @param array<string, Permission> $permissions    keyed by permission key
     * @param array<string, string>     $routes         route name => permission key
     * @param list<string>              $unmappedRoutes protected routes without #[AclAction]
     */
    public function __construct(
        public array $nodes,
        public array $roots,
        public array $permissions,
        public array $routes,
        public array $unmappedRoutes,
    ) {
    }

    public static function permissionKey(string $class, string $actionKey): string
    {
        return $class.'::'.$actionKey;
    }

    public function hasPermission(string $key): bool
    {
        return isset($this->permissions[$key]);
    }

    public function permissionForRoute(string $route): ?string
    {
        return $this->routes[$route] ?? null;
    }

    public function isUnmapped(string $route): bool
    {
        return \in_array($route, $this->unmappedRoutes, true);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return array_keys($this->permissions);
    }

    /**
     * Human-readable path, e.g. "Finance › Invoices › Read".
     */
    public function describe(string $key): string
    {
        $permission = $this->permissions[$key] ?? null;

        if (null === $permission) {
            return $key;
        }

        $labels = [$permission['label']];
        $class = $permission['node'];
        while (null !== $class) {
            array_unshift($labels, $this->nodes[$class]['label']);
            $class = $this->nodes[$class]['parent'];
        }

        return implode(' › ', $labels);
    }
}
