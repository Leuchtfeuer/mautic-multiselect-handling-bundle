<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Form\Loader;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use RuntimeException;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Contracts\Service\ResetInterface;

class LeadFieldValuesChoiceLoader implements ChoiceLoaderInterface, ResetInterface
{
    private ?ChoiceListInterface $choiceList = null;

    private LeadFieldRepository $leadFieldRepository;

    /**
     * @var array<LeadField>
     */
    private array $fields = [];

    private ?bool $loadMultiSelect = null;

    public function __construct(LeadFieldRepository $leadFieldRepository)
    {
        $this->leadFieldRepository = $leadFieldRepository;
    }

    /**
     * @param callable|null $value
     *
     * @return ChoiceListInterface<string, int>
     */
    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        $choices = [];
        foreach ($this->getFields() as $field) {
            $name = $field->getName();

            foreach ($this->getMultiselectValues($field) as $idAndAlias => $data) {
                $choices[sprintf('(%s) %s', $name, $data['name'])] = $idAndAlias;
            }
        }

        return $this->choiceList = new ArrayChoiceList($choices, $value);
    }

    /**
     * @param callable|null $value
     *
     * @return array<string>
     */
    public function loadChoicesForValues(array $values, $value = null): array
    {
        if ([] === $values) {
            return [];
        }

        $fieldIds = [];
        foreach ($values as $possibleValue) {
            $idAndAlias = $this->extractIdAndAlias($possibleValue);

            if (isset($fieldIds[$idAndAlias['id']])) {
                continue;
            }

            $fieldIds[$idAndAlias['id']] = true;
        }

        if (0 === count($fieldIds)) {
            return [];
        }

        $valuesById = [];
        $objects    = [];
        /** @var LeadField[] $unorderedObjects */
        $unorderedObjects = $this->leadFieldRepository->getEntities([
            'ids' => array_keys($fieldIds),
        ]);

        foreach ($unorderedObjects as $object) {
            foreach ($this->getMultiselectValues($object) as $idAndAlias => $data) {
                $valuesById[$idAndAlias] = $idAndAlias;
            }
        }

        foreach ($values as $i => $id) {
            if (isset($valuesById[$id])) {
                $objects[$i] = $valuesById[$id];
            }
        }

        return $objects;
    }

    /**
     * @param array<string|mixed> $choices
     * @param callable|null       $value
     *
     * @return array<string>
     */
    public function loadValuesForChoices(array $choices, $value = null): array
    {
        if (!count($choices)) {
            return [];
        }

        $fields = $this->getFields();

        $values = [];
        foreach ($choices as $index => $choice) {
            if (!is_string($choice)) {
                continue;
            }

            foreach ($fields as $field) {
                foreach ($this->getMultiselectValues($field) as $idAndAlias => $data) {
                    if ($choice === $idAndAlias) {
                        $values[$index] = $idAndAlias;
                        break 2;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * @return array<LeadField>
     */
    private function getFields(): array
    {
        if (count($this->fields)) {
            return $this->fields;
        }

        if (null === $this->loadMultiSelect) {
            return $this->fields = array_merge(
                $this->leadFieldRepository->getFieldsByType('multiselect'),
                $this->leadFieldRepository->getFieldsByType('select')
            );
        }

        if (true === $this->loadMultiSelect) {
            return $this->fields = $this->leadFieldRepository->getFieldsByType('multiselect');
        }

        return $this->fields = $this->leadFieldRepository->getFieldsByType('select');
    }

    /**
     * Returns an array indexed on fieldId-fieldAlias. This is needed, so in validation we will not
     * use LeadFieldRepository, but compare values by id. Also prevents adding field values from different fields with
     * same alias.
     *
     * @return array<string, array{id: int, name: string, alias: string}>
     */
    private function getMultiselectValues(LeadField $field): array
    {
        $properties = $field->getProperties();
        if (!isset($properties['list']) || !is_array($properties['list']) || 0 === count($properties['list'])) {
            return [];
        }

        $id     = $field->getId();
        $values = [];
        foreach ($properties['list'] as $property) {
            $values[$id.'-'.$property['value']] = [
                'id'    => $id,
                'name'  => $property['label'],
                'alias' => $property['value'],
            ];
        }

        return $values;
    }

    /**
     * @return array{id?: int, alias?: string}
     */
    private function extractIdAndAlias(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        $idAndAlias = explode('-', $value);

        if (2 !== count($idAndAlias) || !is_numeric($idAndAlias[0])) {
            throw new RuntimeException('There is something wrong with the field alias.');
        }

        return ['id' => (int) $idAndAlias[0], 'alias' => $idAndAlias['1']];
    }

    public function reset(): void
    {
        $this->choiceList = null;
        $this->fields     = [];
    }

    public function setType(bool $multiple): void
    {
        $this->loadMultiSelect = $multiple;
    }
}
