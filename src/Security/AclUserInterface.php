<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Security;

use Nsmeele\RouteAclBundle\Entity\Role;

/**
 * Implemented by the application's user entity, together with AclUserTrait.
 * getRoles() should include getAclSymfonyRoles(), so the roles also work with is_granted('ROLE_…').
 */
interface AclUserInterface
{
    /**
     * @return iterable<Role>
     */
    public function getAclRoles(): iterable;
}
