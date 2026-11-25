<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Tree;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Builds the tree once and keeps it in the cache directory. In debug mode it is rebuilt
 * whenever a route or one of the classes in the tree changes.
 *
 * As a (non-optional) cache warmer, it makes cache:clear fail on mistakes in the tree.
 */
final class AclTreeProvider implements CacheWarmerInterface
{
    private ?AclTree $tree = null;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly AclTreeBuilder $builder,
        private readonly string $cacheDir,
        private readonly bool $debug,
        private readonly string $protectedPath,
    ) {
    }

    public function getTree(): AclTree
    {
        return $this->tree ??= $this->load($this->cacheDir);
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->load($cacheDir);

        return [];
    }

    private function load(string $cacheDir): AclTree
    {
        $cache = new ConfigCache($cacheDir.'/nsmeele_route_acl/tree.php', $this->debug);

        if (!$cache->isFresh()) {
            $collection = $this->router->getRouteCollection();
            $tree = $this->builder->build($collection, $this->protectedPath);

            $resources = $collection->getResources();
            foreach ($this->builder->getFiles() as $file) {
                $resources[] = new FileResource($file);
            }

            $data = [$tree->nodes, $tree->roots, $tree->permissions, $tree->routes, $tree->unmappedRoutes];
            $cache->write('<?php return '.var_export($data, true).';'.\PHP_EOL, $resources);

            return $tree;
        }

        return new AclTree(...require $cache->getPath());
    }
}
