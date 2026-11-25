<?php

namespace App\Services\Betting;

use RuntimeException;

class GenerationCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A geração das apostas foi cancelada.');
    }
}
