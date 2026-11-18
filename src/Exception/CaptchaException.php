<?php

namespace Azcaptcha\Exception;

/**
 * Base error. $errorCode carries the raw API code; $retryable mirrors the
 * shared taxonomy in sdk-spec.
 */
class CaptchaException extends \RuntimeException
{
    public string $errorCode;
    public bool $retryable = false;

    public function __construct(string $errorCode = 'ERROR_UNKNOWN', ?string $message = null, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message ?? $errorCode, 0, $previous);
    }
}
