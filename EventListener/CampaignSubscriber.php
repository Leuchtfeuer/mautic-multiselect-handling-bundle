<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\UpdateMultiselectFieldType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $action = [
            'label'       => 'plugin.multiselect_handling.action.label',
            'description' => 'plugin.multiselect_handling.action.description',
            'formType'    => UpdateMultiselectFieldType::class,
            'eventName'   => ActionSubscriber::EVENT,
        ];

        $event->addAction(ActionSubscriber::ACTION, $action);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
        ];
    }
}
