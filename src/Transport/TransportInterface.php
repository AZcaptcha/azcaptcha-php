<?php

namespace Azcaptcha\Transport;

interface TransportInterface
{
    /** @return array<string, mixed> */
    public function postForm(string $path, array $data): array;

    /** @return array<string, mixed> */
    public function postJson(string $path, array $payload): array;

    /** @return array<string, mixed> */
    public function get(string $path, array $params): array;
}
