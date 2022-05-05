<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\Validator;

use MauticPlugin\MauticContactSegmentsBundle\Form\Type\UpdateMultiselectFieldType;
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

        if (!isset($value['properties'][UpdateMultiselectFieldType::FIELD])) {
            throw new UnexpectedTypeException(null, 'string');
        }

        if (!isset($value['properties'][UpdateMultiselectFieldType::ADD])
            && !isset($value['properties'][UpdateMultiselectFieldType::REMOVE])) {
            $this->context->buildViolation($constraint->messageEmpty)
                ->addViolation();

            return;
        }

        if ((isset($value['properties'][UpdateMultiselectFieldType::ADD]) && !$this->checkValueFromSameField($value['properties'][UpdateMultiselectFieldType::ADD], $value['properties'][UpdateMultiselectFieldType::FIELD], $constraint->messageNotSameField))
            || (isset($value['properties'][UpdateMultiselectFieldType::REMOVE]) && !$this->checkValueFromSameField($value['properties'][UpdateMultiselectFieldType::REMOVE], $value['properties'][UpdateMultiselectFieldType::FIELD], $constraint->messageNotSameField))) {
            return;
        }

        if ((isset($value['properties'][UpdateMultiselectFieldType::ADD]) && !isset($value['properties'][UpdateMultiselectFieldType::REMOVE]))
            || (isset($value['properties'][UpdateMultiselectFieldType::REMOVE]) && !isset($value['properties'][UpdateMultiselectFieldType::ADD]))) {
            return; // if one of fields is not set then fields are considered unique
        }

        $intersection = array_intersect(
            $value['properties'][UpdateMultiselectFieldType::ADD],
            $value['properties'][UpdateMultiselectFieldType::REMOVE]
        );

        if (0 === count($intersection)) {
            return;
        }

        $this->context->buildViolation($constraint->messageNonUnique)
            ->addViolation();
    }

    private function checkValueFromSameField(?array $fieldValues, string $fieldId, string $message): bool
    {
        if (null === $fieldValues) {
            return true;
        }

        foreach ($fieldValues as $item) {
            $value = explode('-', $item);

            if ($fieldId !== $value[0]) {
                $this->context->buildViolation($message)
                    ->addViolation();

                return false;
            }
        }

        return true;
    }
}
