<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class SettingsType extends AbstractType
{
    public const FIELD    = 'field';
    public const CHECKBOX = 'create_missing';

    private LeadFieldChoiceLoader $choiceLoader;

    public function __construct(LeadFieldChoiceLoader $choiceLoader)
    {
        $this->choiceLoader = $choiceLoader;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(self::FIELD, ChoiceType::class, [
            'label'         => 'mautic.plugin.multiselect_handling.actions.contact_segments_manage',
            'required'      => true,
            'choice_loader' => $this->choiceLoader,
            'constraints'   => [
                new NotBlank(),
            ],
        ])->add(self::CHECKBOX, YesNoButtonGroupType::class, [
            'label' => 'mautic.plugin.multiselect_handling.field.create_segments_checkbox',
            'data'  => false,
            'attr'  => [
                'tooltip' => 'mautic.plugin.multiselect_handling.field.create_segments_checkbox_desc',
            ],
        ]);
    }
}
