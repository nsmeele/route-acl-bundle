# nsmeele/route-acl-bundle

Route-based access control for Symfony: a permission tree built from controller
attributes, database roles, and parent roles merged into `security.role_hierarchy`.

Requires PHP 8.2+, Symfony 7.4 LTS or 8.x, Doctrine ORM 3 (DoctrineBundle 2.13+ or 3.4+) and Twig 3.21+.

## Installation

```bash
composer config repositories.route-acl-bundle vcs https://github.com/nsmeele/route-acl-bundle
composer require nsmeele/route-acl-bundle:dev-main
```

```php
// config/bundles.php (if Flex did not add it)
Nsmeele\RouteAclBundle\NsmeeleRouteAclBundle::class => ['all' => true],
```

```yaml
# config/packages/nsmeele_route_acl.yaml
nsmeele_route_acl:
    user_class: App\Entity\User        # required
    user_identifier_property: email    # default: email
    protected_path: '^/admin'          # default: ^/admin
    layout: admin/layout.html.twig     # default: @NsmeeleRouteAcl/layout.html.twig, needs a "content" block
```

```yaml
# config/routes/nsmeele_route_acl.yaml
nsmeele_route_acl:
    resource: '@NsmeeleRouteAclBundle/config/routes.php'
    prefix: /admin/acl
```

```php
// src/Entity/User.php
use Nsmeele\RouteAclBundle\Security\AclUserInterface;
use Nsmeele\RouteAclBundle\Security\AclUserTrait;

class User implements UserInterface, PasswordAuthenticatedUserInterface, AclUserInterface, EquatableInterface
{
    use AclUserTrait;

    public function __construct()
    {
        $this->initAclRoles();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER', ...$this->getAclSymfonyRoles()];
    }

    // Leave roles out, so role changes apply without logging the user out.
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getId() === $this->getId()
            && $user->getUserIdentifier() === $this->getUserIdentifier()
            && $user->getPassword() === $this->getPassword();
    }

    // Symfony 7.x only: UserInterface still requires it. Remove it on Symfony 8.
    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
```

```bash
bin/console make:migration
bin/console doctrine:migrations:migrate
bin/console acl:role:create administrator Administrator --full-access --protected
bin/console acl:role:assign admin@example.com administrator
```

Tables: `acl_role`, `acl_user_role`.

## Usage

```php
use Nsmeele\RouteAclBundle\Attribute\AclAction;
use Nsmeele\RouteAclBundle\Attribute\AclNode;

#[AclNode('Finance')]
final class FinanceSection {}

#[Route('/admin/finance/invoices', name: 'admin_invoice_')]
#[AclNode('Invoices', parent: FinanceSection::class)]
final class InvoiceController extends AbstractController
{
    #[Route('', name: 'index')]
    #[Route('/{id}', name: 'show')]
    #[AclAction('Read')]
    public function index(): Response {}

    #[Route('/{id}/edit', name: 'edit')]
    #[AclAction('Write')]
    public function edit(): Response {}
}
```

- `#[AclNode(label, parent)]`: a node in the tree. `parent` is another class with `#[AclNode]`. No depth limit.
- `#[AclAction(label, key)]`: a permission on a controller method. `key` defaults to the snake-cased label. A role stores it as `App\Controller\InvoiceController::write`.
- Each request is checked against its route. Controllers don't call `isGranted()`.
- Routes matching `protected_path` without `#[AclAction]` require full access.
- An invalid tree (unknown parent, parent without `#[AclNode]`, loop, action without node) fails `cache:clear`.

Checking access in templates and code:

```twig
{% if can_access('admin_invoice_edit') %}…{% endif %}
```

```php
$this->isGranted('ACL_ROUTE', 'admin_invoice_edit');
```

## Roles

- Role `chief_editor` is Symfony role `ROLE_CHIEF_EDITOR`.
- Parent roles are merged into `security.role_hierarchy` and apply to `is_granted()`, `access_control` and the ACL. `role_hierarchy` in `security.yaml` still applies.
- A role inherits its parent's permissions and can only add to them.
- **Full access**: every permission, including future ones.
- **Protected**: cannot be deleted or renamed, full access is fixed.
- Only users with full access can set these flags.
- Users without full access cannot grant permissions or roles beyond their own, or edit roles and users that exceed them. Use `GrantGuard::canManageUser()` in your own user screens.
- Role changes apply on the next request.

## Commands

| Command | |
| --- | --- |
| `acl:role:create <name> <label> [--full-access] [--protected]` | Create a role |
| `acl:role:assign <user> <role>` | Assign a role by user identifier and role name |
| `acl:tree` | Print the tree, the routes per action and the unmapped protected routes |

## Admin screens

| Route | Tree node | Actions |
| --- | --- | --- |
| `nsmeele_route_acl_role_*` | Access control › Roles | Read, Write, Delete |
| `nsmeele_route_acl_coverage_index` | Access control › Route coverage | View |

## License

MIT, see [LICENSE](LICENSE).
