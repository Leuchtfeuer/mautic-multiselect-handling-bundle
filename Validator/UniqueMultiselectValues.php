<?php

declare(strict_types=1);

namespace MauticPlugin\MauticContactSegmentsBundle\Validator;

use Symfony\Component\Validator\Constraint;

class UniqueMultiselectValues extends Constraint
{
    public string $messageEmpty        = 'mautic.plugin.contact_segments_manage.validator.empty';
    public string $messageNonUnique    = 'mautic.plugin.contact_segments_manage.validator.non_unique';
    public string $messageNotSameField = 'mautic.plugin.contact_segments_manage.validator.not_same_field';
}
