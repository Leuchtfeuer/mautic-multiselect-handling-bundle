<?php

return [
    'name'        => 'Contact segments',
    'description' => 'Provides a custom form action to manage contact segments.',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer',

    'services' => [
        'other' => [
            'mautic.plugin.contact_segments.lead_field_choice_loader' => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\Form\Loader\LeadFieldChoiceLoader::class,
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
        ],
        'forms' => [
            'mautic.plugin.contact_segments.action_settings_type' => [
                'class'     => \MauticPlugin\MauticContactSegmentsBundle\Form\Type\SettingsType::class,
                'arguments' => [
                    'mautic.plugin.contact_segments.lead_field_choice_loader',
                ],
            ],
        ],
    ],
];
