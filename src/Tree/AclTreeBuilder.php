<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Tree;

use Nsmeele\RouteAclBundle\Attribute\AclAction;
use Nsmeele\RouteAclBundle\Attribute\AclNode;
use Symfony\Component\Routing\RouteCollection;

/**
 * Reads the attributes of every routed controller and builds the tree.
 * Throws AclTreeException on mistakes, so they surface at cache:clear.
 */
final class AclTreeBuilder
{
    /** @var array<string, array{label: string, parent: ?string, children: list<string>, actions: list<string>}> */
    private array $nodes = [];
    /** @var list<string> */
    private array $roots = [];
    /** @var array<string, array{node: string, label: string, routes: list<string>}> */
    private array $permissions = [];
    /** @var array<string, string> */
    private array $routes = [];
    /** @var list<string> */
    private array $unmapped = [];
    /** @var array<string, string> class => file, for cache freshness checks */
    private array $files = [];

    public function build(RouteCollection $collection, string $protectedPath): AclTree
    {
        $this->nodes = $this->roots = $this->permissions = $this->routes = $this->unmapped = $this->files = [];
        $isProtected = static fn (string $path): bool => 1 === preg_match('#'.str_replace('#', '\#', $protectedPath).'#', $path);

        foreach ($collection->all() as $routeName => $route) {
            [$class, $method] = $this->parseController($route->getDefault('_controller'));

            if (null === $class) {
                if ($isProtected($route->getPath())) {
                    $this->unmapped[] = $routeName;
                }
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $node = $this->attribute($reflection, AclNode::class);
            $action = $reflection->hasMethod($method)
                ? $this->attribute($reflection->getMethod($method), AclAction::class)
                : null;

            if (null !== $action && null === $node) {
                throw new AclTreeException(sprintf('%s::%s() has #[AclAction] but %s has no #[AclNode].', $class, $method, $class));
            }

            if (null === $node || null === $action) {
                if ($isProtected($route->getPath())) {
                    $this->unmapped[] = $routeName;
                }
                continue;
            }

            $this->addNode($class, []);
            $this->addRoute($class, $action, $routeName);
        }

        $byLabel = fn (string $a, string $b): int => strnatcasecmp($this->nodes[$a]['label'], $this->nodes[$b]['label']);
        usort($this->roots, $byLabel);
        foreach ($this->nodes as &$node) {
            usort($node['children'], $byLabel);
        }
        unset($node);
        sort($this->unmapped);

        return new AclTree($this->nodes, $this->roots, $this->permissions, $this->routes, $this->unmapped);
    }

    /**
     * @return list<string> files of every class that took part, so the tree is rebuilt when one changes
     */
    public function getFiles(): array
    {
        return array_values(array_unique($this->files));
    }

    /**
     * @param list<string> $chain classes below this one that are being added, to detect loops
     */
    private function addNode(string $class, array $chain): void
    {
        if (isset($this->nodes[$class])) {
            return;
        }

        if (\in_array($class, $chain, true)) {
            $loop = [...\array_slice($chain, (int) array_search($class, $chain, true)), $class];
            throw new AclTreeException(sprintf('#[AclNode] parents form a loop: %s.', implode(' → ', $loop)));
        }

        $child = end($chain) ?: null;

        if (!class_exists($class)) {
            throw new AclTreeException(sprintf('Class %s, the parent of %s, does not exist.', $class, $child));
        }

        $reflection = new \ReflectionClass($class);
        $node = $this->attribute($reflection, AclNode::class);

        if (null === $node) {
            throw new AclTreeException(sprintf('%s is the parent of %s but has no #[AclNode] attribute.', $class, $child));
        }

        $this->files[$class] = (string) $reflection->getFileName();

        if (null !== $node->parent) {
            $this->addNode(ltrim($node->parent, '\\'), [...$chain, $class]);
        }

        $this->nodes[$class] = [
            'label' => $node->label,
            'parent' => null === $node->parent ? null : ltrim($node->parent, '\\'),
            'children' => [],
            'actions' => [],
        ];

        if (null === $node->parent) {
            $this->roots[] = $class;
        } else {
            $this->nodes[ltrim($node->parent, '\\')]['children'][] = $class;
        }
    }

    private function addRoute(string $class, AclAction $action, string $routeName): void
    {
        $key = AclTree::permissionKey($class, $action->key);

        if (!isset($this->permissions[$key])) {
            $this->permissions[$key] = ['node' => $class, 'label' => $action->label, 'routes' => []];
            $this->nodes[$class]['actions'][] = $key;
        } elseif ($this->permissions[$key]['label'] !== $action->label) {
            throw new AclTreeException(sprintf('Action key "%s" on %s is used with two labels: "%s" and "%s".', $action->key, $class, $this->permissions[$key]['label'], $action->label));
        }

        $this->permissions[$key]['routes'][] = $routeName;
        $this->routes[$routeName] = $key;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function parseController(mixed $controller): array
    {
        if (!\is_string($controller) || '' === $controller) {
            return [null, ''];
        }

        [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];

        return class_exists($class) ? [$class, $method] : [null, ''];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return T|null
     */
    private function attribute(\ReflectionClass|\ReflectionMethod $reflection, string $attribute): ?object
    {
        $attributes = $reflection->getAttributes($attribute);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
