<?php

namespace App\Http\Requests\Krs;

use App\Models\CourseOffering;
use Illuminate\Foundation\Http\FormRequest;

class StoreKrsPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offering = $this->route('offering');

        return $offering instanceof CourseOffering
            && $this->user()?->can('view', $offering);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
