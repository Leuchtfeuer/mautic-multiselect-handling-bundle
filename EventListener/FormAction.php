<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Exception\ValidationException;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticMultiselectHandlingBundle\Exception\InvalidSetupException;
use MauticPlugin\MauticMultiselectHandlingBundle\Exception\NonExistingListException;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
use MauticPlugin\MauticMultiselectHandlingBundle\Model\SegmentsModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormAction implements EventSubscriberInterface
{
    public const ACTION = 'mautic.plugin.multiselect_handling.actions.contact_segments_manage';

    public const INVALID_SETUP = 'mautic.plugin.multiselect_handling.actions.contact_segments_manage_validate_invalid_setup';

    public const NON_EXISTING_LIST = 'mautic.plugin.multiselect_handling.actions.contact_segments_manage_validate_non_existing_list';

    private TranslatorInterface $translator;

    private LeadFieldChoiceLoader $choiceLoader;

    private LeadModel $leadModel;

    private SegmentsModel $segmentsModel;

    public function __construct(LeadFieldChoiceLoader $choiceLoader, TranslatorInterface $translator, LeadModel $leadModel, SegmentsModel $segmentsModel)
    {
        $this->choiceLoader     = $choiceLoader;
        $this->translator       = $translator;
        $this->leadModel        = $leadModel;
        $this->segmentsModel    = $segmentsModel;
    }

    /**
     * @throws ValidationException
     */
    public function onAction(SubmissionEvent $event): void
    {
        if (false === $event->checkContext(FormSubscriber::ACTION) || null === $action = $event->getAction()) {
            return;
        }

        if (null === $contact = $event->getLead()) {
            return;
        }

        $actionProperties = $action->getProperties();

        if (!isset($actionProperties[SettingsType::FIELD], $actionProperties[SettingsType::CHECKBOX])) {
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

        try {
            if (null === $segmentsData = $this->segmentsModel->getSegments($actionProperties[SettingsType::FIELD], (bool) $actionProperties[SettingsType::CHECKBOX])) {
                throw new ValidationException($this->translator->trans(self::INVALID_SETUP));
            }
        } catch (InvalidSetupException $e) {
            throw new ValidationException($this->translator->trans(self::INVALID_SETUP));
        } catch (NonExistingListException $e) {
            throw new ValidationException($this->translator->trans(self::NON_EXISTING_LIST));
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

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            self::ACTION   => 'onAction',
        ];
    }
}
