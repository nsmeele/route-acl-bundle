<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Attribute;

/**
 * A checkbox in the permission tree. Put it on controller methods; every route of the method needs it.
 * Methods with the same action share one checkbox, e.g. index() and show() both #[AclAction('Read')].
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class AclAction
{
    /** Stored per role together with the class name. Derived from the label unless given. */
    public readonly string $key;

    public function __construct(
        public string $label,
        ?string $key = null,
    ) {
        $key ??= trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');

        if ('' === $key) {
            throw new \InvalidArgumentException(sprintf('Cannot derive a key from action label "%s"; pass one with key:.', $label));
        }

        $this->key = $key;
    }
}
