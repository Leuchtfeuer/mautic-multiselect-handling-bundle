<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\InvalidSetupException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\NonExistingListException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;

class SegmentsModel
{
    public function __construct(
        private ListModel $listModel,
        private LeadFieldChoiceLoader $leadFieldChoiceLoader)
    {
    }

    /**
     * @return array<int, string>|null
     */
    public function getSegments(int $multiselectFieldId, bool $createNew): ?array
    {
        $choices = $this->leadFieldChoiceLoader->loadFieldsForChoices([$multiselectFieldId]);

        if (1 !== count($choices)) {
            throw new InvalidSetupException();
        }

        $properties = $choices[0]->getProperties();
        if (!isset($properties['list']) || !is_array($properties['list']) || 0 === count($properties['list'])) {
            throw new InvalidSetupException();
        }

        $segmentsSettings = [];
        foreach ($properties['list'] as $property) {
            $segmentsSettings[$property['value']] = $property['label'];
        }

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

                throw new NonExistingListException();
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
