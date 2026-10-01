<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Queues\Drivers\DatabaseQueueDriver;
use Jengo\Queues\Jobs\DatabaseJob;
use Jengo\Queues\Support\DatabaseFailedJobProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Jobs\DbTestJob;

class DatabaseQueueDriverTest extends CIUnitTestCase
{
    protected $migrate = false;
    protected $DBGroup = 'tests';

    protected function setUp(): void
    {
        parent::setUp();
        DbTestJob::$handledCount = 0;

        $forge = \Config\Database::forge('tests');

        // Setup in-memory sqlite tables
        $forge->dropTable('queue_jobs', true);
        $forge->dropTable('queue_failed_jobs', true);

        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true],
            'queue'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'default'],
            'payload'      => ['type' => 'TEXT'],
            'attempts'     => ['type' => 'INTEGER', 'default' => 0],
            'reserved_at'  => ['type' => 'INTEGER', 'null' => true],
            'available_at' => ['type' => 'INTEGER'],
            'created_at'   => ['type' => 'INTEGER'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('queue_jobs', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'connection' => ['type' => 'VARCHAR', 'constraint' => 255],
            'queue'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'payload'    => ['type' => 'TEXT'],
            'exception'  => ['type' => 'TEXT'],
            'failed_at'  => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('queue_failed_jobs', true);
    }

    #[Test]
    public function it_pushes_and_pops_database_job(): void
    {
        $db = \Config\Database::connect('tests');
        $driver = (new DatabaseQueueDriver(['DBGroup' => 'tests']))->setConnection($db);

        $job = new DbTestJob('database-test');
        $id = $driver->push($job, '', 'default');

        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $driver->size('default'));

        $popped = $driver->pop('default');
        $this->assertNotNull($popped, 'Database queue pop returned null');
        $this->assertInstanceOf(DatabaseJob::class, $popped);
        $this->assertSame(1, $popped->attempts());

        $popped->fire();
        $this->assertSame(1, DbTestJob::$handledCount);

        $popped->delete();
        $this->assertSame(0, $driver->size('default'));
    }

    #[Test]
    public function it_delays_and_releases_database_job(): void
    {
        $db = \Config\Database::connect('tests');
        $driver = (new DatabaseQueueDriver(['DBGroup' => 'tests']))->setConnection($db);

        $job = new DbTestJob('delayed-test');
        $driver->later(3600, $job, '', 'default');

        // Not available yet
        $this->assertNull($driver->pop('default'));
        $this->assertSame(1, $driver->size('default'));

        $driver->clear('default');
        $this->assertSame(0, $driver->size('default'));
    }

    #[Test]
    public function database_failed_job_provider_stores_and_queries(): void
    {
        $db = \Config\Database::connect('tests');
        $provider = (new DatabaseFailedJobProvider(['DBGroup' => 'tests']))->setConnection($db);

        $id = $provider->log('database', 'default', '{"foo":"bar"}', new RuntimeException('DB Error'));
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $provider->count());

        $failed = $provider->find($id);
        $this->assertNotNull($failed);
        $this->assertSame('database', $failed->connection);
        $this->assertSame('default', $failed->queue);

        $all = $provider->all();
        $this->assertCount(1, $all);

        $this->assertTrue($provider->forget($id));
        $this->assertSame(0, $provider->count());
    }
}
