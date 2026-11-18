# Cloud Tasks Queue Driver for Laravel

[![tests](https://github.com/firevel/cloud-tasks-queue-driver/actions/workflows/tests.yml/badge.svg)](https://github.com/firevel/cloud-tasks-queue-driver/actions/workflows/tests.yml)

A Laravel queue driver for [Google Cloud Tasks](https://cloud.google.com/tasks), enabling serverless job processing for applications running on Google App Engine or Google Cloud Run.

Compatible with both [Firevel](https://github.com/firevel/firevel) and standard Laravel applications.

## Requirements

- PHP 8.0+ (tested on 8.2, 8.3, and 8.4)
- Laravel 8.x – 12.x (tested against 11.x and 12.x)
- Google Cloud Project with Cloud Tasks API enabled

## Installation

```bash
composer require firevel/cloud-tasks-queue-driver
```

The package auto-discovers and registers itself via Laravel's package discovery.

## Configuration

Add the connection to your `config/queue.php`:

```php
'connections' => [
    'cloudtasks' => [
        'driver' => 'cloudtasks',
        'project' => env('GOOGLE_CLOUD_PROJECT'),
        'location' => env('CLOUD_TASKS_LOCATION', 'us-central1'),
        'queue_name' => env('CLOUD_TASKS_QUEUE', 'default'),
        'route' => env('CLOUD_TASKS_ROUTE', '/_cloudtasks'),
        // Defaults for jobs without $tries / $backoff (optional)
        'tries' => 3,
        'backoff' => 60,
        // App Engine specific (optional)
        'service' => env('GAE_SERVICE'),
        'version' => env('GAE_VERSION'),
        // Cloud Run specific (optional)
        'url' => env('CLOUD_TASKS_URL'),
    ],
],
```

Set your default queue connection in `.env`:

```env
QUEUE_CONNECTION=cloudtasks
CLOUD_TASKS_LOCATION=us-central1
```

> **Note:** The `location` must match your App Engine or Cloud Run region.

### Configuration Options

| Option | Description |
|--------|-------------|
| `project` | Google Cloud project ID |
| `location` | Cloud Tasks queue location (must match your compute region) |
| `queue_name` | Default queue name |
| `route` | HTTP endpoint path for task callbacks |
| `tries` | Attempts a job gets when it does not define `$tries` (default `3`) |
| `backoff` | Seconds before a job that threw is attempted again, when it does not define `$backoff` (default `60`) |
| `service` | App Engine service name (falls back to the `GAE_SERVICE` env variable when not set) |
| `version` | App Engine version to route tasks to (when not set, tasks go to the service's default version) |
| `url` | Custom URL for Cloud Run or when behind a proxy/load balancer |

## Cloud Tasks Setup

Create a queue using `gcloud`:

```bash
gcloud tasks queues create default
```

Or via `queue.yaml`:

```yaml
queue:
- name: default
  rate: 500/s
```

See the [Cloud Tasks documentation](https://cloud.google.com/tasks/docs/queue-yaml) for advanced queue configuration.

## Usage

Use Laravel's standard queue API:

```php
// Dispatch a job
dispatch(new ProcessOrder($order));

// Dispatch with delay
dispatch(new ProcessOrder($order))->delay(now()->addMinutes(5));

// Dispatch to a specific queue
dispatch(new ProcessOrder($order))->onQueue('orders');
```

> **Note:** Queue names map directly to Cloud Tasks queues, so `onQueue('orders')` requires a Cloud Tasks queue named `orders` to exist.

## How It Works

Unlike traditional queue drivers, there is no `queue:work` process. Cloud Tasks delivers each job back to your application over HTTP:

1. Dispatching a job creates a Cloud Task with the serialized job as its payload, signed with an HMAC-SHA256 signature derived from your `APP_KEY`.
2. Cloud Tasks sends the payload via HTTP POST to the handler route (`/_cloudtasks` by default), which the package registers automatically.
3. The handler verifies the signature, rejects unauthenticated requests with a 403, and processes valid jobs through Laravel's queue worker.

> **Note:** Since job payloads are signed with `APP_KEY`, rotating the key will invalidate tasks that were queued before the rotation.

## Retries, Attempts and Overlapping

Laravel owns the retries of a job that throws, exactly like under `queue:work`; the Cloud Tasks retry policy only covers deliveries that never reached a healthy worker.

- **A job that throws is released**: the worker creates a fresh Cloud Task with the same payload (the attempt count travels in the payload, since a new task starts with a retry count of 0) scheduled after the job's `backoff()` / `$backoff`, or the connection's `backoff` when the job defines none. The handler then responds with **200**, so Cloud Tasks does *not* redeliver the original task as well — one failed attempt is one retry, never two.
- **Attempts are capped** by the job's `$tries` (or `retryUntil()`), falling back to the connection's `tries` (default 3). A job on its last attempt is marked as failed, dispatched as `JobFailed`, stored with the configured failed job provider (`queue.failed`, e.g. the `failed_jobs` table) and answered with 200. `$job->attempts()` is 1-based (1 on the first attempt).
- **Cloud Tasks retries only what Laravel could not handle**: when creating the released task fails, or the process dies (timeout, out of memory), the request ends with an error status and the queue's retry policy (`maxAttempts`, backoff) redelivers the original task. Keep that policy finite.
- **Releasing** (`$this->release($delay)`) uses the same mechanism. This also makes the [`WithoutOverlapping`](https://laravel.com/docs/queues#preventing-job-overlaps) job middleware work as expected — overlapping jobs are pushed back instead of lost. It requires a cache store that supports atomic locks.
- **Unique jobs** (`ShouldBeUnique`) work out of the box; the unique lock is handled by Laravel at dispatch time and also requires a lock-capable cache store.
- To process an entire queue serially (one task at a time), set `maxConcurrentDispatches=1` on the Cloud Tasks queue — no application code needed.

> **Why not let Cloud Tasks retry everything?** Earlier versions of the handler both released the job *and* returned 500, so every failed attempt produced two tasks. A job that could never succeed (a blocked search index, a deleted model) grew exponentially until the queue had hundreds of thousands of copies and the service was saturated.

## Routing Behavior

- **App Engine:** Tasks are routed to the specific service and version that dispatched them, ensuring version consistency during deployments.
- **Cloud Run:** Tasks are routed to the currently promoted revision.

## Testing

The test suite uses [Orchestra Testbench](https://github.com/orchestral/testbench) and runs without any Google Cloud credentials (the Cloud Tasks client is mocked).

```bash
composer install
composer test
```

Tests run automatically via GitHub Actions on PHP 8.2, 8.3, and 8.4 for every push and pull request.

## License

MIT
