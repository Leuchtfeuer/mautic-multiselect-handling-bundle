<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception;

class UnexpectedTypeException extends \RuntimeException
{
    public function __construct($value, string $expectedType)
    {
        parent::__construct(sprintf('Expected argument of type "%s", "%s" given', $expectedType, get_debug_type($value)));
    }
}
