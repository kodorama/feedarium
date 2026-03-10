<?php

namespace App\Domains\Feed\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url', 'max:255', 'unique:feeds,url'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'hub_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
