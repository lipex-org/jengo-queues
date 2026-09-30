<?php

declare(strict_types=1);

namespace Jengo\Queues\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class QueueInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'queue';
    }

    public static function description(): string
    {
        return 'Publish Jengo Queues configuration (app/Config/Queue.php)';
    }

    public static function reasonForSkipping(): string
    {
        return 'Queue configuration already published in app/Config/Queue.php.';
    }

    public function shouldRun(): bool
    {
        return !file_exists(APPPATH . 'Config/Queue.php');
    }

    public function install(): void
    {
        $this->addRun();

        $destConfig = APPPATH . 'Config/Queue.php';
        if (!file_exists($destConfig)) {
            $source = __DIR__ . '/../Config/Queue.php';
            $content = (string) file_get_contents($source);
            $content = str_replace(
                "namespace Jengo\\Queues\\Config;\n\nuse CodeIgniter\\Config\\BaseConfig;",
                "namespace Config;\n\nuse Jengo\\Queues\\Config\\Queue as BaseQueue;",
                $content
            );
            $content = str_replace(
                "class Queue extends BaseConfig",
                "class Queue extends BaseQueue",
                $content
            );

            $this->writeFile($destConfig, $content);
            CLI::write('Published Config/Queue.php successfully.', 'green');
        }
    }
}
