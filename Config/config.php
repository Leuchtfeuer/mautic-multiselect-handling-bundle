<?php

return [
    'name'        => 'Multiselect Handling by Leuchtfeuer',
    'description' => 'Provides custom actions to manage multiselect fields.',
    'version'     => '2.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'other' => [
            'mautic.plugin.multiselect_handling.lead_field_choice_loader' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader::class,
                'arguments' => [
                    'mautic.lead.repository.field',
                ],
            ],
            'mautic.plugin.multiselect_handling.lead_field_values_choice_loader' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldValuesChoiceLoader::class,
                'arguments' => [
                    'mautic.lead.repository.field',
                ],
            ],
            'mautic.plugin.leuchtfeuermultiselecthandling.model.segments' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel::class,
                'arguments' => [
                    'mautic.lead.model.list',
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                ],
            ],
            'mautic.leuchtfeuermultiselecthandling.config' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
        'events' => [
            MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormSubscriber::class => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormSubscriber::class,
                'arguments' => [
                    'mautic.leuchtfeuermultiselecthandling.config',
                ],
            ],
            MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\CampaignSubscriber::class => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    'mautic.leuchtfeuermultiselecthandling.config',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.leuchtfeuermultiselecthandling' => [
                'class' => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\LeuchtfeuerMultiselectHandlingIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'leuchtfeuermultiselecthandling.integration.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
        ],
        'forms' => [
            'mautic.plugin.multiselect_handling.action_settings_type' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType::class,
                'arguments' => [
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                ],
            ],
            'mautic.plugin.multiselect_handling.update_multiselect_field_type' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldType::class,
                'arguments' => [
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                    'mautic.plugin.multiselect_handling.lead_field_values_choice_loader',
                ],
            ],
        ],
    ],
];
