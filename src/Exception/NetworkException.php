<?php

namespace Azcaptcha\Exception;

/** Transport-level failure — retryable. */
class NetworkException extends CaptchaException
{
    public bool $retryable = true;
}
