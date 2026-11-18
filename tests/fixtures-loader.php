<?php

namespace Azcaptcha\Tests;

function fixture(string $name): array
{
    $path = __DIR__ . '/fixtures/' . $name . '.json';
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new \RuntimeException("bad fixture: {$name}");
    }

    return $data;
}
