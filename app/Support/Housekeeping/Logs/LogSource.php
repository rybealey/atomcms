<?php

namespace App\Support\Housekeeping\Logs;

use Closure;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * One log table as the explorer reads it: where its time lives and in what
 * shape, which columns hold players and the room, what free text searches,
 * and how a row reads as a sentence.
 *
 * `time` is the column; `timeType` says how it is stored - `int` / `double`
 * (unix seconds), `char` (unix seconds as a string, logs_client_trade) or
 * `timestamp` - so a filter compares against the column itself and keeps its
 * index.
 */
final readonly class LogSource
{
    /**
     * @param  list<string>  $players  columns holding user ids; the first is the actor
     * @param  list<string>  $search  columns free text matches against
     * @param  list<string>  $columns  columns to select
     * @param  Closure(stdClass, array<int, array{id: int, username: string, look: string}>, array<int, string>, int|null): array<string, mixed>  $describe
     * @param  Closure(Builder, int): void|null  $forPlayer  a custom player filter (activity_log)
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $kind,
        public ?string $permission,
        public string $time,
        public string $timeType,
        public array $players,
        public ?string $room,
        public array $search,
        public array $columns,
        public Closure $describe,
        public ?Closure $forPlayer = null,
        public ?Closure $scope = null,
        public string $table = '',
    ) {}

    public function table(): string
    {
        return $this->table !== '' ? $this->table : $this->key;
    }
}
