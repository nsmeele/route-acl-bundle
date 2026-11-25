<?php

declare(strict_types=1);

namespace Nsmeele\RouteAclBundle\Form;

use Nsmeele\RouteAclBundle\Entity\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Role>
 */
final class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Role|null $role */
        $role = $options['data'] ?? null;
        $isProtected = $role?->isProtected() ?? false;
        $locked = array_flip($options['locked_permissions']);

        $builder
            ->add('label', TextType::class, [
                'label' => 'Label',
            ])
            ->add('name', TextType::class, [
                'label' => 'Name',
                'help' => 'Lowercase machine name, e.g. "chief_editor". Becomes the Symfony role ROLE_CHIEF_EDITOR.',
                'disabled' => $isProtected,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('parent', EntityType::class, [
                'label' => 'Parent role',
                'help' => 'This role gets every permission of the parent role.',
                'class' => Role::class,
                'choices' => $options['parent_choices'],
                'choice_label' => 'label',
                'placeholder' => 'None',
                'required' => false,
            ])
            ->add('fullAccess', CheckboxType::class, [
                'label' => 'Full access',
                'help' => 'Grants every permission, including ones added later. Only someone with full access can change this.',
                'required' => false,
                'disabled' => $isProtected || !$options['can_set_full_access'],
            ])
            ->add('protected', CheckboxType::class, [
                'label' => 'Protected',
                'help' => 'A protected role cannot be deleted or renamed, and its full access cannot be changed. Only someone with full access can change this.',
                'required' => false,
                'disabled' => !$options['can_set_full_access'],
            ])
            ->add('permissions', ChoiceType::class, [
                'label' => 'Permissions',
                'choices' => array_combine($options['permission_keys'], $options['permission_keys']),
                'choice_attr' => static fn (string $key): array => isset($locked[$key]) ? ['disabled' => 'disabled'] : [],
                'expanded' => true,
                'multiple' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'permission_keys' => [],
            'locked_permissions' => [],
            'parent_choices' => [],
            'can_set_full_access' => false,
        ]);
        $resolver->setAllowedTypes('permission_keys', 'string[]');
        $resolver->setAllowedTypes('locked_permissions', 'string[]');
        $resolver->setAllowedTypes('parent_choices', Role::class.'[]');
        $resolver->setAllowedTypes('can_set_full_access', 'bool');
    }
}
