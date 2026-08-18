<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SendAiChatRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'uuid'],
            'plan_id' => ['nullable', 'integer', 'exists:krs_plans,id'],
            'offering_id' => ['nullable', 'integer', 'exists:course_offerings,id'],
        ];
    }
}
