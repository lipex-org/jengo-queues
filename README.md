# Jengo Queues

An asynchronous job queue and worker management subsystem for CodeIgniter 4 and the Jengo ecosystem. Supports **Redis**, **Database (MySQL/PostgreSQL/SQLite)**, **Sync**, and **Null** drivers with support for delayed jobs, exponential backoff, worker daemons, and failed job handling.

Documentation: https://lipex-org.github.io/jengophp.com/packages/queues

---

## Installation

```bash
composer require jengo/queues
php spark jengo:install queue
php spark migrate --all
```

---

## Quick Example

```php
use App\Jobs\SendWelcomeEmail;
use Jengo\Queues\Facades\Queue;

// Dispatch job immediately to the default queue
dispatch(new SendWelcomeEmail($user));

// Dispatch with delay and queue routing
(new SendWelcomeEmail($user))
    ->onQueue('emails')
    ->delay(60)
    ->tries(3)
    ->backoff(10)
    ->dispatch();
```

---

## Documentation

For complete configuration options, worker supervision guides, failed job retries, and testing utilities, visit the official documentation at https://lipex-org.github.io/jengophp.com/packages/queues.

---

## License

Released under the [MIT License](LICENSE).
