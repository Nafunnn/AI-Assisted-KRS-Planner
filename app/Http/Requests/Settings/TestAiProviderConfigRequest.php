<?php

namespace App\Http\Requests\Settings;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestAiProviderConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}
