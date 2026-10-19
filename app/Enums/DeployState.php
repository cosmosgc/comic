<?php

namespace App\Enums;

/**
 * Lifecycle of a background `deploy:host` run. The same values live in the
 * status JSON file polled by the admin deploy panel, so they must not change
 * without updating the front-end strings.
 */
enum DeployState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Done = 'done';
    case Failed = 'failed';

    /**
     * States in which the worker may still be alive (the deploy lock holds).
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::Queued->value,
            self::Running->value,
            self::Cancelling->value,
        ];
    }
}
