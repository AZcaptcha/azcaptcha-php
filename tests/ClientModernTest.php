<?php

namespace Azcaptcha\Tests;

use Azcaptcha\Client;
use Azcaptcha\Exception\BalanceException;
use Azcaptcha\Exception\ResultException;
use PHPUnit\Framework\TestCase;

use function Azcaptcha\Tests\fixture;

final class ClientModernTest extends TestCase
{
    public function testCreateTaskFixture(): void
    {
        $fx = fixture('modern-create-task');
        $transport = new FakeTransport($fx['response']['json']);
        $id = (new Client('test-key', 'https://azcaptcha.com', $transport, 30.0, 0))
            ->createTask('RecaptchaV2TaskProxyless', [
                'websiteURL' => 'https://example.com/login',
                'websiteKey' => '6Le-wvkSAAAAAPBMRTvw0Q4Muexq9bi0DJwx_mJ-',
            ]);
        self::assertSame(1234567892, $id);
        $call = $transport->calls[0];
        self::assertSame(['json', '/createTask'], [$call['kind'], $call['path']]);
        self::assertSame('test-key', $call['payload']['clientKey']);
        self::assertSame($fx['request']['json']['task'], $call['payload']['task']);
    }

    public function testGetTaskResultFixture(): void
    {
        $fx = fixture('modern-task-ready');
        $transport = new FakeTransport($fx['response']['json']);
        $body = (new Client('test-key', 'https://azcaptcha.com', $transport))->getTaskResult(1234567892);
        self::assertSame('ready', $body['status']);
        self::assertSame('03AGdBq26...', $body['solution']['gRecaptchaResponse']);
    }

    public function testWaitTaskResultPollsUntilReady(): void
    {
        $transport = new FakeTransport(
            ['errorId' => 0, 'status' => 'processing'],
            ['errorId' => 0, 'status' => 'ready', 'solution' => ['token' => 'abc']]
        );
        $sleeps = [];
        $client = new Client('test-key', 'https://azcaptcha.com', $transport, 30.0, 0,
            function (float $seconds) use (&$sleeps): void {
                $sleeps[] = $seconds;
            });
        self::assertSame(['token' => 'abc'], $client->waitTaskResult(1));
        self::assertSame([1.0], $sleeps);
    }

    public function testModernErrorMapsToTypedException(): void
    {
        $transport = new FakeTransport(['errorId' => 12, 'errorCode' => 'ERROR_ZERO_BALANCE']);
        $this->expectException(BalanceException::class);
        (new Client('test-key', 'https://azcaptcha.com', $transport))
            ->createTask('RecaptchaV2TaskProxyless');
    }

    public function testWaitTaskResultTimeout(): void
    {
        $transport = new FakeTransport(['errorId' => 0, 'status' => 'processing']);
        $this->expectException(ResultException::class);
        $client = new Client('test-key', 'https://azcaptcha.com', $transport, 30.0, 0, static function (): void {
        });
        $client->waitTaskResult(1, 0.001);
    }
}
