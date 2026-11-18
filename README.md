# AZcaptcha PHP SDK

Official PHP client for [AZcaptcha](https://azcaptcha.com) — image captchas,
reCAPTCHA v2/v3, hCaptcha, Cloudflare Turnstile, FunCaptcha and GeeTest.

- 2Captcha-compatible method surface (`solve`, `result`, `balance`, `reportBad`)
  plus the modern JSON API (`createTask`, `getTaskResult`).
- Typed exceptions for every API error code
  ([spec-v1.0.0](https://github.com/AZcaptcha/sdk-spec)).
- Contract polling with `NO_SLOT` backoff and per-type deadlines.
- No runtime dependencies beyond `ext-curl` and `ext-json`, PHP 7.4+.

## Install

```bash
composer require azcaptcha/azcaptcha-php
```

## Quickstart

```php
use Azcaptcha\Client;

$client = new Client('YOUR_API_KEY');

// reCAPTCHA v2
$token = $client->solveRecaptchaV2(
    '6Le-wvkSAAAAAPBMRTvw0Q4Muexq9bi0DJwx_mJ-',
    'https://example.com/login'
);

// Image captcha (file path, base64, data: URI or raw bytes)
$answer = $client->solveImage('captcha.png');

// hCaptcha / Turnstile
$token = $client->solveHCaptcha('SITEKEY', 'https://example.com/');
$token = $client->solveTurnstile('0x4AAAAAAA...', 'https://example.com/');
```

### Low-level (2Captcha style)

```php
$taskId = $client->submit('userrecaptcha', [
    'googlekey' => 'SITEKEY',
    'pageurl' => 'https://example.com/',
]);
$token = $client->waitResult($taskId, 300);  // polls 1s then every 5s
```

### Modern JSON API

```php
$taskId = $client->createTask('RecaptchaV2TaskProxyless', [
    'websiteURL' => 'https://example.com/login',
    'websiteKey' => 'SITEKEY',
]);
$solution = $client->waitTaskResult($taskId);
echo $solution['gRecaptchaResponse'];
```

## Proxies

```php
$client->solveRecaptchaV2($sitekey, $pageurl, false, false, [
    'proxy' => 'socks5://user:pass@1.2.3.4:1080',
]);
```

Any accepted spelling works (`scheme://user:pass@host:port`,
`host:port:user:pass`, `user:pass@host:port`). The scheme is never sent
upstream; `proxytype` is derived (`http`/`https` → `HTTP`, `socks4`, `socks5`).

## Errors

```php
use Azcaptcha\Exception\BalanceException;
use Azcaptcha\Exception\CapacityException;
use Azcaptcha\Exception\ResultException;

try {
    $token = $client->solveTurnstile($sitekey, $pageurl);
} catch (BalanceException $e) {
    // ERROR_ZERO_BALANCE
} catch (CapacityException $e) {
    // ERROR_NO_SLOT_AVAILABLE — retry with backoff
} catch (ResultException $e) {
    // unsolvable / timeout / expired
}
```

Every exception exposes `$errorCode` and `$retryable`. `waitResult` retries only
codes marked retryable and backs off 10s ×1.5 up to 60s.

## Timeouts

Defaults match server settings: image 120s, hCaptcha 120s, Turnstile 180s,
reCAPTCHA and others 300s. Override per call:
`solve('turnstile', $params, 90)` or `waitResult($id, 600)`.

## Custom transport

```php
$client = new Client('KEY', 'https://azcaptcha.com', new MyTransport());
```

`MyTransport` implements `Azcaptcha\Transport\TransportInterface`.

## Links

- API contract: <https://github.com/AZcaptcha/sdk-spec>
- Docs: <https://azcaptcha.com/docs>
- PHP guide: <https://azcaptcha.com/lang/php>
- Changelog: [CHANGELOG.md](CHANGELOG.md)

## License

MIT — see [LICENSE](LICENSE).
