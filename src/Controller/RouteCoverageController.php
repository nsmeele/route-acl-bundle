<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Controller;

use Nsmeele\RouteAclBundle\Attribute\AclAction;
use Nsmeele\RouteAclBundle\Attribute\AclNode;
use Nsmeele\RouteAclBundle\Repository\RoleRepository;
use Nsmeele\RouteAclBundle\Section\AccessControlSection;
use Nsmeele\RouteAclBundle\Tree\AclTreeProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/route-coverage', name: 'nsmeele_route_acl_coverage_')]
#[AclNode('Route coverage', parent: AccessControlSection::class)]
final class RouteCoverageController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[AclAction('View')]
    public function index(
        AclTreeProvider $treeProvider,
        RoleRepository $roles,
        #[Autowire(param: 'nsmeele_route_acl.protected_path')] string $protectedPath,
    ): Response {
        $tree = $treeProvider->getTree();

        $orphaned = [];
        foreach ($roles->findAllOrdered() as $role) {
            foreach ($role->getPermissions() as $key) {
                if (!$tree->hasPermission($key)) {
                    $orphaned[$key][] = $role->getLabel();
                }
            }
        }

        return $this->render('@NsmeeleRouteAcl/coverage/index.html.twig', [
            'tree' => $tree,
            'protected_path' => $protectedPath,
            'orphaned' => $orphaned,
        ]);
    }
}
