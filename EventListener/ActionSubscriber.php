<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\EventListener;

use LogicException;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticMultiselectHandlingBundle\Exception\UnexpectedTypeException;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\UpdateMultiselectFieldType;
use MauticPlugin\MauticMultiselectHandlingBundle\Model\SegmentsModel;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    public const MANAGE_FIELD_EVENT     = 'plugin.multiselect_handling.manage_field_event';
    public const MANAGE_SEGMENTS_EVENT  = 'plugin.multiselect_handling.manage_segments_event';
    public const MANAGE_FIELD_ACTION    = 'plugin.multiselect_handling.manage_field_action';
    public const MANAGE_SEGMENTS_ACTION = 'plugin.multiselect_handling.manage_segments_action';

    private LeadModel $leadModel;

    private SegmentsModel $segmentsModel;

    public function __construct(LeadModel $leadModel, SegmentsModel $segmentsModel)
    {
        $this->leadModel     = $leadModel;
        $this->segmentsModel = $segmentsModel;
    }

    public function onManageFieldAction(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext(self::MANAGE_FIELD_ACTION)) {
            return;
        }

        $values = $event->getConfig();

        if (!isset($values[UpdateMultiselectFieldType::FIELD])) {
            throw new RuntimeException('Invalid event configuration.');
        }

        $lead   = $event->getLead();

        if (null === $field = $this->getCurrentField($event, $lead, $values)) {
            return; // field is not in contact
        }

        $currentValue = $this->getFieldValue($field);

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

        $this->leadModel->setFieldValues($lead, [$field['alias'] => array_filter(array_values($currentValue))], true);
        $this->leadModel->saveEntity($lead);

        $event->setResult(true); // for legacy event dispatcher
    }

    public function onManageSegmentsAction(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext(self::MANAGE_SEGMENTS_ACTION)) {
            return;
        }

        $values = $event->getConfig();

        if (!isset($values[SettingsType::FIELD], $values[SettingsType::CHECKBOX])) {
            throw new RuntimeException('Invalid event configuration.');
        }

        $lead   = $event->getLead();

        if (null === $field = $this->getCurrentField($event, $lead, $values)) {
            return; // field is not in contact
        }

        $selectedSegments   = $this->getFieldValue($field);
        $multiselectFieldId = $values[SettingsType::FIELD];

        if (null === $availableSegments = $this->segmentsModel->getSegments($multiselectFieldId, (bool) $values[SettingsType::CHECKBOX])) {
            throw new LogicException('Invalid setup.');
        }

        /** @var LeadList[] $currentSegments */
        $currentSegments = $this->leadModel->getLists($lead);

        $newSegments = $removeSegments = [];
        foreach ($availableSegments as $segmentId => $segmentAlias) {
            if (!in_array($segmentAlias, $selectedSegments, true)) {
                if ($this->isInCurrentSegments($currentSegments, $segmentId)) {
                    $removeSegments[] = $segmentId;
                }

                continue;
            }

            if ($this->isInCurrentSegments($currentSegments, $segmentId)) {
                continue;
            }

            $newSegments[] = $segmentId;
        }

        if (count($removeSegments) > 0) {
            $this->leadModel->removeFromLists($lead, $removeSegments);
        }

        if (count($newSegments) > 0) {
            $this->leadModel->addToLists($lead, $newSegments);
        }

        $event->setResult(true); // for legacy event dispatcher
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::MANAGE_FIELD_EVENT    => 'onManageFieldAction',
            self::MANAGE_SEGMENTS_EVENT => 'onManageSegmentsAction',
        ];
    }

    /**
     * @param array<int|string> $values
     *
     * @return array<string>
     */
    private function getCurrentField(CampaignExecutionEvent $event, Lead $lead, array $values): ?array
    {
        $fields = $lead->getFields();
        $field  = [];

        foreach ($fields['core'] as $coreField) {
            if ((int) $coreField['id'] !== (int) $values[UpdateMultiselectFieldType::FIELD]) {
                continue;
            }

            $field = $coreField;
        }

        if ([] === $field) {
            return null; // field is not in contact
        }

        return $field;
    }

    /**
     * @param array<int|string> $field
     *
     * @return array<string>
     */
    private function getFieldValue(array $field): ?array
    {
        $currentValue = explode('|', $field['value'] ?? '');

        if (!is_array($currentValue)) {
            throw new UnexpectedTypeException($currentValue, 'array');
        }

        return $currentValue;
    }

    /**
     * @param array<LeadList> $currentSegments
     */
    private function isInCurrentSegments(array $currentSegments, int $segmentId): bool
    {
        foreach ($currentSegments as $currentSegment) {
            if ($currentSegment->getId() !== $segmentId) {
                continue;
            }

            return true;
        }

        return false;
    }
}
