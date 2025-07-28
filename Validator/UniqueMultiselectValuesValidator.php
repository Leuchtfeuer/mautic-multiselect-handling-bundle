<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Validator;

use MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Form\Type\UpdateSelectFieldType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueMultiselectValuesValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
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

        if (isset($value[UpdateSelectFieldType::FIELD]) && null === $properties) {
            $properties = $value;
        }

        if (!isset($properties[UpdateSelectFieldType::FIELD])) {
            throw new UnexpectedTypeException(null, 'string');
        }

        $fields = [
            UpdateSelectFieldType::ADD    => [],
            UpdateSelectFieldType::REMOVE => [],
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

        if ([] === $fields[UpdateSelectFieldType::ADD] && [] === $fields[UpdateSelectFieldType::REMOVE]) {
            $this->context->buildViolation($constraint->messageEmpty)
                ->addViolation();

            return;
        }

        foreach ([UpdateSelectFieldType::ADD, UpdateSelectFieldType::REMOVE] as $fieldName) {
            // check if the value (multi is in array) and is in the same field and add the validation message otherwise
            // stop adding messages if an error found, otherwise add the violation of wrong field value type
            if ($this->checkValuesFromSameField($fields[$fieldName], (int) $properties[UpdateSelectFieldType::FIELD], $constraint->messageNotSameField)) {
                continue;
            }

            return;
        }

        if ((count($fields[UpdateSelectFieldType::ADD]) > 0 && 0 === count($fields[UpdateSelectFieldType::REMOVE]))
            || (count($fields[UpdateSelectFieldType::REMOVE]) > 0 && 0 === count($fields[UpdateSelectFieldType::ADD]))) {
            return; // if one of fields is not set then fields are considered unique
        }

        $intersection = array_intersect(
            $fields[UpdateSelectFieldType::ADD],
            $fields[UpdateSelectFieldType::REMOVE]
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
            $value = explode('-', $item);

            if ($fieldId !== (int) $value[0]) {
                $this->context->buildViolation($message)
                    ->addViolation();

                return false;
            }
        }

        return true;
    }
}
