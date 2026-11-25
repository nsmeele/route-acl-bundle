<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Security;

use Nsmeele\RouteAclBundle\Entity\Role;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine mapping between the application's user and the ACL roles.
 * Call initAclRoles() from the user's constructor.
 */
trait AclUserTrait
{
    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'acl_user_role')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    private Collection $aclRoles;

    protected function initAclRoles(): void
    {
        $this->aclRoles = new ArrayCollection();
    }

    /**
     * @return Collection<int, Role>
     */
    public function getAclRoles(): Collection
    {
        return $this->aclRoles;
    }

    public function addAclRole(Role $role): static
    {
        if (!$this->aclRoles->contains($role)) {
            $this->aclRoles->add($role);
        }

        return $this;
    }

    public function removeAclRole(Role $role): static
    {
        $this->aclRoles->removeElement($role);

        return $this;
    }

    /**
     * The assigned roles as Symfony role names (ROLE_…). Parents are added by the role hierarchy.
     *
     * @return list<string>
     */
    public function getAclSymfonyRoles(): array
    {
        return array_values(array_map(static fn (Role $role): string => $role->getSymfonyRole(), $this->aclRoles->toArray()));
    }
}
