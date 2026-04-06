<?php

namespace App\Exceptions;

class WithdrawalPendingException extends \Exception
{
    public function __construct(string $message = "A withdrawal request is already pending", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
