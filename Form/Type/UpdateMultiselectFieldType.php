<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\Form\Type;

use MauticPlugin\MauticContactSegmentsBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\MauticContactSegmentsBundle\Form\Loader\LeadFieldValuesChoiceLoader;
use MauticPlugin\MauticContactSegmentsBundle\Validator\UniqueMultiselectValues;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UpdateMultiselectFieldType extends AbstractType
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
        $builder->add(self::FIELD, ChoiceType::class, [
            'label'         => 'mautic.plugin.contact_segments_manage.action.managed_field',
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
            'label'         => 'mautic.plugin.contact_segments_manage.action.multiselect_add',
            'required'      => false,
            'choice_loader' => $this->leadFieldValuesChoiceLoader,
            'label_attr'    => ['class' => 'control-label'],
            'attr'          => [
                'class' => 'form-control',
            ],
            'multiple' => true,
            'expanded' => false,
        ])->add(self::REMOVE, ChoiceType::class, [
            'label'         => 'mautic.plugin.contact_segments_manage.action.multiselect_remove',
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
    }
}
