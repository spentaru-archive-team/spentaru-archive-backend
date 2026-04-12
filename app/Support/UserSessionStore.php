<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;

class UserSessionStore
{
    private const KEY_PREFIX = 'user_session:';

    public function put(string $sessionId, array $payload, int $ttlSeconds = 7200): void
    {
        Redis::connection(config('session.connection', 'default'))->setex(
            $this->key($sessionId),
            $ttlSeconds,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    public function find(string $sessionId): ?array
    {
        $payload = Redis::connection(config('session.connection', 'default'))->get($this->key($sessionId));

        if ($payload === null) {
            return null;
        }

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public function forget(string $sessionId): void
    {
        Redis::connection(config('session.connection', 'default'))->del($this->key($sessionId));
    }

    public function touch(string $sessionId, int $ttlSeconds = 7200): bool
    {
        return (bool) Redis::connection(config('session.connection', 'default'))->expire(
            $this->key($sessionId),
            $ttlSeconds
        );
    }

    private function key(string $sessionId): string
    {
        return self::KEY_PREFIX.$sessionId;
    }
}
