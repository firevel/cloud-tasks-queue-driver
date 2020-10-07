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
     * @param Container $container
     *
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
     * @param array $job
     *
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
     * @param array $headers
     *
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
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function header($name, $default = null)
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->header('X-AppEngine-TaskRetryCount', 0);
    }
}
