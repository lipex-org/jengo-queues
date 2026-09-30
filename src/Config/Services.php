<?php

declare(strict_types=1);

namespace Jengo\Queues\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Queues\Config\Queue as QueueConfig;
use Jengo\Queues\Contracts\FailedJobProviderInterface;
use Jengo\Queues\Support\DatabaseFailedJobProvider;
use Jengo\Queues\Support\FailedJobManager;
use Jengo\Queues\Support\QueueManager;
use Jengo\Queues\Support\Worker;

class Services extends BaseService
{
    public static function queues(?QueueConfig $config = null, bool $getShared = true): QueueManager
    {
        if ($getShared) {
            return static::getSharedInstance('queues', $config);
        }

        $config ??= config(QueueConfig::class) ?? new QueueConfig();

        return new QueueManager($config);
    }

    public static function queueWorker(?QueueManager $manager = null, ?FailedJobManager $failedJobs = null, bool $getShared = true): Worker
    {
        if ($getShared) {
            return static::getSharedInstance('queueWorker', $manager, $failedJobs);
        }

        $manager ??= static::queues();
        $failedJobs ??= static::queueFailedJobs();

        return new Worker($manager, $failedJobs);
    }

    public static function queueFailedJobs(?FailedJobProviderInterface $provider = null, bool $getShared = true): FailedJobManager
    {
        if ($getShared) {
            return static::getSharedInstance('queueFailedJobs', $provider);
        }

        $config = config(QueueConfig::class) ?? new QueueConfig();
        $provider ??= new DatabaseFailedJobProvider($config->failed);

        $manager = new FailedJobManager($provider);
        $manager->setQueueManager(static::queues());

        return $manager;
    }
}
