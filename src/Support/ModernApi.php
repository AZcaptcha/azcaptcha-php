<?php

namespace Azcaptcha\Support;

use Azcaptcha\Exception\ErrorFactory;

/** Modern createTask/getTaskResult surface, composed into the client. */
trait ModernApi
{
    public function createTask(string $type, array $task = []): int
    {
        $payload = ['clientKey' => $this->apiKey, 'task' => ['type' => $type] + Params::clean($task)];
        if ($this->softId > 0) {
            $payload['softId'] = $this->softId;
        }
        $body = $this->http->postJson('/createTask', $payload);
        $this->raiseModern($body);

        return (int) ($body['taskId'] ?? 0);
    }

    public function getTaskResult(int $taskId): array
    {
        $body = $this->http->postJson('/getTaskResult', [
            'clientKey' => $this->apiKey, 'taskId' => $taskId,
        ]);
        $this->raiseModern($body);

        return $body;
    }

    public function waitTaskResult(int $taskId, float $timeout = 300.0, float $pollInterval = self::POLL_INTERVAL): array
    {
        $deadline = microtime(true) + $timeout;
        $delay = self::FIRST_DELAY;
        while (true) {
            $body = $this->getTaskResult($taskId);
            if (($body['status'] ?? '') === 'ready') {
                return $body['solution'] ?? [];
            }
            if (microtime(true) + $delay >= $deadline) {
                throw ErrorFactory::make('ERROR_TIMEOUT', "task {$taskId} not ready");
            }
            ($this->sleep)($delay);
            $delay = $pollInterval;
        }
    }

}
