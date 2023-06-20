<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public const ACTION = 'plugin.multiselectHandlingManageAction';

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuild', 0],
        ];
    }

    public function onFormBuild(FormBuilderEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

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
