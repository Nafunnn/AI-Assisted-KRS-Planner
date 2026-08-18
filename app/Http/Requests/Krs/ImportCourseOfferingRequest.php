<?php

namespace App\Http\Requests\Krs;

use Illuminate\Foundation\Http\FormRequest;

class ImportCourseOfferingRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
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
