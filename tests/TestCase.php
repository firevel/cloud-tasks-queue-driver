<?php

namespace Firevel\CloudTasksQueueDriver\Tests;

use Firevel\CloudTasksQueueDriver\CloudTasksServiceProvider;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [CloudTasksServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('queue.default', 'cloudtasks');
        $app['config']->set('queue.failed.driver', null);
        $app['config']->set('queue.connections.cloudtasks', [
            'driver' => 'cloudtasks',
            'route' => '/_cloudtasks',
            'project' => 'test-project',
            'location' => 'us-central1',
            'queue_name' => 'default',
            'url' => 'https://example.com',
        ]);
    }

    /**
     * Post a task payload to the handler route.
     *
     * @param  string  $payload
     * @param  string|null  $signature
     * @param  array  $headers
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postTask($payload, $signature, array $headers = [])
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_X-SIGNATURE'] = $signature;
        }

        foreach ($headers as $name => $value) {
            $server['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
        }

        return $this->call('POST', '/_cloudtasks', [], [], [], $server, $payload);
    }

    /**
     * Build a queue payload for the given job instance.
     *
     * @param  object  $job
     * @return string
     */
    protected function jobPayload($job)
    {
        return json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => get_class($job),
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => $job->tries ?? null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'retryUntil' => null,
            'data' => [
                'commandName' => get_class($job),
                'command' => serialize($job),
            ],
        ]);
    }
}
