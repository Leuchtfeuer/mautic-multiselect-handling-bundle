<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener;

use Mautic\CampaignBundle\Event\PendingEvent;
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

    public static function getSubscribedEvents(): array
    {
        return [
            self::MANAGE_MULTISELECT_FIELD_EVENT => 'onManageFieldAction',
            self::MANAGE_SELECT_FIELD_EVENT      => 'onManageFieldAction',
            self::MANAGE_SEGMENTS_EVENT          => 'onManageSegmentsAction',
        ];
    }

    public function onManageFieldAction(PendingEvent $pendingEvent): void
    {
        if (!$this->config->isPublished()) {
            $pendingEvent->failAll('Plugin not published');
            return;
        }

        $eventType = $pendingEvent->getEvent()->getType();
        if ($eventType !== self::MANAGE_MULTISELECT_FIELD_ACTION && $eventType !== self::MANAGE_SELECT_FIELD_ACTION) {
            return;
        }

        $config = $pendingEvent->getEvent()->getProperties();

        if (!isset($config[UpdateMultiSelectFieldType::FIELD])) {
            $pendingEvent->failAll('Invalid event configuration.');
            return;
        }

        // Parse configuration once (outside the loop)
        $fields = [
            UpdateMultiSelectFieldType::ADD    => [],
            UpdateMultiSelectFieldType::REMOVE => [],
        ];
        foreach ($fields as $key => $item) {
            if (isset($config[$key])) {
                if (is_string($config[$key]) && '' !== $config[$key]) {
                    $fields[$key] = [$config[$key]];
                    continue;
                }

                if (is_array($config[$key]) && [] !== $config[$key]) {
                    $fields[$key] = $config[$key];
                    continue;
                }

                if (!is_array($config[$key]) && !is_string($config[$key])) {
                    $pendingEvent->failAll('Field values has an incompatible type.');
                    return;
                }
            }
        }

        $fieldId = (int) $config[UpdateMultiSelectFieldType::FIELD];

        // Batch processing: Loop through all logs
        foreach ($pendingEvent->getPending() as $log) {
            $contact = $log->getLead();
            try {
                if (null === $field = $this->getCurrentField($contact, $fieldId)) {
                    $pendingEvent->pass($log); // Skip, field not in contact
                    continue;
                }

                $currentValue = $this->getFieldValue($field, false);

                // Add values
                foreach ($fields[UpdateMultiSelectFieldType::ADD] as $idAliasToAdd) {
                    $aliasToAdd = SegmentsModel::splitAliasId($idAliasToAdd)['alias'];
                    if (!in_array($aliasToAdd, $currentValue, true)) {
                        $currentValue[] = $aliasToAdd;
                    }
                }

                // Remove values
                foreach ($fields[UpdateMultiSelectFieldType::REMOVE] as $idAliasToRemove) {
                    $aliasToRemove = SegmentsModel::splitAliasId($idAliasToRemove)['alias'];
                    if (false !== $index = array_search($aliasToRemove, $currentValue, true)) {
                        unset($currentValue[$index]);
                    }
                }

                $fieldValue = array_filter(array_values($currentValue));

                if ('select' === $field['type']) {
                    $fieldValue = count($fieldValue) > 0 ? array_pop($fieldValue) : null;
                }

                $this->leadModel->setFieldValues($contact, [$field['alias'] => $fieldValue], true);
                $this->leadModel->saveEntity($contact);

                $pendingEvent->pass($log);
            } catch (\Exception $e) {
                $pendingEvent->fail($log, $e->getMessage());
            }
        }
    }

    public function onManageSegmentsAction(PendingEvent $pendingEvent): void
    {
        if (!$this->config->isPublished()) {
            $pendingEvent->failAll('Plugin not published');
            return;
        }

        $eventType = $pendingEvent->getEvent()->getType();
        if ($eventType !== self::MANAGE_SEGMENTS_ACTION) {
            return;
        }

        $config = $pendingEvent->getEvent()->getProperties();

        if (!isset($config[SettingsType::FIELD]) || !array_key_exists(SettingsType::CHECKBOX, $config)) {
            $pendingEvent->failAll('Invalid event configuration.');
            return;
        }

        $fieldId = (int) $config[SettingsType::FIELD];
        $createMissing = (bool) $config[SettingsType::CHECKBOX];

        $availableSegments = $this->segmentsModel->getSegments($fieldId, $createMissing);
        if (empty($availableSegments)) {
            $pendingEvent->failAll('Invalid setup.');
            return;
        }

        // Batch processing: Loop through all logs
        foreach ($pendingEvent->getPending() as $log) {
            $contact = $log->getLead();
            try {
                if (null === $field = $this->getCurrentField($contact, $fieldId)) {
                    $pendingEvent->pass($log); // Skip, field not in contact
                    continue;
                }

                $selectedSegments = $this->getFieldValue($field, true);

                /** @var LeadList[] $currentSegments */
                $currentSegments = $this->leadModel->getLists($contact);

                $newSegments = $removeSegments = [];
                foreach ($availableSegments as $segmentId => $segmentAlias) {
                    if (!in_array($segmentAlias, $selectedSegments, true)) {
                        if ($this->isInCurrentSegments($currentSegments, $segmentId)) {
                            $removeSegments[] = $segmentId;
                        }
                        continue;
                    }

                    if (!$this->isInCurrentSegments($currentSegments, $segmentId)) {
                        $newSegments[] = $segmentId;
                    }
                }

                if (count($removeSegments) > 0) {
                    $this->leadModel->removeFromLists($contact, $removeSegments);
                }

                if (count($newSegments) > 0) {
                    $this->leadModel->addToLists($contact, $newSegments);
                }

                $pendingEvent->pass($log);
            } catch (\Exception $e) {
                $pendingEvent->fail($log, $e->getMessage());
            }
        }
    }

    /**
     * @return array{id: int|string, alias: string, type: string, value: int|string|null}|null
     */
    private function getCurrentField(Lead $lead, int $fieldId): ?array
    {
        $fields = $lead->getFields();

        if (!isset($fields['core']) || !is_array($fields['core'])) {
            return null;
        }

        foreach ($fields['core'] as $coreField) {
            if (!is_array($coreField) || !isset($coreField['id'])) {
                continue;
            }

            if ((int) $coreField['id'] !== $fieldId) {
                continue;
            }

            return $coreField;
        }

        return null;
    }

    /**
     * @param array{id: int|string, alias: string, type: string, value: int|string|null} $field
     *
     * @return array<int,string>
     */
    private function getFieldValue(array $field, bool $cleanAlias): array
    {
        $value = $field['value'] ?? '';

        if (!is_string($value) && !is_int($value)) {
            return [];
        }

        $currentValue = explode('|', (string) $value);

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
