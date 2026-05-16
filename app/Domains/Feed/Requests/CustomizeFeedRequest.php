<?php

namespace App\Domains\Feed\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CustomizeFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disable_full_article_scraping' => ['required', 'boolean'],
            'hide_image_in_detail' => ['required', 'boolean'],
        ];
    }
}
