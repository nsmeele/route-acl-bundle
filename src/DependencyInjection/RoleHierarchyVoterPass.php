<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;

/**
 * SecurityBundle only uses the role hierarchy when security.yaml defines a role_hierarchy.
 * Parent roles from the database must always count, so make sure the hierarchy voter is used.
 */
final class RoleHierarchyVoterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('security.role_hierarchy') || $container->hasDefinition('security.access.role_hierarchy_voter')) {
            return;
        }

        $container->setDefinition('security.access.role_hierarchy_voter', (new Definition(RoleHierarchyVoter::class))
            ->setArguments([new Reference('security.role_hierarchy')])
            ->addTag('security.voter', ['priority' => 245]));

        $container->removeDefinition('security.access.simple_role_voter');
    }
}
