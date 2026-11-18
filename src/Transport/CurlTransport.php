<?php

namespace Azcaptcha\Transport;

use Azcaptcha\Exception\NetworkException;
use Azcaptcha\Exception\ServiceException;

/** Default transport — ext-curl, JSON envelopes only. */
final class CurlTransport implements TransportInterface
{
    private string $baseUrl;
    private float $timeout;

    public function __construct(string $baseUrl = 'https://azcaptcha.com', float $timeout = 30.0)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function postForm(string $path, array $data): array
    {
        return $this->request('POST', $path, http_build_query(
            array_filter($data, static fn ($value): bool => $value !== null)
        ), 'application/x-www-form-urlencoded');
    }

    public function postJson(string $path, array $payload): array
    {
        return $this->request('POST', $path, json_encode($payload), 'application/json');
    }

    public function get(string $path, array $params): array
    {
        $query = http_build_query(array_filter($params, static fn ($value): bool => $value !== null));
        return $this->request('GET', $path . '?' . $query, null, null);
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?string $body, ?string $contentType): array
    {
        $curl = curl_init($this->baseUrl . $path);
        if ($curl === false) {
            throw new NetworkException('ERROR_NETWORK', 'cannot initialise curl');
        }
        $headers = ['Accept: application/json', 'User-Agent: azcaptcha-php/1.0.0'];
        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) ceil($this->timeout),
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->timeout),
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false || $raw === '') {
            throw new NetworkException('ERROR_NETWORK', $error !== '' ? $error : 'empty response');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ServiceException('ERROR_BAD_RESPONSE', substr((string) $raw, 0, 200));
        }
        if ($status >= 500) {
            throw new ServiceException('ERROR_HTTP_5XX', 'HTTP ' . $status);
        }

        return $decoded;
    }
}
