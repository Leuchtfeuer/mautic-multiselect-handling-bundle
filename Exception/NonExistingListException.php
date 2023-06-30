<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception;

use RuntimeException;
use Throwable;

class NonExistingListException extends RuntimeException
{
    public function __construct($message = 'Segment does not exist.', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
