<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Exception\ValidationException;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\InvalidSetupException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\UnexpectedTypeException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateMultiSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldActionType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormAction implements EventSubscriberInterface
{
    public const ACTION             = 'mautic.plugin.multiselect_handling.actions.contact_segments_manage';

    // value of this const is misleading, but I'm leaving it as it is, to not break current forms
    public const ACTION_FORM_UPDATE_CONTACT_MULTISELECT_VALUE = 'mautic.plugin.multiselect_handling.form.actions.contact_segments_manage';
    public const ACTION_FORM_UPDATE_CONTACT_SELECT_VALUE      = 'mautic.plugin.multiselect_handling.form.actions.contact_select_manage';

    public const INVALID_SETUP = 'mautic.plugin.multiselect_handling.actions.contact_segments_manage_validate_invalid_setup';

    public const NON_EXISTING_LIST = 'mautic.plugin.multiselect_handling.actions.contact_segments_manage_validate_non_existing_list';

    public function __construct(private Config $config, private LeadFieldChoiceLoader $choiceLoader, private TranslatorInterface $translator, private LeadModel $leadModel, private SegmentsModel $segmentsModel)
    {
    }

    /**
     * @throws ValidationException
     */
    public function onAction(SubmissionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (false === $event->checkContext(FormSubscriber::ACTION) || null === $action = $event->getAction()) {
            return;
        }

        if (null === $contact = $event->getLead()) {
            return;
        }

        $actionProperties = $action->getProperties();

        if (!isset($actionProperties[SettingsType::FIELD]) || !array_key_exists(SettingsType::CHECKBOX, $actionProperties)) {
            throw new ValidationException('Seems like you do not have proper SettingsType.');
        }

        $choices = $this->choiceLoader->loadFieldsForChoices([$actionProperties[SettingsType::FIELD]]);

        if (1 !== count($choices)) {
            throw new ValidationException($this->translator->trans(self::INVALID_SETUP));
        }

        $contactFields          = $event->getContactFieldMatches();
        $actionSelectFieldAlias = $choices[0]->getAlias();
        if (!isset($contactFields[$actionSelectFieldAlias])) {
            return;
        }

        $selectedSegments = $contactFields[$actionSelectFieldAlias];
        // if the field is single select the value is a string.
        if (is_string($selectedSegments) && '' !== $selectedSegments) {
            $selectedSegments = [$selectedSegments];
        }
        // in case no checkboxes selected the value is ... string
        if (!is_array($selectedSegments)) {
            $selectedSegments = [];
        }

        $selectedSegments = array_map(function (string $segmentAlias): string {
            return $this->leadModel->cleanAlias($segmentAlias, '', 0, '-');
        }, $selectedSegments);

        try {
            if (null === $segmentsData = $this->segmentsModel->getSegments($actionProperties[SettingsType::FIELD], (bool) $actionProperties[SettingsType::CHECKBOX])) {
                throw new ValidationException($this->translator->trans(self::INVALID_SETUP));
            }
        } catch (InvalidSetupException) {
            throw new ValidationException($this->translator->trans(self::INVALID_SETUP));
        }

        /** @var LeadList[] $currentSegments */
        $currentSegments = $this->leadModel->getLists($contact);

        $removeSegments = [];
        foreach ($currentSegments as $currentSegment) {
            $segmentId = $currentSegment->getId();
            if (!isset($segmentsData[$segmentId])) {
                continue; // segment not in multiselect - noop
            }

            if (false !== $key = array_search($currentSegment->getAlias(), $selectedSegments, true)) {
                unset($selectedSegments[$key]);
                continue; // current contact segment is selected in form
            }

            unset($segmentsData[$segmentId]);
            $removeSegments[] = $segmentId;
        }

        $newSegments = [];
        foreach ($segmentsData as $segmentId => $segmentAlias) {
            if (!in_array($segmentAlias, $selectedSegments, true)) {
                continue;
            }

            $newSegments[] = $segmentId;
        }

        if (count($removeSegments) > 0) {
            $this->leadModel->removeFromLists($contact, $removeSegments);
        }

        if (count($newSegments) > 0) {
            $this->leadModel->addToLists($contact, $newSegments);
        }
    }

    public function processUpdateMultiselectField(SubmissionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (false === $event->checkContext(FormSubscriber::ACTION_UPDATE_MULTISELECT_CONTACT_FIELD)) {
            return;
        }

        $lead = $event->getLead();
        if (null === $lead || !$lead instanceof Lead) {
            return; // no lead to update
        }

        $action = $event->getAction();
        $fields = [
            UpdateMultiSelectFieldType::ADD    => [],
            UpdateMultiSelectFieldType::REMOVE => [],
        ];

        $values = $action->getProperties();
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

        if (null === $field = $this->getCurrentField($lead, (int) $values[UpdateMultiSelectFieldType::FIELD])) {
            return; // field is not in contact
        }

        $currentValue = $this->getFieldValue($field);

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
    }

    public function processUpdateSelectField(SubmissionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (false === $event->checkContext(FormSubscriber::ACTION_UPDATE_SELECT_CONTACT_FIELD)) {
            return;
        }

        $lead = $event->getLead();
        if (!$lead instanceof Lead) {
            return; // no lead to update
        }

        $action     = $event->getAction();
        $properties = $action->getProperties();

        if (null === $field = $this->getCurrentField($lead, (int) $properties[UpdateMultiSelectFieldType::FIELD])) {
            return; // field is not in contact
        }

        $valueToSet = SegmentsModel::splitAliasId($properties[UpdateSelectFieldActionType::FIELD_SELECT_VALUE])['alias'];
        $this->leadModel->setFieldValues($lead, [$field['alias'] => $valueToSet], true);
        $this->leadModel->saveEntity($lead);
    }

    /**
     * @param array<int|string> $field
     *
     * @return array<int,string>
     */
    private function getFieldValue(array $field): array
    {
        $currentValue = explode('|', $field['value'] ?? '');

        if (!is_array($currentValue)) {
            throw new UnexpectedTypeException($currentValue, 'array');
        }

        if ([''] === $currentValue) {
            return [];
        }

        return $currentValue;
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
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            self::ACTION                                         => 'onAction',
            self::ACTION_FORM_UPDATE_CONTACT_MULTISELECT_VALUE   => 'processUpdateMultiselectField',
            self::ACTION_FORM_UPDATE_CONTACT_SELECT_VALUE        => 'processUpdateSelectField',
        ];
    }
}
