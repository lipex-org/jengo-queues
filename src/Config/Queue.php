<?php

declare(strict_types=1);

namespace Jengo\Queues\Config;

use CodeIgniter\Config\BaseConfig;

class Queue extends BaseConfig
{
    /**
     * Default queue connection to use.
     * Supported: 'sync', 'database', 'redis', 'null'
     */
    public string $default = 'sync';

    /**
     * Prefix for queue names (useful for Redis keys and tables).
     */
    public string $prefix = 'jengo_';

    /**
     * Connections configuration.
     */
    public array $connections = [
        'sync' => [
            'driver' => 'sync',
            'queue'  => 'default',
        ],
        'database' => [
            'driver'       => 'database',
            'table'        => 'queue_jobs',
            'queue'        => 'default',
            'retryAfter'   => 90,
            'DBGroup'      => 'default',
        ],
        'redis' => [
            'driver'     => 'redis',
            'host'       => '127.0.0.1',
            'password'   => null,
            'port'       => 6379,
            'database'   => 0,
            'queue'      => 'default',
            'retryAfter' => 90,
            'timeout'    => 5.0,
        ],
        'null' => [
            'driver' => 'null',
        ],
    ];

    /**
     * Failed jobs storage settings.
     */
    public array $failed = [
        'driver'   => 'database',
        'table'    => 'queue_failed_jobs',
        'DBGroup'  => 'default',
    ];
}
