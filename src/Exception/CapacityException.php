<?php

namespace Azcaptcha\Exception;

/** No solver slot available — retry with backoff. */
class CapacityException extends CaptchaException
{
    public bool $retryable = true;
}
