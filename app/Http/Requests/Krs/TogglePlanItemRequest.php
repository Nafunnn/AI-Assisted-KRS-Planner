<?php

namespace App\Http\Requests\Krs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TogglePlanItemRequest extends FormRequest
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
            'course_section_id' => ['required', 'integer', Rule::exists('course_sections', 'id')],
            'action' => ['required', Rule::in(['add', 'remove'])],
        ];
    }
}
