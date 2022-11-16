<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMultiselectHandlingBundle\Validator;

use Symfony\Component\Validator\Constraint;

class UniqueMultiselectValues extends Constraint
{
    public string $messageEmpty        = 'mautic.plugin.multiselect_handling.validator.empty';
    public string $messageNonUnique    = 'mautic.plugin.multiselect_handling.validator.non_unique';
    public string $messageNotSameField = 'mautic.plugin.multiselect_handling.validator.not_same_field';
    public string $messageInvalidField = 'mautic.plugin.multiselect_handling.validator.invalid_field';
}
