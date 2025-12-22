<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener;

use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\UnexpectedTypeException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateMultiSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionSubscriber implements EventSubscriberInterface
{
    public const MANAGE_MULTISELECT_FIELD_EVENT = 'plugin.multiselect_handling.manage_multiselect_field_event';
    public const MANAGE_SELECT_FIELD_EVENT      = 'plugin.multiselect_handling.manage_select_field_event';
    public const MANAGE_SEGMENTS_EVENT          = 'plugin.multiselect_handling.manage_segments_event';
    // strange names due to mautic having a constraint in the `campaign_events` table for 50 symbols
    public const MANAGE_MULTISELECT_FIELD_ACTION = 'plugin.multiselect_handling.manage_N_field_action';
    public const MANAGE_SELECT_FIELD_ACTION      = 'plugin.multiselect_handling.manage_1_field_action';
    public const MANAGE_SEGMENTS_ACTION          = 'plugin.multiselect_handling.manage_segments_action';

    public function __construct(private Config $config, private LeadModel $leadModel, private SegmentsModel $segmentsModel)
    {
    }

    public function onManageFieldAction(CampaignExecutionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (!$event->checkContext(self::MANAGE_MULTISELECT_FIELD_ACTION) && !$event->checkContext(self::MANAGE_SELECT_FIELD_ACTION)) {
            return;
        }

        $values = $event->getConfig();

        if (!isset($values[UpdateMultiSelectFieldType::FIELD])) {
            throw new \RuntimeException('Invalid event configuration.');
        }

        $fields = [
            UpdateMultiSelectFieldType::ADD    => [],
            UpdateMultiSelectFieldType::REMOVE => [],
        ];
        foreach ($fields as $key => $item) {
            if (isset($values[$key])) {
                if (is_string($values[$key]) && '' !== $values[$key]) {
                    $fields[$key] = [$values[$key]];
                    continue;
                }

                if (is_array($values[$key]) && [] !== $values[$key]) {
                    $fields[$key] = $values[$key];
                    continue;
                }

                if (!is_array($values[$key]) && !is_string($values[$key])) {
                    throw new \RuntimeException('Field values has an incompatible type.');
                }
            }
        }

        $lead = $event->getLead();

        if (null === $lead || !$lead instanceof Lead) {
            return; // no lead to update
        }

        if (null === $field = $this->getCurrentField($lead, (int) $values[UpdateMultiSelectFieldType::FIELD])) {
            return; // field is not in contact
        }

        $currentValue = $this->getFieldValue($field, false);

        foreach ($fields[UpdateMultiSelectFieldType::ADD] as $idAliasToAdd) {
            $aliasToAdd = SegmentsModel::splitAliasId($idAliasToAdd)['alias'];
            if (in_array($aliasToAdd, $currentValue, true)) {
                continue;
            }

            $currentValue[] = $aliasToAdd;
        }

        foreach ($fields[UpdateMultiSelectFieldType::REMOVE] as $idAliasToRemove) {
            $aliasToRemove = SegmentsModel::splitAliasId($idAliasToRemove)['alias'];
            if (false === $index = array_search($aliasToRemove, $currentValue, true)) {
                continue;
            }

            unset($currentValue[$index]);
        }

        $fieldValue = array_filter(array_values($currentValue));

        if ('select' === $field['type']) {
            $fieldValue = count($fieldValue) > 0 ? array_pop($fieldValue) : null;
        }

        $this->leadModel->setFieldValues($lead, [$field['alias'] => $fieldValue], true);
        $this->leadModel->saveEntity($lead);

        $event->setResult(true); // for legacy event dispatcher
    }

    public function onManageSegmentsAction(CampaignExecutionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (!$event->checkContext(self::MANAGE_SEGMENTS_ACTION)) {
            return;
        }

        $values = $event->getConfig();

        if (!isset($values[SettingsType::FIELD]) || !array_key_exists(SettingsType::CHECKBOX, $values)) {
            throw new \RuntimeException('Invalid event configuration.');
        }

        $lead = $event->getLead();

        if (null === $field = $this->getCurrentField($lead, (int) $values[UpdateMultiSelectFieldType::FIELD])) {
            return; // field is not in contact
        }

        $selectedSegments   = $this->getFieldValue($field, true);
        $multiselectFieldId = $values[SettingsType::FIELD];

        if (null === $availableSegments = $this->segmentsModel->getSegments($multiselectFieldId, (bool) $values[SettingsType::CHECKBOX])) {
            throw new \LogicException('Invalid setup.');
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
            self::MANAGE_MULTISELECT_FIELD_EVENT => 'onManageFieldAction',
            self::MANAGE_SELECT_FIELD_EVENT      => 'onManageFieldAction',
            self::MANAGE_SEGMENTS_EVENT          => 'onManageSegmentsAction',
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getCurrentField(Lead $lead, int $fieldId): ?array
    {
        $fields = $lead->getFields();
        $field  = [];

        foreach ($fields['core'] as $coreField) {
            if ((int) $coreField['id'] !== $fieldId) {
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
     * @return array<int,string>
     */
    private function getFieldValue(array $field, bool $cleanAlias): array
    {
        $currentValue = explode('|', $field['value'] ?? '');

        if (!is_array($currentValue)) {
            throw new UnexpectedTypeException($currentValue, 'array');
        }

        if ([''] === $currentValue) {
            return [];
        }

        if (!$cleanAlias) {
            return $currentValue;
        }

        return array_map(function (string $segmentAlias): string {
            return $this->leadModel->cleanAlias($segmentAlias, '', 0, '-');
        }, $currentValue);
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
