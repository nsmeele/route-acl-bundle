<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Command;

use Nsmeele\RouteAclBundle\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'acl:role:create',
    description: 'Creates a role. Use it for the first role, before anyone can open the admin.',
)]
final readonly class RoleCreateCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Machine name, e.g. "administrator"; becomes ROLE_ADMINISTRATOR')]
        string $name,
        #[Argument(description: 'Label shown in the admin, e.g. "Administrator"')]
        string $label,
        #[Option(name: 'full-access', description: 'Give the role every permission, including ones added later')]
        bool $fullAccess = false,
        #[Option(name: 'protected', description: 'Make the role impossible to delete or rename in the admin')]
        bool $protected = false,
    ): int {
        $role = (new Role($name, $label))
            ->setFullAccess($fullAccess)
            ->setProtected($protected);

        $violations = $this->validator->validate($role);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error(sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage()));
            }

            return Command::FAILURE;
        }

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        $io->success(sprintf('Role "%s" (%s) created%s.', $label, $role->getSymfonyRole(), $fullAccess ? ' with full access' : ''));

        return Command::SUCCESS;
    }
}
