<?php

namespace App\Domains\Feed\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchFeedsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
