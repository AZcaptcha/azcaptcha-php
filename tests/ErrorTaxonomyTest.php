<?php

namespace Azcaptcha\Tests;

use Azcaptcha\Exception\AuthException;
use Azcaptcha\Exception\BalanceException;
use Azcaptcha\Exception\CaptchaException;
use Azcaptcha\Exception\ErrorFactory;
use Azcaptcha\Exception\UnknownException;
use PHPUnit\Framework\TestCase;

final class ErrorTaxonomyTest extends TestCase
{
    private array $spec;

    protected function setUp(): void
    {
        $this->spec = json_decode(
            (string) file_get_contents(__DIR__ . '/fixtures/errors.json'),
            true
        );
    }

    public function testEveryCodeMapsToTypedError(): void
    {
        $codes = array_merge(array_keys($this->spec['errors']), array_keys($this->spec['envelope_codes']));
        foreach ($codes as $code) {
            $error = ErrorFactory::make($code);
            self::assertNotInstanceOf(UnknownException::class, $error, $code);
            self::assertSame($code, $error->errorCode);
        }
    }

    public function testRetryableFlagsMatchSpec(): void
    {
        $taxonomy = ErrorFactory::taxonomy();
        foreach ($this->spec['errors'] as $code => $meta) {
            self::assertSame($meta['retryable'], $taxonomy[$code], $code);
            self::assertSame($meta['retryable'], ErrorFactory::make($code)->retryable, $code);
        }
        foreach ($this->spec['envelope_codes'] as $code => $meta) {
            self::assertSame($meta['retryable'], ErrorFactory::make($code)->retryable, $code);
        }
    }

    public function testUnknownCodeIsForwardCompatible(): void
    {
        $error = ErrorFactory::make('ERROR_FROM_THE_FUTURE');
        self::assertInstanceOf(UnknownException::class, $error);
        self::assertFalse($error->retryable);
    }

    public function testRepresentativeCategories(): void
    {
        self::assertInstanceOf(AuthException::class, ErrorFactory::make('ERROR_KEY_DOES_NOT_EXIST'));
        self::assertInstanceOf(BalanceException::class, ErrorFactory::make('ERROR_ZERO_BALANCE'));
        self::assertTrue(ErrorFactory::make('ERROR_NO_SLOT_AVAILABLE')->retryable);
        self::assertInstanceOf(CaptchaException::class, ErrorFactory::make('ERROR_CAPTCHA_UNSOLVABLE'));
    }
}
