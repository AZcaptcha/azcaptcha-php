<?php

namespace Azcaptcha;

use Azcaptcha\Exception\CapacityException;
use Azcaptcha\Exception\ErrorFactory;
use Azcaptcha\Exception\LimitException;
use Azcaptcha\Exception\ProcessingException;
use Azcaptcha\Support\ModernApi;
use Azcaptcha\Support\Params;
use Azcaptcha\Support\Solvers;
use Azcaptcha\Transport\CurlTransport;
use Azcaptcha\Transport\TransportInterface;

/**
 * AZcaptcha client — legacy (in.php/res.php) and modern (createTask) APIs.
 * Contract: https://github.com/AZcaptcha/sdk-spec (spec-v1.0.0).
 */
final class Client
{
    use ModernApi;
    use Solvers;

    public const SOFT_ID = 0;
    public const NOT_READY = 'CAPCHA_NOT_READY';
    public const POLL_INTERVAL = 5.0;
    public const FIRST_DELAY = 1.0;
    public const NO_SLOT_BACKOFF = 10.0;

    private string $apiKey;
    private int $softId;
    private TransportInterface $http;
    /** @var callable(float): void */
    private $sleep;

    public function __construct(
        string $apiKey,
        string $host = 'https://azcaptcha.com',
        ?TransportInterface $transport = null,
        float $timeout = 30.0,
        int $softId = self::SOFT_ID,
        ?callable $sleep = null
    ) {
        if ($apiKey === '') {
            throw ErrorFactory::make('ERROR_KEY_DOES_NOT_EXIST', 'apiKey is required');
        }
        $this->apiKey = $apiKey;
        $this->softId = $softId;
        $this->http = $transport ?? new CurlTransport($host, $timeout);
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1000000));
        };
    }

    // ── legacy API ────────────────────────────────────────────────────
    public function submit(string $method, array $params = []): string
    {
        $form = ['key' => $this->apiKey, 'method' => $method, 'json' => '1'];
        if ($this->softId > 0) {
            $form['soft_id'] = $this->softId;
        }
        if (!empty($params['proxy'])) {
            $form += Params::normalizeProxy((string) $params['proxy']);
            unset($params['proxy']);
        }
        if (!empty($params['proxytype'])) {
            $form['proxytype'] = strtoupper((string) $params['proxytype']);
            unset($params['proxytype']);
        }
        $form += Params::clean($params);

        return $this->unwrap($this->http->postForm('/in.php', $form), 'submit');
    }

    public function result(string $taskId): string
    {
        $body = $this->http->get('/res.php', [
            'key' => $this->apiKey, 'action' => 'get', 'id' => $taskId, 'json' => '1',
        ]);
        if (($body['request'] ?? '') === self::NOT_READY) {
            throw new ProcessingException(self::NOT_READY);
        }

        return $this->unwrap($body, 'result');
    }

    public function waitResult(string $taskId, ?float $timeout = null, float $pollInterval = self::POLL_INTERVAL): string
    {
        $deadline = microtime(true) + ($timeout ?? 300.0);
        $delay = self::FIRST_DELAY;
        $backoff = self::NO_SLOT_BACKOFF;
        while (true) {
            try {
                return $this->result($taskId);
            } catch (ProcessingException $error) {
                $pause = $delay;
                $delay = $pollInterval;
            } catch (CapacityException $error) {
                $pause = $backoff;
                $backoff = min($backoff * 1.5, 60.0);
            } catch (LimitException $error) {
                if (!$error->retryable) {
                    throw $error;
                }
                $pause = $backoff;
                $backoff = min($backoff * 1.5, 60.0);
            }
            if (microtime(true) + $pause >= $deadline) {
                throw ErrorFactory::make('ERROR_TIMEOUT', "task {$taskId} not ready");
            }
            ($this->sleep)($pause);
        }
    }

    public function balance(): float
    {
        $body = $this->http->get('/res.php', [
            'key' => $this->apiKey, 'action' => 'getbalance', 'json' => '1',
        ]);

        return (float) $this->unwrap($body, 'balance');
    }

    public function reportBad(string $taskId): bool
    {
        $body = $this->http->get('/res.php', [
            'key' => $this->apiKey, 'action' => 'reportbad', 'id' => $taskId, 'json' => '1',
        ]);

        return strncmp(strtoupper($this->unwrap($body, 'reportbad')), 'OK', 2) === 0;
    }

    // ── modern API ────────────────────────────────────────────────────
    // ── helpers ───────────────────────────────────────────────────────
    public function solve(string $method, array $params = [], ?float $timeout = null): string
    {
        $taskId = $this->submit($method, $params);
        $deadline = $timeout ?? (Params::DEFAULT_TIMEOUTS[$method] ?? 300);

        return $this->waitResult($taskId, (float) $deadline);
    }

    public function solveImage(string $image, array $params = []): string
    {
        return $this->solve('base64', ['body' => Params::imageBody($image)] + $params);
    }

    // ── internals ─────────────────────────────────────────────────────
    private function unwrap(array $body, string $action): string
    {
        $request = $body['request'] ?? null;
        if (is_string($request) && strncmp($request, 'ERROR_', 6) === 0) {
            throw ErrorFactory::make($request);
        }
        if ((int) ($body['status'] ?? 0) !== 1) {
            throw ErrorFactory::make(is_string($request) ? $request : 'ERROR_' . strtoupper($action) . '_FAILED');
        }

        return $request === null ? '' : (string) $request;
    }

    private function raiseModern(array $body): void
    {
        if ((int) ($body['errorId'] ?? 0) !== 0) {
            throw ErrorFactory::make(
                (string) ($body['errorCode'] ?? 'ERROR_UNKNOWN'),
                (string) ($body['errorDescription'] ?? '')
            );
        }
    }
}
