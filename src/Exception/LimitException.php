<?php

namespace Azcaptcha\Exception;

/** Daily limit exceeded (terminal) or too many threads (retryable). */
class LimitException extends CaptchaException
{
}
