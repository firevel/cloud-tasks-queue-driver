<?php

namespace Firevel\CloudTasksQueueDriver\Services;

class SignatureService
{
	/**
	 * Hmac hashing algorithm
	 */
	const ALGO = 'sha256';

	/**
	 * Generate hmac signature.
	 *
	 * @param object|array $data
	 * @return string
	 */
    public static function sign($data)
    {
    	if (! is_string($data)) {
			$data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    	}

        return hash_hmac(
            self::ALGO,
            $data,
            config('app.key')
        );
    }

    /**
     * Verify if data match signature.
     *
     * @param object|array|string $data 
     * @param string $signature
     * @return bool
     */
    public static function verify($data, $signature)
    {
    	if (! is_string($data)) {
			$data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    	}

        if (hash_equals(hash_hmac(self::ALGO, $data, config('app.key')), $signature)) {
            return true;
        }

        return false;
    }
}