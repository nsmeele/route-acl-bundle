<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Command;

use Nsmeele\RouteAclBundle\Tree\AclTree;
use Nsmeele\RouteAclBundle\Tree\AclTreeProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'acl:tree',
    description: 'Shows the permission tree, the routes behind each action and the protected routes without a mapping',
)]
final readonly class TreeCommand
{
    public function __construct(
        private AclTreeProvider $treeProvider,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tree = $this->treeProvider->getTree();

        $io->title('Permission tree');
        if ([] === $tree->roots) {
            $io->text('No #[AclNode] controllers found.');
        }
        foreach ($tree->roots as $class) {
            $this->printNode($io, $tree, $class, 0);
        }

        $io->section('Protected routes without #[AclAction] (full access only)');
        if ([] === $tree->unmappedRoutes) {
            $io->text('None.');
        } else {
            $io->listing($tree->unmappedRoutes);
        }

        return Command::SUCCESS;
    }

    private function printNode(SymfonyStyle $io, AclTree $tree, string $class, int $depth): void
    {
        $indent = str_repeat('    ', $depth);
        $node = $tree->nodes[$class];

        $io->writeln(sprintf('%s<info>%s</info>  <comment>%s</comment>', $indent, $node['label'], $class));
        foreach ($node['actions'] as $key) {
            $permission = $tree->permissions[$key];
            $io->writeln(sprintf('%s    ☐ %s  <comment>%s</comment>', $indent, $permission['label'], implode(', ', $permission['routes'])));
        }
        foreach ($node['children'] as $child) {
            $this->printNode($io, $tree, $child, $depth + 1);
        }
    }
}
