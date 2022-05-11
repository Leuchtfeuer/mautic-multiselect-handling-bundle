<?php

return [
    'name'        => 'Multiselect handle',
    'description' => 'Provides custom actions to manage multiselect fields.',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer',
    'services'    => [
        'other' => [
            'mautic.plugin.multiselect_handling.lead_field_choice_loader' => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader::class,
                'arguments' => [
                    'mautic.lead.repository.field',
                ],
            ],
            'mautic.plugin.multiselect_handling.lead_field_values_choice_loader' => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldValuesChoiceLoader::class,
                'arguments' => [
                    'mautic.lead.repository.field',
                ],
            ],
        ],
        'events' => [
            \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\FormSubscriber::class => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\FormSubscriber::class,
            ],
            \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\FormAction::class => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\FormAction::class,
                'arguments' => [
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                    'translator',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.list',
                ],
            ],
            \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\ActionSubscriber::class => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\ActionSubscriber::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                ],
            ],
            \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\CampaignSubscriber::class => [
                'class' => \MauticPlugin\MauticMultiselectHandlingBundle\EventListener\CampaignSubscriber::class,
            ],
        ],
        'forms' => [
            'mautic.plugin.multiselect_handling.action_settings_type' => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType::class,
                'arguments' => [
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                ],
            ],
            'mautic.plugin.multiselect_handling.update_multiselect_field_type' => [
                'class'     => \MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\UpdateMultiselectFieldType::class,
                'arguments' => [
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                    'mautic.plugin.multiselect_handling.lead_field_values_choice_loader',
                ],
            ],
        ],
    ],
];
