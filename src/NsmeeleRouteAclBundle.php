<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle;

use Nsmeele\RouteAclBundle\DependencyInjection\RoleHierarchyVoterPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class NsmeeleRouteAclBundle extends AbstractBundle
{
    protected string $extensionAlias = 'nsmeele_route_acl';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('user_class')
                    ->info('Doctrine entity of the application user; must implement AclUserInterface.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('user_identifier_property')
                    ->info('Property used to find a user from the console (acl:role:assign).')
                    ->defaultValue('email')
                ->end()
                ->scalarNode('protected_path')
                    ->info('Regular expression. Routes whose path matches it and that have no #[AclAction] are only open to roles with full access.')
                    ->defaultValue('^/admin')
                ->end()
                ->scalarNode('layout')
                    ->info('Twig template the admin screens of this bundle extend. It must define a "content" block.')
                    ->defaultValue('@NsmeeleRouteAcl/layout.html.twig')
                ->end()
            ->end();
    }

    /**
     * @param array{user_class: class-string, user_identifier_property: string, protected_path: string, layout: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('nsmeele_route_acl.user_class', $config['user_class'])
            ->set('nsmeele_route_acl.user_identifier_property', $config['user_identifier_property'])
            ->set('nsmeele_route_acl.protected_path', $config['protected_path'])
            ->set('nsmeele_route_acl.layout', $config['layout']);

        $container->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'NsmeeleRouteAclBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Nsmeele\RouteAclBundle\Entity',
                        'alias' => 'NsmeeleRouteAcl',
                    ],
                ],
            ],
        ]);

        // The twig extension is loaded before this bundle's config is processed, so read the raw value here.
        $layout = '@NsmeeleRouteAcl/layout.html.twig';
        foreach ($builder->getExtensionConfig($this->extensionAlias) as $config) {
            if (isset($config['layout']) && \is_string($config['layout'])) {
                $layout = $config['layout'];
            }
        }

        $builder->prependExtensionConfig('twig', [
            'globals' => [
                'nsmeele_route_acl_layout' => $layout,
            ],
        ]);
    }

    public function build(ContainerBuilder $container): void
    {
        // Must run before SecurityBundle's AddSecurityVotersPass (priority 0) collects the voters.
        $container->addCompilerPass(new RoleHierarchyVoterPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }
}
