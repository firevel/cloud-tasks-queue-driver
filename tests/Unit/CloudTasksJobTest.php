<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Unit;

use Firevel\CloudTasksQueueDriver\CloudTasksJob;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;

class CloudTasksJobTest extends TestCase
{
    public function test_job_id_is_read_from_payload_uuid()
    {
        $job = $this->makeJob(['uuid' => 'test-uuid-123']);

        $this->assertSame('test-uuid-123', $job->getJobId());
    }

    public function test_raw_body_is_json_encoded_payload()
    {
        $payload = ['uuid' => 'test-uuid-123', 'displayName' => 'ExampleJob'];

        $job = $this->makeJob($payload);

        $this->assertSame(json_encode($payload), $job->getRawBody());
    }

    public function test_header_lookup_is_case_insensitive()
    {
        $job = $this->makeJob([], ['x-custom-header' => 'value']);

        $this->assertSame('value', $job->header('X-Custom-Header'));
    }

    public function test_header_returns_default_when_missing()
    {
        $job = $this->makeJob([], []);

        $this->assertSame('fallback', $job->header('X-Missing', 'fallback'));
    }

    public function test_attempts_are_read_from_retry_count_header()
    {
        $job = $this->makeJob([], ['x-appengine-taskretrycount' => '3']);

        $this->assertSame(3, $job->attempts());
    }

    public function test_attempts_default_to_zero()
    {
        $job = $this->makeJob([], []);

        $this->assertSame(0, $job->attempts());
    }

    /**
     * Create a job instance with the given payload and headers.
     *
     * @param  array  $payload
     * @param  array  $headers
     * @return CloudTasksJob
     */
    private function makeJob(array $payload = [], array $headers = [])
    {
        return app(CloudTasksJob::class)
            ->setJob($payload)
            ->setHeaders($headers);
    }
}
