<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Feature;

use Firevel\CloudTasksQueueDriver\Services\SignatureService;
use Firevel\CloudTasksQueueDriver\Tests\Fixtures\TestJob;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;

class TaskHandlerEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestJob::$handled = false;
    }

    public function test_request_without_signature_is_rejected()
    {
        $response = $this->postTask($this->jobPayload(new TestJob), null);

        $response->assertForbidden();
        $this->assertFalse(TestJob::$handled);
    }

    public function test_request_with_invalid_signature_is_rejected()
    {
        $response = $this->postTask($this->jobPayload(new TestJob), 'invalid-signature');

        $response->assertForbidden();
        $this->assertFalse(TestJob::$handled);
    }

    public function test_request_with_valid_signature_processes_the_job()
    {
        $payload = $this->jobPayload(new TestJob);

        $response = $this->postTask($payload, SignatureService::sign($payload));

        $response->assertOk();
        $this->assertTrue(TestJob::$handled);
    }
}
