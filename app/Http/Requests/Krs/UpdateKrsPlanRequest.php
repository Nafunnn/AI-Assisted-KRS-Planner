<?php

namespace App\Http\Requests\Krs;

use App\Models\KrsPlan;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKrsPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof KrsPlan
            && $this->user()?->can('update', $plan);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama rencana wajib diisi.',
        ];
    }
}
