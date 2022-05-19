<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\UpdateMultiselectFieldType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $action = [
            'label'       => 'plugin.multiselect_handling.field_action.label',
            'description' => 'plugin.multiselect_handling.field_action.description',
            'formType'    => UpdateMultiselectFieldType::class,
            'eventName'   => ActionSubscriber::MANAGE_FIELD_EVENT,
        ];

        $event->addAction(ActionSubscriber::MANAGE_FIELD_ACTION, $action);

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
