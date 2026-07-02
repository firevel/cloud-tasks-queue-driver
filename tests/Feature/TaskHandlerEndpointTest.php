<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Feature;

use Firevel\CloudTasksQueueDriver\Services\SignatureService;
use Firevel\CloudTasksQueueDriver\Tests\Fixtures\TestJob;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;
use Illuminate\Support\Str;

class TaskHandlerEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestJob::$handled = false;
    }

    public function test_request_without_signature_is_rejected()
    {
        $response = $this->postTask($this->jobPayload(), null);

        $response->assertForbidden();
        $this->assertFalse(TestJob::$handled);
    }

    public function test_request_with_invalid_signature_is_rejected()
    {
        $response = $this->postTask($this->jobPayload(), 'invalid-signature');

        $response->assertForbidden();
        $this->assertFalse(TestJob::$handled);
    }

    public function test_request_with_valid_signature_processes_the_job()
    {
        $payload = $this->jobPayload();

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk();
        $this->assertTrue(TestJob::$handled);
    }

    /**
     * Post a task payload to the handler route.
     *
     * @param  string  $payload
     * @param  string|null  $signature
     * @return \Illuminate\Testing\TestResponse
     */
    private function postTask($payload, $signature)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_X-SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/_cloudtasks', [], [], [], $server, $payload);
    }

    /**
     * Build a queue payload for the test job.
     *
     * @return string
     */
    private function jobPayload()
    {
        $job = new TestJob;

        return json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => TestJob::class,
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'retryUntil' => null,
            'data' => [
                'commandName' => TestJob::class,
                'command' => serialize($job),
            ],
        ]);
    }
}
