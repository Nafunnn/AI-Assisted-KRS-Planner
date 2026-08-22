<?php

namespace App\Http\Requests\Krs;

use App\Models\KrsPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompareKrsPlansRequest extends FormRequest
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
            'plan_a' => ['required', 'integer', 'exists:krs_plans,id', 'different:plan_b'],
            'plan_b' => ['required', 'integer', 'exists:krs_plans,id', 'different:plan_a'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_a.required' => 'Rencana pertama wajib dipilih.',
            'plan_b.required' => 'Rencana kedua wajib dipilih.',
            'plan_a.different' => 'Pilih dua rencana yang berbeda.',
            'plan_b.different' => 'Pilih dua rencana yang berbeda.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $offeringA = KrsPlan::query()->whereKey($this->integer('plan_a'))->value('course_offering_id');
            $offeringB = KrsPlan::query()->whereKey($this->integer('plan_b'))->value('course_offering_id');

            if ($offeringA !== null && $offeringB !== null && $offeringA !== $offeringB) {
                $validator->errors()->add(
                    'plan_b',
                    'Kedua rencana harus dari katalog semester yang sama.',
                );
            }
        });
    }
}
