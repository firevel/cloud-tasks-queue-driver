# Cloud Tasks queue driver

Cloud Tasks queue driver for Laravel apps running inside Google App Engine or Google Cloud Run. Driver is compatible with Firevel and Laravel.

## Configuration

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

## Cloud Tasks setup

Create queue using `queue.yaml` or `gcloud` ([read more](https://cloud.google.com/tasks/docs/queue-yaml)).

Example `queue.yaml`:
```
queue:
- name: default
  rate: 500/s
```

Example `gcloud` command:
```
gcloud tasks queues create default
```

## Routing

Inside App Engine routing matching service and version, so your task will always match version it was dispatched from. Inside Cloud Run it will always be handled by promoted version.
