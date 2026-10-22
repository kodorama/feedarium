<?php

namespace Tests\Support;

use Mockery;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Job as QueueJob;

/** Reconstructs the encrypted command exactly as Laravel's queue handler does. */
final class QueuePayloadRehydrator
{
    /** @return array<string, mixed> */
    public static function capture(object $job): array
    {
        $manager = Queue::getFacadeRoot()->queue ?? app(QueueManager::class);
        $queue = $manager->connection('sync');
        $method = new \ReflectionMethod($queue, 'createPayload');
        $method->setAccessible(true);

        return json_decode($method->invoke($queue, $job, 'default'), true, 32, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $payload */
    public static function fresh(array $payload, bool $expectRelease = false): object
    {
        $command = app(Encrypter::class)->decrypt($payload['data']['command']);
        $job = unserialize($command, ['allowed_classes' => true]);
        $wrapper = Mockery::mock(QueueJob::class);
        $wrapper->shouldReceive('getJobId')->andReturn($payload['uuid']);
        $wrapper->shouldReceive('payload')->andReturn($payload);
        $wrapper->shouldReceive('attempts')->andReturn(1);
        if ($expectRelease) {
            $wrapper->shouldReceive('release')->once()->withArgs(static fn (int $delay): bool => $delay > 0);
        } else {
            $wrapper->shouldReceive('release')->never();
        }
        $job->setJob($wrapper);

        return $job;
    }
}
