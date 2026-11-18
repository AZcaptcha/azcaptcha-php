<?php

namespace Azcaptcha\Exception;

/** CAPCHA_NOT_READY — poll again. */
class ProcessingException extends CaptchaException
{
    public bool $retryable = true;
}
