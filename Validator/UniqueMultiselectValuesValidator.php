<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Validator;

use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateMultiSelectFieldType;
use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Model\SegmentsModel;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueMultiselectValuesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueMultiselectValues) {
            throw new UnexpectedTypeException($constraint, UniqueMultiselectValues::class);
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        $properties = null;
        if (isset($value['properties'])) {
            $properties = $value['properties'];
        }

        if (isset($value[UpdateMultiSelectFieldType::FIELD]) && null === $properties) {
            $properties = $value;
        }

        if (!is_array($properties) || !isset($properties[UpdateMultiSelectFieldType::FIELD])) {
            throw new UnexpectedTypeException(null, 'string');
        }

        $fields = [
            UpdateMultiSelectFieldType::ADD    => [],
            UpdateMultiSelectFieldType::REMOVE => [],
        ];

        foreach ($fields as $key => $item) {
            if (isset($properties[$key])) {
                if (is_string($properties[$key])) {
                    if ('' !== $properties[$key]) {
                        $fields[$key] = [$properties[$key]];
                    } else {
                        $fields[$key] = [];
                    }

                    continue;
                }

                if (is_array($properties[$key])) {
                    $fields[$key] = $properties[$key];
                    continue;
                }

                $this->context->buildViolation($constraint->messageInvalidField)
                    ->addViolation();

                return;
            }
        }

        if ([] === $fields[UpdateMultiSelectFieldType::ADD] && [] === $fields[UpdateMultiSelectFieldType::REMOVE]) {
            $this->context->buildViolation($constraint->messageEmpty)
                ->addViolation();

            return;
        }

        if (!is_numeric($properties[UpdateMultiSelectFieldType::FIELD])) {
            $this->context->buildViolation($constraint->messageInvalidField)
                ->addViolation();

            return;
        }

        foreach ([UpdateMultiSelectFieldType::ADD, UpdateMultiSelectFieldType::REMOVE] as $fieldName) {
            if ($this->checkValuesFromSameField($fields[$fieldName], (int) $properties[UpdateMultiSelectFieldType::FIELD], $constraint->messageNotSameField)) {
                continue;
            }

            return;
        }

        if ((count($fields[UpdateMultiSelectFieldType::ADD]) > 0 && 0 === count($fields[UpdateMultiSelectFieldType::REMOVE]))
            || (count($fields[UpdateMultiSelectFieldType::REMOVE]) > 0 && 0 === count($fields[UpdateMultiSelectFieldType::ADD]))) {
            return; // if one of fields is not set then fields are considered unique
        }

        $intersection = array_intersect(
            $fields[UpdateMultiSelectFieldType::ADD],
            $fields[UpdateMultiSelectFieldType::REMOVE]
        );

        if (0 === count($intersection)) {
            return;
        }

        $this->context->buildViolation($constraint->messageNonUnique)
            ->addViolation();
    }

    /**
     * @param array<string>|null $fieldValues
     */
    private function checkValuesFromSameField(?array $fieldValues, int $fieldId, string $message): bool
    {
        if (null === $fieldValues) {
            return true;
        }

        foreach ($fieldValues as $item) {
            if (!is_string($item)) {
                continue;
            }

            $id = SegmentsModel::splitAliasId($item)['id'];

            if ($fieldId !== $id) {
                $this->context->buildViolation($message)
                    ->addViolation();

                return false;
            }
        }

        return true;
    }
}
