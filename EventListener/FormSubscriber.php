<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public const ACTION          = 'plugin.multiselectHandlingManageAction';

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuild', 0],
        ];
    }

    public function onFormBuild(FormBuilderEvent $event): void
    {
        $event->addSubmitAction(
            self::ACTION,
            [
                // Label to group by in the dropdown
                'group'       => 'mautic.plugin.multiselect_handling.actions.group',

                // Label to list by in the dropdown
                'label'          => 'mautic.plugin.multiselect_handling.actions.action',
                'description'    => 'mautic.plugin.multiselect_handling.actions.action_description',
                'formType'       => SettingsType::class,

                // Callback method to be executed after the submission
                'eventName'    => FormAction::ACTION,
            ]
        );
    }
}
