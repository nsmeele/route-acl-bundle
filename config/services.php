<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Nsmeele\RouteAclBundle\Tree\AclTreeProvider;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('Nsmeele\\RouteAclBundle\\', '../src/')
        ->exclude([
            '../src/NsmeeleRouteAclBundle.php',
            '../src/Attribute/',
            '../src/Controller/',
            '../src/DependencyInjection/',
            '../src/Entity/',
            '../src/Section/',
            '../src/Tree/AclTree.php',
        ]);

    // Not autoconfigured: that would tag them "routing.controller", and the application's
    // "routing.controllers" import would then load these routes a second time without the prefix.
    $services->load('Nsmeele\\RouteAclBundle\\Controller\\', '../src/Controller/')
        ->autoconfigure(false)
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber');

    $services->get(AclTreeProvider::class)
        ->arg('$cacheDir', '%kernel.cache_dir%')
        ->arg('$debug', '%kernel.debug%')
        ->arg('$protectedPath', '%nsmeele_route_acl.protected_path%');
};
