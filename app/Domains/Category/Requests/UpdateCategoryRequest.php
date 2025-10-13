<?php

namespace App\Domains\Category\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,'.$this->route('id')],
            'description' => ['nullable', 'string'],
        ];
    }
}
