<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/docs/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo Queues</h1>

<p align="center">
  <strong>Lightweight, high-throughput asynchronous background job queue engine with Redis, Database, and Sync drivers for CodeIgniter 4.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/queues"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/queues/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/queues/issues"><strong>Issues</strong></a>
</p>

---

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
