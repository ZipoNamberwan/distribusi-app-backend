<?php

namespace App\Exceptions;

use RuntimeException;

class ImportException extends RuntimeException
{
    public function __construct(
        string $systemMessage,
        private readonly string $userMessage,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($systemMessage, $code, $previous);
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
