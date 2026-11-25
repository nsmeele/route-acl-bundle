<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Attribute;

/**
 * A node in the permission tree. Put it on a controller, or on an empty class for a heading without screens.
 *
 *     #[AclNode('Finance')]
 *     final class FinanceSection {}
 *
 *     #[AclNode('Invoices', parent: FinanceSection::class)]
 *     final class InvoiceController extends AbstractController {}
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AclNode
{
    /**
     * @param class-string|null $parent another class with #[AclNode]
     */
    public function __construct(
        public string $label,
        public ?string $parent = null,
    ) {
    }
}
