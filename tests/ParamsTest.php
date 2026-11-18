<?php

namespace Azcaptcha\Tests;

use Azcaptcha\Support\Params;
use PHPUnit\Framework\TestCase;

final class ParamsTest extends TestCase
{
    public function testProxyHostPort(): void
    {
        self::assertSame(
            ['proxy' => '1.2.3.4:8080', 'proxytype' => 'HTTP'],
            Params::normalizeProxy('1.2.3.4:8080')
        );
    }

    public function testProxyUserInfoKept(): void
    {
        self::assertSame('user:pass@1.2.3.4:8080', Params::normalizeProxy('user:pass@1.2.3.4:8080')['proxy']);
    }

    public function testProxyHostPortUserPassReordered(): void
    {
        self::assertSame('user:pass@1.2.3.4:8080', Params::normalizeProxy('1.2.3.4:8080:user:pass')['proxy']);
    }

    public function testSchemeNeverSent(): void
    {
        foreach (['http' => 'HTTP', 'https' => 'HTTP', 'socks4' => 'SOCKS4', 'socks5' => 'SOCKS5'] as $scheme => $expected) {
            $out = Params::normalizeProxy($scheme . '://user:pass@1.2.3.4:1080');
            self::assertSame('user:pass@1.2.3.4:1080', $out['proxy'], $scheme);
            self::assertSame($expected, $out['proxytype'], $scheme);
        }
    }

    public function testProxyWithoutPortRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Params::normalizeProxy('1.2.3.4');
    }

    public function testImageDataUri(): void
    {
        self::assertSame('AAAA', Params::imageBody('data:image/png;base64,AAAA'));
    }

    public function testImageFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cap');
        file_put_contents($path, "\x89PNG");
        self::assertSame(base64_encode("\x89PNG"), Params::imageBody($path));
        unlink($path);
    }

    public function testImageRawBytes(): void
    {
        $bytes = "\xFF\xD8\xFF\xE0binary";
        self::assertSame(base64_encode($bytes), Params::imageBody($bytes));
    }

    public function testImageBase64Passthrough(): void
    {
        self::assertSame('iVBORw0KGgo=', Params::imageBody("iVBORw0KGgo=\n"));
    }
}
