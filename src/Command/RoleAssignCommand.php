<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Command;

use Nsmeele\RouteAclBundle\Repository\RoleRepository;
use Nsmeele\RouteAclBundle\Security\AclUserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'acl:role:assign',
    description: 'Gives a user a role',
)]
final readonly class RoleAssignCommand
{
    /**
     * @param class-string $userClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoleRepository $roles,
        #[Autowire(param: 'nsmeele_route_acl.user_class')]
        private string $userClass,
        #[Autowire(param: 'nsmeele_route_acl.user_identifier_property')]
        private string $identifierProperty,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The user, e.g. their e-mail address')]
        string $user,
        #[Argument(description: 'Machine name of the role, e.g. "administrator"')]
        string $role,
    ): int {
        $account = $this->entityManager->getRepository($this->userClass)->findOneBy([$this->identifierProperty => $user]);

        if (null === $account) {
            $io->error(sprintf('No user found with %s "%s".', $this->identifierProperty, $user));

            return Command::FAILURE;
        }

        if (!$account instanceof AclUserInterface || !method_exists($account, 'addAclRole')) {
            $io->error(sprintf('%s must implement %s and use AclUserTrait.', $this->userClass, AclUserInterface::class));

            return Command::FAILURE;
        }

        $entity = $this->roles->findOneByName($role);

        if (null === $entity) {
            $io->error(sprintf('No role named "%s". Create it with acl:role:create.', $role));

            return Command::FAILURE;
        }

        $account->addAclRole($entity);
        $this->entityManager->flush();

        $io->success(sprintf('"%s" now has the role "%s".', $user, $entity->getLabel()));

        return Command::SUCCESS;
    }
}
