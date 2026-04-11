<?php

namespace App\Domains\Settings\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'news_retention_enabled' => ['sometimes', 'boolean'],
            'news_retention_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'scrape_full_body' => ['sometimes', 'boolean'],
        ];
    }
}
