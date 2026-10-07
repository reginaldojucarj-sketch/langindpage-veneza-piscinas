<?php

namespace App\Exceptions;

use RuntimeException;

final class OrderConflict extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'revision_conflict')
    {
        parent::__construct($message);
    }
}
