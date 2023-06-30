<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Loader;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Contracts\Service\ResetInterface;

class LeadFieldChoiceLoader implements ChoiceLoaderInterface, ResetInterface
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
            $choices[$field->getName().' ('.$field->getType().')'] = (string) $field->getId();
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
        if (empty($values)) {
            return [];
        }

        $objectsById = [];
        $objects     = [];
        /** @var LeadField[] $unorderedObjects */
        $unorderedObjects = $this->leadFieldRepository->getEntities([
            'ids' => $values,
        ]);

        foreach ($unorderedObjects as $object) {
            $objectsById[(string) $object->getId()] = $object;
        }

        foreach ($values as $i => $id) {
            if (isset($objectsById[$id])) {
                $objects[$i] = $objectsById[$id]->getId();
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
            if (!is_int($choice)) {
                continue;
            }

            foreach ($fields as $key => $field) {
                if ($choice === $field->getId()) {
                    $values[$index] = $field->getId();
                    unset($fields[$key]);
                    break;
                }
            }
        }

        return $values;
    }

    /**
     * @param array<string|mixed> $choices
     *
     * @return array<LeadField>
     */
    public function loadFieldsForChoices(array $choices): array
    {
        if (!count($choices)) {
            return [];
        }

        $fields = $this->getFields();

        $resultFields = [];
        foreach ($choices as $index => $choice) {
            if (!is_int($choice)) {
                continue;
            }

            foreach ($fields as $field) {
                if ($choice === $field->getId()) {
                    $resultFields[$index] = $field;
                    break;
                }
            }
        }

        return $resultFields;
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
