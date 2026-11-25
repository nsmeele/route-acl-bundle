<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Repository;

use Nsmeele\RouteAclBundle\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
final class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @return list<Role>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.parent', 'p')
            ->addSelect('p')
            ->orderBy('r.protected', 'DESC')
            ->addOrderBy('r.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByName(string $name): ?Role
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return array<string, Role> keyed by Symfony role name (ROLE_…)
     */
    public function findAllBySymfonyRole(): array
    {
        $roles = [];
        foreach ($this->findAllOrdered() as $role) {
            $roles[$role->getSymfonyRole()] = $role;
        }

        return $roles;
    }
}
