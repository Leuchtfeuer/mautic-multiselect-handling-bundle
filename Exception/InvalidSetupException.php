<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception;

class InvalidSetupException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid setup.', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
