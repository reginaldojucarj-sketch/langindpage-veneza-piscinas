<?php

namespace App\Exceptions;

use RuntimeException;

final class PublishedListingChanged extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The published article list changed while the page was being read.');
    }
}
