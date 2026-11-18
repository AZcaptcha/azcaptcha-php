<?php

namespace Azcaptcha\Tests;

use Azcaptcha\Client;
use Azcaptcha\Exception\BalanceException;
use Azcaptcha\Exception\CapacityException;
use Azcaptcha\Exception\ProcessingException;
use Azcaptcha\Exception\ResultException;
use PHPUnit\Framework\TestCase;

use function Azcaptcha\Tests\fixture;

final class ClientLegacyTest extends TestCase
{
    private array $sleeps = [];

    private function client(FakeTransport $transport, int $softId = 0): Client
    {
        return new Client('test-key', 'https://azcaptcha.com', $transport, 30.0, $softId,
            function (float $seconds): void {
                $this->sleeps[] = $seconds;
            });
    }

    public function testSubmitImageFixture(): void
    {
        $fx = fixture('submit-image');
        $transport = new FakeTransport($fx['response']['json'], ['status' => 1, 'request' => 'wxyz']);
        self::assertSame('wxyz', $this->client($transport)->solveImage("\x89PNG"));
        $call = $transport->calls[0];
        self::assertSame(['form', '/in.php'], [$call['kind'], $call['path']]);
        self::assertSame('base64', $call['payload']['method']);
        self::assertSame('test-key', $call['payload']['key']);
        self::assertSame('1', $call['payload']['json']);
        self::assertSame(base64_encode("\x89PNG"), $call['payload']['body']);
    }

    public function testSubmitRecaptchaV2Fixture(): void
    {
        $fx = fixture('submit-recaptcha-v2');
        $transport = new FakeTransport($fx['response']['json']);
        $id = $this->client($transport)->submit('userrecaptcha', [
            'googlekey' => '6Le-wvkSAAAAAPBMRTvw0Q4Muexq9bi0DJwx_mJ-',
            'pageurl' => 'https://example.com/login',
        ]);
        self::assertSame('1234567891', $id);
        self::assertArrayNotHasKey('soft_id', $transport->calls[0]['payload']);
    }

    public function testSoftIdSentWhenConfigured(): void
    {
        $transport = new FakeTransport(['status' => 1, 'request' => '1']);
        $this->client($transport, 4242)->submit('base64', ['body' => 'AA==']);
        self::assertSame(4242, $transport->calls[0]['payload']['soft_id']);
    }

    public function testProxyFieldsNormalised(): void
    {
        $transport = new FakeTransport(['status' => 1, 'request' => '1']);
        $this->client($transport)->submit('turnstile', [
            'sitekey' => '0x4AAA', 'pageurl' => 'https://e.com',
            'proxy' => 'socks5://u:p@1.2.3.4:1080',
        ]);
        $form = $transport->calls[0]['payload'];
        self::assertSame('u:p@1.2.3.4:1080', $form['proxy']);
        self::assertSame('SOCKS5', $form['proxytype']);
    }

    public function testZeroBalanceFixture(): void
    {
        $fx = fixture('error-zero-balance');
        $this->expectException(BalanceException::class);
        $this->client(new FakeTransport($fx['response']['json']))->submit('userrecaptcha', [
            'googlekey' => 'k', 'pageurl' => 'https://e.com',
        ]);
    }

    public function testNoSlotFixture(): void
    {
        $fx = fixture('error-no-slot');
        try {
            $this->client(new FakeTransport($fx['response']['json']))->submit('turnstile', [
                'sitekey' => 'k', 'pageurl' => 'https://e.com',
            ]);
            self::fail('expected CapacityException');
        } catch (CapacityException $error) {
            self::assertSame('ERROR_NO_SLOT_AVAILABLE', $error->errorCode);
            self::assertTrue($error->retryable);
        }
    }

    public function testPollReadyFixture(): void
    {
        $fx = fixture('poll-ready-recaptcha');
        self::assertSame('03AGdBq26...', $this->client(new FakeTransport($fx['response']['json']))->result('1234567891'));
    }

    public function testPollProcessingFixture(): void
    {
        $fx = fixture('poll-processing');
        $this->expectException(ProcessingException::class);
        $this->client(new FakeTransport($fx['response']['json']))->result('1234567891');
    }

    public function testWaitResultDelays(): void
    {
        $transport = new FakeTransport(
            fixture('poll-processing')['response']['json'],
            fixture('poll-ready-recaptcha')['response']['json']
        );
        self::assertSame('03AGdBq26...', $this->client($transport)->waitResult('1234567891'));
        self::assertSame([1.0], $this->sleeps);
    }

    public function testWaitResultBacksOffOnNoSlot(): void
    {
        $transport = new FakeTransport(
            ['status' => 0, 'request' => 'ERROR_NO_SLOT_AVAILABLE'],
            ['status' => 1, 'request' => 'token']
        );
        self::assertSame('token', $this->client($transport)->waitResult('1'));
        self::assertSame([10.0], $this->sleeps);
    }

    public function testWaitResultNonRetryableRaisesImmediately(): void
    {
        $transport = new FakeTransport(['status' => 0, 'request' => 'ERROR_CAPTCHA_UNSOLVABLE']);
        $this->expectException(ResultException::class);
        $this->client($transport)->waitResult('1');
    }

    public function testWaitResultTimeout(): void
    {
        $transport = new FakeTransport(['status' => 0, 'request' => 'CAPCHA_NOT_READY']);
        $this->expectException(ResultException::class);
        $this->client($transport)->waitResult('1', 0.001);
    }

    public function testBalance(): void
    {
        self::assertSame(12.5, $this->client(new FakeTransport(['status' => 1, 'request' => 12.5]))->balance());
    }

    public function testReportBad(): void
    {
        $transport = new FakeTransport(['status' => 1, 'request' => 'OK_REPORT_RECORDED']);
        self::assertTrue($this->client($transport)->reportBad('123'));
    }

    public function testMissingKeyRejected(): void
    {
        $this->expectException(\Azcaptcha\Exception\AuthException::class);
        new Client('');
    }
}
