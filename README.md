# Cloud Tasks Queue Driver for Laravel

A Laravel queue driver for [Google Cloud Tasks](https://cloud.google.com/tasks), enabling serverless job processing for applications running on Google App Engine or Google Cloud Run.

Compatible with both [Firevel](https://github.com/firevel/firevel) and standard Laravel applications.

## Requirements

- PHP 7.4+
- Laravel 8.x / 9.x / 10.x / 11.x / 12.x
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
| `service` | App Engine service name (auto-detected from `GAE_SERVICE`) |
| `version` | App Engine version (auto-detected from `GAE_VERSION`) |
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

## Routing Behavior

- **App Engine:** Tasks are routed to the specific service and version that dispatched them, ensuring version consistency during deployments.
- **Cloud Run:** Tasks are routed to the currently promoted revision.

## License

MIT
