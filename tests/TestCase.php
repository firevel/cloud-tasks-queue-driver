<?php

namespace Firevel\CloudTasksQueueDriver\Tests;

use Firevel\CloudTasksQueueDriver\CloudTasksServiceProvider;
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
        $app['config']->set('queue.connections.cloudtasks', [
            'driver' => 'cloudtasks',
            'route' => '/_cloudtasks',
            'project' => 'test-project',
            'location' => 'us-central1',
            'queue_name' => 'default',
            'url' => 'https://example.com',
        ]);
    }
}
