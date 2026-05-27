<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Domains\Settings\Support\LocaleDetector;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'locale' => ['required', 'string', Rule::in(LocaleDetector::available()->all())],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
