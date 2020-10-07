<?php

namespace Firevel\CloudTasksQueueDriver\Http\Requests;

use Firevel\CloudTasksQueueDriver\Services\SignatureService;
use Illuminate\Foundation\Http\FormRequest;

class CloudTasksRequest extends FormRequest
{
    /**
     * Validate signature.
     *
     * @return bool
     */
    public function authorize()
    {
        $signature = $this->header('x-signature');

        if (empty($signature)) {
            return false;
        }

        if (SignatureService::verify($this->getContent(), $signature)) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
