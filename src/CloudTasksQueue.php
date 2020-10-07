<?php

namespace Firevel\CloudTasksQueueDriver;

use Firevel\CloudTasksQueueDriver\Services\CloudTasksService;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue as LaravelQueue;
use Illuminate\Support\InteractsWithTime;

class CloudTasksQueue extends LaravelQueue implements QueueContract
{
    use InteractsWithTime;

    /**
     * Default queue name.
     *
     * @var string
     */
    private $default;

    /**
     * Configuration.
     *
     * @var array
     */
    private $config;

    /**
     * @var CloudTasksService
     */
    private $tasksService;

    /**
     * Constructor.
     *
     * @param array $config
     * @param CloudTasksClient $client
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->default = $config['queue_name'];
    }

    /**
     * Get tasks service.
     *
     * @return CloudTasksService
     */
    public function getTasksService()
    {
        if (empty($this->tasksService)) {
            $this->tasksService = new CloudTasksService($this->getConfig());
        }

        return $this->tasksService;
    }

    /**
     * Get the size of the queue.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null)
    {
        // Currently not supported.
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->getTasksService()->pushTaskToQueue(
            $this->getQueue($queue),
            $this->createPayload($job, $this->getQueue($queue), $data)
        );
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->getTasksService()->pushTaskToQueue($queue, $payload);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushToCloudTasks(
            $queue,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $delay
        );
    }

    public function pop($queue = null)
    {
        // TODO: Implement pop() method.
    }

    /**
     * Get queue name.
     *
     * @param string $queue
     * @return string
     */
    private function getQueue($queue = null)
    {
        return $queue ?: $this->default;
    }

    /**
     * Get configuration.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
