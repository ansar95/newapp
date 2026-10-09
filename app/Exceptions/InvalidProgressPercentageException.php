<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidProgressPercentageException extends InvalidArgumentException
{
    public static function outOfRange(float|int|string $value): self
    {
        return new self("Progress percentage must be between 0 and 100. Given: {$value}");
    }
}
