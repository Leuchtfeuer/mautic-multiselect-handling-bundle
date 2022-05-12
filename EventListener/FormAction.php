<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Exception\ValidationException;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;
use MauticPlugin\MauticMultiselectHandlingBundle\Form\Type\SettingsType;
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

    private ListModel $listModel;

    public function __construct(LeadFieldChoiceLoader $choiceLoader, TranslatorInterface $translator, LeadModel $leadModel, ListModel $listModel)
    {
        $this->choiceLoader = $choiceLoader;
        $this->translator   = $translator;
        $this->leadModel    = $leadModel;
        $this->listModel    = $listModel;
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

        $contactFields               = $event->getContactFieldMatches();
        $actionMultiselectFieldAlias = $choices[0]->getAlias();
        if (!isset($contactFields[$actionMultiselectFieldAlias])) {
            return;
        }

        // in case no checkboxes selected the value is ... string
        $selectedSegments = $contactFields[$actionMultiselectFieldAlias];
        if (!is_array($selectedSegments)) {
            $selectedSegments = [];
        }

        $properties = $choices[0]->getProperties();
        if (!isset($properties['list']) || !is_array($properties['list']) || 0 === count($properties['list'])) {
            throw new ValidationException($this->translator->trans(self::INVALID_SETUP));
        }

        $segmentsSettings = [];
        foreach ($properties['list'] as $property) {
            $segmentsSettings[$property['value']] = $property['label'];
        }

        if (null === $segmentsData = $this->getSegments($segmentsSettings, (bool) $actionProperties[SettingsType::CHECKBOX])) {
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

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            self::ACTION   => 'onAction',
        ];
    }

    /**
     * @param array<string, string> $segmentsSettings
     *
     * @return array<int, string>|null
     */
    private function getSegments(array $segmentsSettings, bool $createNew): ?array
    {
        $segments = [];
        foreach ($segmentsSettings as $segmentAlias => $segmentName) {
            $segmentsData = $this->listModel->getUserLists($segmentAlias);

            if (1 !== count($segmentsData)) {
                if ($createNew) {
                    $newSegment = new LeadList();
                    $newSegment->setName($segmentName)
                        ->setAlias($segmentAlias);

                    $this->listModel->saveEntity($newSegment);
                    $segments[$newSegment->getId()] = $newSegment->getAlias();

                    continue;
                }

                throw new ValidationException($this->translator->trans(self::NON_EXISTING_LIST));
            }

            $segmentData = array_pop($segmentsData);

            if (!is_array($segmentData) || !isset($segmentData['id'], $segmentData['alias'])) {
                return null;
            }

            $segments[(int) $segmentData['id']] = $segmentData['alias'];
        }

        return $segments;
    }
}
