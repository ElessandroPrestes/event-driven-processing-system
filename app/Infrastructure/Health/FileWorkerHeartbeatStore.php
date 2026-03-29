<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\WorkerHeartbeatStore;
use App\Application\Health\DataTransferObjects\WorkerHeartbeatData;
use Carbon\CarbonImmutable;
use JsonException;
use RuntimeException;
use Throwable;

final class FileWorkerHeartbeatStore implements WorkerHeartbeatStore
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $workerName, array $context = []): WorkerHeartbeatData
    {
        $heartbeat = new WorkerHeartbeatData(
            workerName: $workerName,
            recordedAt: CarbonImmutable::now(),
            context: $context,
        );

        $directory = $this->directory();

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create worker heartbeat directory [%s].', $directory));
        }

        $payload = json_encode([
            'worker' => $heartbeat->workerName,
            'recorded_at' => $heartbeat->recordedAt->toIso8601String(),
            'context' => $heartbeat->context,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $path = $this->pathFor($workerName);
        $temporaryPath = sprintf('%s.%s.tmp', $path, uniqid('', true));

        file_put_contents($temporaryPath, $payload, LOCK_EX);
        rename($temporaryPath, $path);

        return $heartbeat;
    }

    public function latest(string $workerName): ?WorkerHeartbeatData
    {
        $path = $this->pathFor($workerName);

        if (! is_file($path)) {
            return null;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $recordedAt = $payload['recorded_at'] ?? null;

        if (! is_string($recordedAt) || trim($recordedAt) === '') {
            return null;
        }

        try {
            $timestamp = CarbonImmutable::parse($recordedAt);
        } catch (Throwable) {
            return null;
        }

        $storedWorkerName = $payload['worker'] ?? $workerName;
        $context = $payload['context'] ?? [];

        return new WorkerHeartbeatData(
            workerName: is_string($storedWorkerName) && trim($storedWorkerName) !== '' ? $storedWorkerName : $workerName,
            recordedAt: $timestamp,
            context: is_array($context) ? $context : [],
        );
    }

    private function directory(): string
    {
        return rtrim((string) config('event_pipeline.health.workers.heartbeat_dir'), '/');
    }

    private function pathFor(string $workerName): string
    {
        return sprintf('%s/%s.json', $this->directory(), $this->normalizeWorkerName($workerName));
    }

    private function normalizeWorkerName(string $workerName): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9._-]/', '-', $workerName);

        return trim(is_string($normalized) ? $normalized : $workerName, '-');
    }
}
