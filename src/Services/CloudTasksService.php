<?php

namespace Firevel\CloudTasksQueueDriver\Services;

use Firevel\CloudTasksQueueDriver\CloudTasksJob;
use Firevel\CloudTasksQueueDriver\Http\Requests\CloudTasksRequest;
use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Google\Cloud\Tasks\V2\AppEngineRouting;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Illuminate\Support\InteractsWithTime;

class CloudTasksService
{
    use InteractsWithTime;

    /**
     * Algorithm used to generate signature.
     *
     * @var string
     */
    protected $algorithm = 'sha256';

    /**
     * @var CloudTasksClient
     */
    private $client;

    /**
     * Configuration.
     *
     * @var array
     */
    private $config;

    /**
     * Constructor.
     *
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create job from Cloud Task request.
     *
     * @return CloudTasksJob
     */
    public static function makeJobFromRequest(CloudTasksRequest $request)
    {
        return app(CloudTasksJob::class)
            ->setJob(json_decode($request->getContent(), true))
            ->setHeaders($request->header());
    }

    /**
     * Get fully-qualified path to represent a location resource.
     *
     * @return string
     */
    public function getLocationName()
    {
        return $this
            ->getClient()
            ->locationName($this->getConfig('project'), $this->getConfig('location'));
    }

    /**
     * Get client.
     *
     * @return Google\Cloud\Tasks\V2\CloudTasksClient
     */
    protected function getClient()
    {
        if (empty($this->client)) {
            $this->client = app(CloudTasksClient::class);
        }

        return $this->client;
    }

    /**
     * Get queues list.
     *
     * @return array
     */
    public function listQueues()
    {
        return $this->getClient()->listQueues($this->getLocationName());
    }

    /**
     * Create App Engine http request used in task.
     *
     * @return AppEngineHttpRequest
     */
    public function createAppEngineHttpRequest($service, $route, $payload, $method = null)
    {
        $httpRequest = new AppEngineHttpRequest();
        $httpRequest->setRelativeUri($route);
        $httpRequest->setHttpMethod($method ?? HttpMethod::POST);
        $httpRequest->setBody($payload);
        $httpRequest->setHeaders(['x-signature' => SignatureService::sign($payload)]);

        $routing = new AppEngineRouting();

        if (! empty($service)) {
            $routing->setService($service);
        }

        if (env('GAE_VERSION')) {
            $routing->setVersion(env('GAE_VERSION'));
        }

        $httpRequest->setAppEngineRouting($routing);

        return $httpRequest;
    }

    /**
     * Create http request used in task.
     *
     * @return HttpRequest
     */
    public function createHttpRequest($url, $route, $payload, $method = null)
    {
        $httpRequest = new HttpRequest();
        $httpRequest->setUrl($url.$route);
        $httpRequest->setHttpMethod($method ?? HttpMethod::POST);
        $httpRequest->setBody($payload);
        $httpRequest->setHeaders(['x-signature' => SignatureService::sign($payload)]);

        return $httpRequest;
    }

    /**
     * Push task to queue.
     *
     * @param  string  $queue  Queue name.
     * @param  string  $payload
     * @param  int  $delay
     * @param  int  $attempts
     * @return bool
     */
    public function pushTaskToQueue($queue, $payload, $delay = 0, $attempts = 0)
    {
        $queueName = $this->getClient()->queueName($this->getConfig('project'), $this->getConfig('location'), $queue);
        $service = $this->getConfig('service') ?? env('GAE_SERVICE');

        $task = app(Task::class);

        // Google App Engine.
        if ($service) {
            $task->setAppEngineHttpRequest(
                $this->createAppEngineHttpRequest($service, $this->getConfig('route'), $payload)
            );
        } else {
            $url = $this->getConfig('url') ?? 'https://'.$_SERVER['HTTP_HOST'];
            $task->setHttpRequest(
                $this->createHttpRequest($url, $this->getConfig('route'), $payload)
            );
        }

        $availableAt = $this->availableAt($delay);
        if ($availableAt > time()) {
            $task->setScheduleTime(new Timestamp(['seconds' => $availableAt]));
        }

        $this->getClient()->createTask($queueName, $task);
    }

    /**
     * Get config.
     *
     * @param  string  $key
     * @return array|null
     */
    protected function getConfig($key = null)
    {
        if (empty($key)) {
            return $this->config;
        }

        return $this->config[$key];
    }
}
