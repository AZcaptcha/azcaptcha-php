<?php

namespace Azcaptcha\Exception;

/** Maps API codes from spec/errors.json to typed exceptions. */
final class ErrorFactory
{
    private const TABLE = [
        'CAPCHA_NOT_READY' => [ProcessingException::class, true],
        'ERROR_KEY_DOES_NOT_EXIST' => [AuthException::class, false],
        'ERROR_ACCOUNT_SUSPENDED' => [AuthException::class, false],
        'ERROR_ZERO_BALANCE' => [BalanceException::class, false],
        'ERROR_BAD_PARAMETERS' => [RequestException::class, false],
        'ERROR_WRONG_ID_FORMAT' => [RequestException::class, false],
        'ERROR_NO_SUCH_CAPCHA_ID' => [RequestException::class, false],
        'ERROR_TASK_NOT_FOUND' => [RequestException::class, false],
        'ERROR_TASK_NOT_SUPPORTED' => [RequestException::class, false],
        'ERROR_WRONG_GOOGLEKEY' => [RequestException::class, false],
        'ERROR_RECAPTCHA_INVALID_SITEKEY' => [RequestException::class, false],
        'ERROR_WRONG_PAGEURL' => [RequestException::class, false],
        'ERROR_IMAGE_NOT_FOUND' => [RequestException::class, false],
        'ERROR_IMAGE_TYPE_NOT_SUPPORTED' => [RequestException::class, false],
        'ERROR_ZERO_CAPTCHA_FILESIZE' => [RequestException::class, false],
        'ERROR_TOO_BIG_CAPTCHA_FILESIZE' => [RequestException::class, false],
        'ERROR_DAILY_LIMIT_EXCEEDED' => [LimitException::class, false],
        'ERROR_TOO_MANY_THREADS' => [LimitException::class, true],
        'ERROR_NO_SLOT_AVAILABLE' => [CapacityException::class, true],
        'ERROR_MAINTENANCE' => [ServiceException::class, false],
        'ERROR_TIMEOUT' => [ResultException::class, false],
        'ERROR_TTL' => [ResultException::class, false],
        'ERROR_TOKEN_EXPIRED' => [ResultException::class, false],
        'ERROR_CAPTCHA_UNSOLVABLE' => [ResultException::class, false],
        'ERROR_PROXY_REQUIRED' => [ProxyException::class, false],
        'ERROR_PROXY_NOT_AUTHORIZED' => [ProxyException::class, false],
        'ERROR_PROXY_BANNED' => [ProxyException::class, false],
        'ERROR_PINGBACK_NOT_VERIFIED' => [PingbackException::class, false],
        'ERROR_MODULE_ACCESS_DENIED' => [ModuleException::class, false],
        'ERROR_MODULE_NOT_TRAINED' => [ModuleException::class, false],
    ];

    public static function make(string $code, ?string $message = null): CaptchaException
    {
        [$class, $retryable] = self::TABLE[$code] ?? [UnknownException::class, false];
        $error = new $class($code, $message);
        $error->retryable = $retryable;
        return $error;
    }

    /** @return array<string, bool> code => retryable */
    public static function taxonomy(): array
    {
        return array_map(static fn (array $row): bool => $row[1], self::TABLE);
    }
}
