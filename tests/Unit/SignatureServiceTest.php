<?php

namespace Firevel\CloudTasksQueueDriver\Tests\Unit;

use Firevel\CloudTasksQueueDriver\Services\SignatureService;
use Firevel\CloudTasksQueueDriver\Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    public function test_sign_returns_hmac_of_payload_using_app_key()
    {
        $payload = '{"foo":"bar"}';

        $expected = hash_hmac('sha256', $payload, config('app.key'));

        $this->assertSame($expected, SignatureService::sign($payload));
    }

    public function test_verify_accepts_valid_signature()
    {
        $payload = '{"foo":"bar"}';

        $signature = SignatureService::sign($payload);

        $this->assertTrue(SignatureService::verify($payload, $signature));
    }

    public function test_verify_rejects_tampered_payload()
    {
        $signature = SignatureService::sign('{"foo":"bar"}');

        $this->assertFalse(SignatureService::verify('{"foo":"baz"}', $signature));
    }

    public function test_verify_rejects_invalid_signature()
    {
        $this->assertFalse(SignatureService::verify('{"foo":"bar"}', 'not-a-valid-signature'));
    }

    public function test_non_string_data_is_json_encoded_before_signing()
    {
        $data = ['foo' => 'bar', 'url' => 'https://example.com/path'];

        $signature = SignatureService::sign($data);

        $this->assertSame(
            SignatureService::sign(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $signature
        );
        $this->assertTrue(SignatureService::verify($data, $signature));
    }
}
