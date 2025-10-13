<?php

namespace App\Domains\News\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'feed_id' => ['required', 'exists:feeds,id'],
            'title' => ['required', 'string', 'max:255'],
            'link' => ['required', 'string', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'author' => ['nullable', 'string', 'max:255'],
            'guid' => ['nullable', 'string', 'max:255'],
        ];
    }
}
