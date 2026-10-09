<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class BusinessRuleException extends Exception implements ShouldntReport
{
    public static function make(string $message): self
    {
        return new self($message);
    }
}
