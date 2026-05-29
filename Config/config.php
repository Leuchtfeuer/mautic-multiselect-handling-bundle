<?php

declare(strict_types=1);

return [
    'name'        => 'Multiselect Handling by Leuchtfeuer',
    'description' => 'Provides custom actions to manage multiselect fields.',
    'version'     => '5.0.1',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'integrations' => [
            'mautic.leuchtfeuermultiselecthandling.config' => [
                'class'     => MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
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
    ],
];
