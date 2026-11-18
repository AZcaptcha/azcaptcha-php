<?php

namespace Azcaptcha\Tests;

use Azcaptcha\Transport\TransportInterface;

/** Replays canned JSON bodies and records every call. */
final class FakeTransport implements TransportInterface
{
    /** @var list<array<string, mixed>> */
    private array $responses;
    /** @var list<array{kind: string, path: string, payload: array}> */
    public array $calls = [];

    public function __construct(array ...$responses)
    {
        $this->responses = $responses;
    }

    public function postForm(string $path, array $data): array
    {
        return $this->next('form', $path, $data);
    }

    public function postJson(string $path, array $payload): array
    {
        return $this->next('json', $path, $payload);
    }

    public function get(string $path, array $params): array
    {
        return $this->next('get', $path, $params);
    }

    private function next(string $kind, string $path, array $payload): array
    {
        $this->calls[] = ['kind' => $kind, 'path' => $path, 'payload' => $payload];
        if ($this->responses === []) {
            throw new \RuntimeException("unexpected call: {$kind} {$path}");
        }

        return array_shift($this->responses);
    }
}
