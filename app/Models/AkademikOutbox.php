<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AkademikOutbox extends Model
{
    protected $table = 'akademik_outbox';

    protected $primaryKey = 'id_akademik_outbox';

    protected $guarded = ['id_akademik_outbox'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function idempotencyKey(string $event, string|int $aggregateId, array $payload): string
    {
        self::urutkan($payload);

        return hash('sha256', $event.'|'.$aggregateId.'|'.json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $payload */
    public static function enqueue(string $aggregateType, string|int $aggregateId, string $event, string $method, string $path, array $payload): self
    {
        $key = self::idempotencyKey($event, $aggregateId, $payload);

        return self::query()->firstOrCreate(['idempotency_key' => $key], [
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'event_type' => $event,
            'method' => strtoupper($method),
            'path' => $path,
            'payload' => $payload,
            'available_at' => now(),
        ]);
    }

    private static function urutkan(array &$value): void
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                self::urutkan($item);
            }
        }
    }
}