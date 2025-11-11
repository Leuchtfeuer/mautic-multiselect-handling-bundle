<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type;

use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldValuesChoiceLoader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class UpdateSelectFieldActionType extends AbstractType
{
    public const FIELD_MANAGED_FIELD   = 'field';
    public const FIELD_SELECT_VALUE    = 'select_value';

    public function __construct(private LeadFieldChoiceLoader $leadFieldChoiceLoader, private LeadFieldValuesChoiceLoader $leadFieldValuesChoiceLoader)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->leadFieldChoiceLoader->setType(false);
        $this->leadFieldValuesChoiceLoader->setType(false);
        $fieldList = $this->leadFieldChoiceLoader->loadChoiceList()->getOriginalKeys();
        if (null == $options['data'] && !empty($fieldList)) {
            $flipFieldList = array_flip($fieldList);
            $firstField    = reset($flipFieldList);
            $this->leadFieldValuesChoiceLoader->setDefaultFieldId((int) $firstField);
        } elseif (!empty($options['data']) && isset($options['data'][self::FIELD_MANAGED_FIELD]) && $options['data'][self::FIELD_MANAGED_FIELD] > 0) {
            $this->leadFieldValuesChoiceLoader->setDefaultFieldId((int) $options['data'][self::FIELD_MANAGED_FIELD]);
        }
        $builder->add(self::FIELD_MANAGED_FIELD, ChoiceType::class, [
            'label'         => 'mautic.plugin.multiselect_handling.field_action.managed_field',
            'required'      => true,
            'choice_loader' => $this->leadFieldChoiceLoader,
            'constraints'   => [
                new NotBlank(),
            ],
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'    => 'form-control',
                'onchange' => 'Mautic.getOptionsFromField(this)',
            ],
            'multiple' => false,
            'expanded' => false,
        ])->add(self::FIELD_SELECT_VALUE, ChoiceType::class, [
            'label'         => 'mautic.plugin.multiselect_handling.field_action.select_add',
            'required'      => true,
            'choice_loader' => $this->leadFieldValuesChoiceLoader,
            'label_attr'    => ['class' => 'control-label'],
            'attr'          => [
                'class' => 'form-control',
            ],
            'multiple' => false,
            'expanded' => false,
        ]);
    }
}
