<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyKeyConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This idempotency key was already used with a different order.');
    }
}
