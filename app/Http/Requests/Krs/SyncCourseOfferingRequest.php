<?php

namespace App\Http\Requests\Krs;

use Illuminate\Foundation\Http\FormRequest;

class SyncCourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offering = $this->route('offering');

        return $this->user()?->can('sync', $offering) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File penawaran mata kuliah wajib diunggah.',
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls).',
        ];
    }
}
