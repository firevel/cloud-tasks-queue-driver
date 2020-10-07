# Cloud Tasks queue driver

Cloud Tasks queue driver for Laravel apps running inside Google App Engine.

## Configuration.

Add to your `config/queue.php`:
```
        'cloudtasks' => [
            'driver' => 'cloudtasks',
            'route' => env('CLOUD_TASKS_ROUTE', '/_cloudtasks'),
            'project' => env('GOOGLE_CLOUD_PROJECT'),
            'location' => env('CLOUD_TASKS_LOCATION', 'us-central1'), // Location must match your App Engine project location.
            'queue_name' => env('CLOUD_TASKS_QUEUE', 'default'),
        ],
```

Set `QUEUE_CONNECTION` to `cloudtasks`, and update `CLOUD_TASKS_LOCATION` in your `.env` file.
