<?php

namespace Firevel\CloudTasksQueueDriver;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class CloudTasksJob extends Job implements JobContract
{
    /**
     * Job array.
     *
     * @var arrayt
     */
    private $job;

    /**
     * Job headers.
     *
     * @var array
     */
    private $headers;

    /**
     * @param  Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = Container::getInstance();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job['uuid'];
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return json_encode($this->job);
    }

    /**
     * Set job array.
     *
     * @param  array  $job
     * @return self
     */
    public function setJob($job)
    {
        $this->job = $job;

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param  array  $headers
     * @return self
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Get header by name.
     *
     * @param  string  $name
     * @param  mixed  $default
     * @return string
     */
    public function header($name, $default = null)
    {
        $value = $this->headers[strtolower($name)] ?? $default;

        if (is_array($value)) {
            return $value[0] ?? $default;
        }

        return $value;
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * Cloud Tasks reports the number of retries so far via the
     * X-CloudTasks-TaskRetryCount (HTTP targets) or X-AppEngine-TaskRetryCount
     * (App Engine targets) header. Attempts made before the job was last
     * released are carried in the payload, since releasing creates a fresh
     * task with a reset retry counter.
     *
     * @return int
     */
    public function attempts()
    {
        $retries = (int) $this->header(
            'X-CloudTasks-TaskRetryCount',
            $this->header('X-AppEngine-TaskRetryCount', 0)
        );

        return $retries + (int) ($this->job['attempts'] ?? 0) + 1;
    }

    /**
     * Set the name of the queue connection the job belongs to.
     *
     * @param  string  $name
     * @return self
     */
    public function setConnectionName($name)
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * Get the name of the Cloud Tasks queue the job came from.
     *
     * @return string|null
     */
    public function getQueue()
    {
        return $this->queue
            ?: $this->header('X-CloudTasks-QueueName', $this->header('X-AppEngine-QueueName'));
    }

    /**
     * Release the job back onto the queue after (n) seconds.
     *
     * Cloud Tasks has no native release; a new task is created with the same
     * payload and the attempts made so far stamped into it.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $payload = $this->job;
        $payload['attempts'] = $this->attempts();

        $this->container->make('queue')
            ->connection($this->getConnectionName())
            ->pushRaw(json_encode($payload), $this->getQueue(), ['delay' => $delay]);
    }
}
