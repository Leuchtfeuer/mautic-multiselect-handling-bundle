<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception\InvalidSetupException;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader\LeadFieldChoiceLoader;

class SegmentsModel
{
    public function __construct(private ListModel $listModel, private LeadFieldChoiceLoader $leadFieldChoiceLoader)
    {
    }

    /**
     * @return array<int, string>
     */
    public function getSegments(int $multiselectFieldId, bool $createNew): array
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
            if (!is_array($property) || !isset($property['value'], $property['label'])) {
                continue;
            }
            if (!is_string($property['value']) && !is_int($property['value'])) {
                continue;
            }
            if (!is_string($property['label'])) {
                continue;
            }
            $segmentsSettings[$property['value']] = $property['label'];
        }

        $segments = [];
        foreach ($segmentsSettings as $segmentAlias => $segmentName) {
            $segmentAlias = $this->listModel->cleanAlias((string) $segmentAlias, '', 0, '-');
            $segmentsData = $this->listModel->getUserLists($segmentAlias);

            if (1 !== count($segmentsData)) {
                if ($createNew) {
                    $newSegment = new LeadList();
                    $newSegment->setName($segmentName)
                        ->setAlias($segmentAlias);

                    $this->listModel->saveEntity($newSegment);
                    $segments[$newSegment->getId()] = $newSegment->getAlias();
                }
                continue;
            }

            $segmentData = array_pop($segmentsData);
            if (!is_array($segmentData) || !isset($segmentData['id'], $segmentData['alias'])) {
                continue;
            }

            if (!is_numeric($segmentData['id']) || !is_string($segmentData['alias'])) {
                continue;
            }

            $segments[(int) $segmentData['id']] = $segmentData['alias'];
        }

        return $segments;
    }

    /**
     * @return array{id:int, alias: string}
     */
    public static function splitAliasId(string $value): array
    {
        $matches = [];
        if (in_array(preg_match('/^(?<id>\d+)-(?<alias>.+)$/i', $value, $matches), [false, 0], true)) {
            throw new \RuntimeException('There is something wrong with the field alias.');
        }

        if (!isset($matches['id'], $matches['alias']) || !is_numeric($matches['id'])) {
            throw new \RuntimeException('There is something wrong with the field alias.');
        }

        return ['id' => (int) $matches['id'], 'alias' => $matches['alias']];
    }
}
