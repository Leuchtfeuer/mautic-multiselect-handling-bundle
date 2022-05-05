<?php

return [
    'name'        => 'Multiselect handle',
    'description' => 'Provides custom actions to manage multiselect fields.',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer',
    'services'    => [
        'other' => [
            'mautic.plugin.contact_segments.lead_field_choice_loader' => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\Form\Loader\LeadFieldChoiceLoader::class,
                'arguments' => [
                    'mautic.lead.repository.field',
                ],
            ],
            'mautic.plugin.contact_segments.lead_field_values_choice_loader' => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\Form\Loader\LeadFieldValuesChoiceLoader::class,
                'arguments' => [
                    'mautic.lead.repository.field',
                ],
            ],
        ],
        'events' => [
            \MauticPlugin\MauticContactSegmentsBundle\EventListener\FormSubscriber::class => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\EventListener\FormSubscriber::class,
            ],
            \MauticPlugin\MauticContactSegmentsBundle\EventListener\FormAction::class => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\EventListener\FormAction::class,
                'arguments' => [
                    'mautic.plugin.contact_segments.lead_field_choice_loader',
                    'translator',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.list',
                ],
            ],
            \MauticPlugin\MauticContactSegmentsBundle\EventListener\ActionSubscriber::class => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\EventListener\ActionSubscriber::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                ],
            ],
            \MauticPlugin\MauticContactSegmentsBundle\EventListener\CampaignSubscriber::class => [
                'class' => \MauticPlugin\MauticContactSegmentsBundle\EventListener\CampaignSubscriber::class,
            ],
        ],
        'forms' => [
            'mautic.plugin.contact_segments.action_settings_type' => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\Form\Type\SettingsType::class,
                'arguments' => [
                    'mautic.plugin.contact_segments.lead_field_choice_loader',
                ],
            ],
            'mautic.plugin.contact_segments.update_multiselect_field_type' => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\Form\Type\UpdateMultiselectFieldType::class,
                'arguments' => [
                    'mautic.plugin.contact_segments.lead_field_choice_loader',
                    'mautic.plugin.contact_segments.lead_field_values_choice_loader',
                ],
            ],
        ],
    ],
];
