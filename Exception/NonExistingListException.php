<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMultiselectHandlingBundle\Exception;

class NonExistingListException extends \RuntimeException
{
    public function __construct(string $message = 'Segment does not exist.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
