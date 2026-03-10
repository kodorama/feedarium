<?php

namespace App\Domains\Feed\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebSubVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hub.mode' => ['required', 'string', 'in:subscribe,unsubscribe'],
            'hub.challenge' => ['required', 'string'],
            'hub.topic' => ['required', 'string', 'url'],
        ];
    }
}
