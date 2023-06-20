<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type;

use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldValuesChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Validator\UniqueMultiselectValues;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UpdateSelectFieldType extends AbstractType
{
    public const FIELD  = 'field';
    public const ADD    = 'multiselect_add';
    public const REMOVE = 'multiselect_remove';

    private LeadFieldChoiceLoader $leadFieldChoiceLoader;
    private LeadFieldValuesChoiceLoader $leadFieldValuesChoiceLoader;

    public function __construct(LeadFieldChoiceLoader $leadFieldChoiceLoader, LeadFieldValuesChoiceLoader $leadFieldValuesChoiceLoader)
    {
        $this->leadFieldChoiceLoader       = $leadFieldChoiceLoader;
        $this->leadFieldValuesChoiceLoader = $leadFieldValuesChoiceLoader;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->leadFieldChoiceLoader->setType($options['multiple']);
        $this->leadFieldValuesChoiceLoader->setType($options['multiple']);
        $builder->add(self::FIELD, ChoiceType::class, [
            'label'         => 'mautic.plugin.multiselect_handling.field_action.managed_field',
            'required'      => true,
            'choice_loader' => $this->leadFieldChoiceLoader,
            'constraints'   => [
                new NotBlank(),
            ],
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'multiple' => false,
            'expanded' => false,
        ])->add(self::ADD, ChoiceType::class, [
            'label'         => $options['multiple'] ? 'mautic.plugin.multiselect_handling.field_action.multiselect_add' : 'mautic.plugin.multiselect_handling.field_action.select_add',
            'required'      => false,
            'choice_loader' => $this->leadFieldValuesChoiceLoader,
            'label_attr'    => ['class' => 'control-label'],
            'attr'          => [
                'class' => 'form-control',
            ],
            'multiple' => $options['multiple'],
            'expanded' => false,
        ])->add(self::REMOVE, ChoiceType::class, [
            'label'         => $options['multiple'] ? 'mautic.plugin.multiselect_handling.field_action.multiselect_remove' : 'mautic.plugin.multiselect_handling.field_action.select_remove',
            'required'      => false,
            'choice_loader' => $this->leadFieldValuesChoiceLoader,
            'label_attr'    => ['class' => 'control-label'],
            'attr'          => [
                'class' => 'form-control',
            ],
            'multiple' => true,
            'expanded' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('constraints', [
            new UniqueMultiselectValues(),
        ]);
        $resolver->setRequired('multiple');
        $resolver->setAllowedTypes('multiple', ['bool']);
    }
}
