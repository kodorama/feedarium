<?php

namespace App\Domains\News\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchNewsRequest extends FormRequest
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
