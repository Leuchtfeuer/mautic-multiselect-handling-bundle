<?php

return [
    'name'        => 'Multiselect Handling by Leuchtfeuer',
    'description' => 'Provides custom actions to manage multiselect fields.',
    'version'     => '3.1.0',
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
            'mautic.plugin.multiselect_handling.model.segments' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel::class,
                'arguments' => [
                    'mautic.lead.model.list',
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                ],
            ],
            'mautic.leuchtfeuermultiselect.config' => [
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
                    'mautic.leuchtfeuermultiselect.config',
                ],
            ],
            MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormAction::class => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\FormAction::class,
                'arguments' => [
                    'mautic.leuchtfeuermultiselect.config',
                    'mautic.plugin.multiselect_handling.lead_field_choice_loader',
                    'translator',
                    'mautic.lead.model.lead',
                    'mautic.plugin.multiselect_handling.model.segments',
                ],
            ],
            MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\ActionSubscriber::class => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\ActionSubscriber::class,
                'arguments' => [
                    'mautic.leuchtfeuermultiselect.config',
                    'mautic.lead.model.lead',
                    'mautic.plugin.multiselect_handling.model.segments',
                ],
            ],
            MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\CampaignSubscriber::class => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    'mautic.leuchtfeuermultiselect.config',
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
        'integrations' => [
            'mautic.integration.leuchtfeuermultiselect' => [
                'class' => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\LeuchtfeuerMultiselectIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'leuchtfeuermultiselect.integration.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
        ],
    ],
];
