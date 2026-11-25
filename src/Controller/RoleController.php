<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Controller;

use Nsmeele\RouteAclBundle\Attribute\AclAction;
use Nsmeele\RouteAclBundle\Attribute\AclNode;
use Nsmeele\RouteAclBundle\Entity\Role;
use Nsmeele\RouteAclBundle\Form\RoleType;
use Nsmeele\RouteAclBundle\Repository\RoleRepository;
use Nsmeele\RouteAclBundle\Section\AccessControlSection;
use Nsmeele\RouteAclBundle\Security\GrantGuard;
use Nsmeele\RouteAclBundle\Security\PermissionResolver;
use Nsmeele\RouteAclBundle\Tree\AclTreeProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/roles', name: 'nsmeele_route_acl_role_')]
#[AclNode('Roles', parent: AccessControlSection::class)]
final class RoleController extends AbstractController
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly AclTreeProvider $treeProvider,
        private readonly PermissionResolver $resolver,
        private readonly GrantGuard $guard,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[AclAction('Read')]
    public function index(): Response
    {
        $tree = $this->treeProvider->getTree();
        $roles = $this->roles->findAllOrdered();

        return $this->render('@NsmeeleRouteAcl/role/index.html.twig', [
            'roles' => $roles,
            'effective' => array_combine(
                array_map(static fn (Role $role): int => (int) $role->getId(), $roles),
                array_map($this->resolver->effectiveFor(...), $roles),
            ),
            'permission_count' => \count($tree->permissions),
            'manageable' => array_map(static fn (Role $role): int => (int) $role->getId(), array_values(array_filter($roles, $this->guard->canAssignRole(...)))),
            'unmapped_count' => \count($tree->unmappedRoutes),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[AclAction('Write')]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new Role());
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[AclAction('Write')]
    public function edit(Request $request, #[MapEntity(id: 'id')] Role $role): Response
    {
        if (!$this->guard->canAssignRole($role)) {
            $this->addFlash('error', sprintf('You cannot edit "%s": it has permissions you do not have yourself.', $role->getLabel()));

            return $this->redirectToRoute('nsmeele_route_acl_role_index');
        }

        return $this->handleForm($request, $role);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[AclAction('Delete')]
    public function delete(Request $request, #[MapEntity(id: 'id')] Role $role): Response
    {
        if (!$this->isCsrfTokenValid('delete-role-'.$role->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');
        } elseif ($role->isProtected()) {
            $this->addFlash('error', sprintf('"%s" is a protected role and cannot be deleted.', $role->getLabel()));
        } elseif (!$this->guard->canAssignRole($role)) {
            $this->addFlash('error', sprintf('You cannot delete "%s": it has permissions you do not have yourself.', $role->getLabel()));
        } else {
            $this->entityManager->remove($role);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Role "%s" deleted. Roles that had it as parent no longer inherit from it.', $role->getLabel()));
        }

        return $this->redirectToRoute('nsmeele_route_acl_role_index');
    }

    private function handleForm(Request $request, Role $role): Response
    {
        $tree = $this->treeProvider->getTree();
        $before = $role->getPermissions();
        $inherited = $this->resolver->inheritedBy($role);
        $hasFullAccess = $this->guard->hasFullAccess();

        // Locked: inherited from a parent, or not the editor's to hand out. Their state is kept as it was.
        $locked = array_keys($inherited['permissions']);
        if (!$hasFullAccess) {
            $locked = [...$locked, ...array_filter($tree->permissionKeys(), fn (string $key): bool => !$this->guard->canGrantPermission($key))];
        }
        if ($role->hasFullAccess() || $inherited['fullAccess']) {
            $locked = $tree->permissionKeys();
        }
        $locked = array_values(array_unique($locked));

        $form = $this->createRoleForm($role, $locked);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $role->getParent() && $role->getParent()->inheritsFrom($role)) {
                $this->addFlash('error', 'A role cannot inherit from itself, directly or through another role.');
            } else {
                // Keys that are no longer in the tree are dropped here: they are neither locked nor submitted.
                $role->setPermissions($this->guard->merge($before, $role->getPermissions(), $locked));
                $this->entityManager->persist($role);
                $this->entityManager->flush();
                $this->addFlash('success', sprintf('Role "%s" saved.', $role->getLabel()));

                return $this->redirectToRoute('nsmeele_route_acl_role_index');
            }
        }

        $orphaned = array_values(array_filter($before, static fn (string $key): bool => !$tree->hasPermission($key)));

        return $this->render('@NsmeeleRouteAcl/role/form.html.twig', [
            'role' => $role,
            'form' => $form,
            'tree' => $tree,
            'inherited' => $inherited,
            'locked' => $locked,
            'orphaned' => $orphaned,
        ]);
    }

    /**
     * @param list<string> $locked
     *
     * @return FormInterface<Role>
     */
    private function createRoleForm(Role $role, array $locked): FormInterface
    {
        // A parent cannot be the role itself or a role below it, and must be one the editor could assign.
        $parentChoices = array_values(array_filter(
            $this->roles->findAllOrdered(),
            fn (Role $candidate): bool => !$candidate->inheritsFrom($role) && $this->guard->canAssignRole($candidate),
        ));
        if (null !== $role->getParent() && !\in_array($role->getParent(), $parentChoices, true)) {
            $parentChoices[] = $role->getParent();
        }

        return $this->createForm(RoleType::class, $role, [
            'permission_keys' => $this->treeProvider->getTree()->permissionKeys(),
            'locked_permissions' => $locked,
            'parent_choices' => $parentChoices,
            'can_set_full_access' => $this->guard->hasFullAccess(),
        ]);
    }
}
