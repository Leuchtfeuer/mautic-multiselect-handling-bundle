<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception;

use RuntimeException;
use Throwable;

class InvalidSetupException extends RuntimeException
{
    public function __construct($message = 'Invalid setup.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
