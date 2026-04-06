<?php

namespace App\Exceptions;

class WageringRequirementException extends \Exception
{
    public function __construct(string $message = "Wagering requirement not met", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
