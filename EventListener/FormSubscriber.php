<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateMultiSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldActionType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public const ACTION                                  = 'plugin.multiselectHandlingManageAction';
    public const ACTION_UPDATE_MULTISELECT_CONTACT_FIELD = 'plugin.multiselectHandlingContactFieldAction';
    public const ACTION_UPDATE_SELECT_CONTACT_FIELD      = 'plugin.multiselectHandlingContactSelectFieldAction';

    public function __construct(private Config $config)
    {
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

        $event->addSubmitAction(
            self::ACTION_UPDATE_MULTISELECT_CONTACT_FIELD,
            [
                // Label to group by in the dropdown
                'group'       => 'mautic.plugin.multiselect_handling.actions.group',

                // Label to list by in the dropdown
                'label'           => 'mautic.plugin.multiselect_handling.actions.contact_field_action',
                'description'     => 'mautic.plugin.multiselect_handling.actions.contact_field_action_description',
                'formType'        => UpdateMultiSelectFieldType::class,
                'formTypeOptions' => [
                    'multiple' => true,
                ],

                // Callback method to be executed after the submission
                'eventName'    => FormAction::ACTION_FORM_UPDATE_CONTACT_MULTISELECT_VALUE,
            ]
        );

        $event->addSubmitAction(
            self::ACTION_UPDATE_SELECT_CONTACT_FIELD,
            [
                // Label to group by in the dropdown
                'group'       => 'mautic.plugin.multiselect_handling.actions.group',

                // Label to list by in the dropdown
                'label'           => 'mautic.plugin.multiselect_handling.actions.contact_select_field_action',
                'description'     => 'mautic.plugin.multiselect_handling.actions.contact_select_field_action_description',
                'formType'        => UpdateSelectFieldActionType::class,
                'formTypeOptions' => [
                    'multiple' => false,
                ],

                // Callback method to be executed after the submission
                'eventName'    => FormAction::ACTION_FORM_UPDATE_CONTACT_SELECT_VALUE,
            ]
        );
    }
}
