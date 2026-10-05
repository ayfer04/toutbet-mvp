<?php

declare(strict_types=1);

namespace App\Service;

final class WagerRejected extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
