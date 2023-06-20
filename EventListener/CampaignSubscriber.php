<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $action = [
            'label'           => 'plugin.multiselect_handling.multiselect_field_action.label',
            'description'     => 'plugin.multiselect_handling.multiselect_field_action.description',
            'formType'        => UpdateSelectFieldType::class,
            'formTypeOptions' => [
                'multiple' => true,
            ],
            'eventName'   => ActionSubscriber::MANAGE_MULTISELECT_FIELD_EVENT,
        ];

        $event->addAction(ActionSubscriber::MANAGE_MULTISELECT_FIELD_ACTION, $action);

        $action = [
            'label'           => 'plugin.multiselect_handling.select_field_action.label',
            'description'     => 'plugin.multiselect_handling.select_field_action.description',
            'formType'        => UpdateSelectFieldType::class,
            'formTypeOptions' => [
                'multiple' => false,
            ],
            'eventName'   => ActionSubscriber::MANAGE_SELECT_FIELD_EVENT,
        ];

        $event->addAction(ActionSubscriber::MANAGE_SELECT_FIELD_ACTION, $action);

        $action = [
            'label'       => 'plugin.multiselect_handling.segment_action.label',
            'description' => 'plugin.multiselect_handling.segment_action.description',
            'formType'    => SettingsType::class,
            'eventName'   => ActionSubscriber::MANAGE_SEGMENTS_EVENT,
        ];

        $event->addAction(ActionSubscriber::MANAGE_SEGMENTS_ACTION, $action);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
        ];
    }
}
