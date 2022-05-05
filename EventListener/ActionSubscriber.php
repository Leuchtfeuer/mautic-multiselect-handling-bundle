<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\EventListener;

use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticContactSegmentsBundle\Form\Type\UpdateMultiselectFieldType;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    public const EVENT  = 'plugin.contact_segments_action_event';
    public const ACTION = 'plugin.contact_segments_action';

    private LeadModel $leadModel;

    public function __construct(LeadModel $leadModel)
    {
        $this->leadModel = $leadModel;
    }

    public function onAction(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext(self::ACTION)) {
            return;
        }

        $values = $event->getConfig();

        if (!isset($values[UpdateMultiselectFieldType::FIELD])) {
            throw new RuntimeException('Invalid event configuration.');
        }

        $lead   = $event->getLead();
        $fields = $lead->getFields();
        $field  = [];

        foreach ($fields['core'] as $coreField) {
            if ((int) $coreField['id'] !== (int) $values[UpdateMultiselectFieldType::FIELD]) {
                continue;
            }

            $field = $coreField;
        }

        if ([] === $field) {
            return; // field is not in contact
        }

        $currentValue = explode('|', $field['value'] ?? '');

        foreach ($values[UpdateMultiselectFieldType::ADD] as $idAliasToAdd) {
            $aliasToAdd = explode('-', $idAliasToAdd)[1];
            if (in_array($aliasToAdd, $currentValue, true)) {
                continue;
            }

            $currentValue[] = $aliasToAdd;
        }

        foreach ($values[UpdateMultiselectFieldType::REMOVE] as $idAliasToRemove) {
            $aliasToRemove = explode('-', $idAliasToRemove)[1];
            if (false === $index = array_search($aliasToRemove, $currentValue, true)) {
                continue;
            }

            unset($currentValue[$index]);
        }

        $this->leadModel->setFieldValues($lead, [$field['alias'] => array_filter(array_values($currentValue))], false);
        $this->leadModel->saveEntity($lead);

        $event->setResult(true); // for legacy event dispatcher
    }

    public static function getSubscribedEvents(): array
    {
        return [self::EVENT => 'onAction'];
    }
}
