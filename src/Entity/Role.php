<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Entity;

use Nsmeele\RouteAclBundle\Repository\RoleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'acl_role')]
#[UniqueEntity(fields: ['name'], message: 'A role with this name already exists.')]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Machine name, e.g. "chief_editor". Becomes the Symfony role ROLE_CHIEF_EDITOR. */
    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/', message: 'Use lowercase letters, digits and underscores, starting with a letter.')]
    #[Assert\NotIdenticalTo(value: 'user', message: '"user" is reserved: every logged-in user already has ROLE_USER.')]
    private ?string $name = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** This role has every permission of its parent (and the parent's parent, and so on). */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Role $parent = null;

    /** Grants everything, including permissions added later. */
    #[ORM\Column]
    private bool $fullAccess = false;

    /** A protected role cannot be deleted or renamed, and its full access cannot be changed. */
    #[ORM\Column(name: 'is_protected')]
    private bool $protected = false;

    /** @var list<string> permission keys ("Class::action") */
    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    public function __construct(?string $name = null, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label;
    }

    public static function toSymfonyRole(string $name): string
    {
        return 'ROLE_'.strtoupper($name);
    }

    public function getSymfonyRole(): string
    {
        return self::toSymfonyRole((string) $this->name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getParent(): ?Role
    {
        return $this->parent;
    }

    public function setParent(?Role $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Whether $role is this role or one of its ancestors.
     */
    public function inheritsFrom(Role $role): bool
    {
        $seen = [];
        for ($current = $this; null !== $current && !isset($seen[spl_object_id($current)]); $current = $current->parent) {
            if ($current === $role) {
                return true;
            }
            $seen[spl_object_id($current)] = true;
        }

        return false;
    }

    public function hasFullAccess(): bool
    {
        return $this->fullAccess;
    }

    public function setFullAccess(bool $fullAccess): static
    {
        $this->fullAccess = $fullAccess;

        return $this;
    }

    public function isProtected(): bool
    {
        return $this->protected;
    }

    public function setProtected(bool $protected): static
    {
        $this->protected = $protected;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param iterable<string> $permissions
     */
    public function setPermissions(iterable $permissions): static
    {
        $unique = array_values(array_unique([...$permissions]));
        sort($unique);
        $this->permissions = $unique;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->label;
    }
}
