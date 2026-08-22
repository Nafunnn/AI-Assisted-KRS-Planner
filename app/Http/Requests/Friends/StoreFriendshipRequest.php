<?php

namespace App\Http\Requests\Friends;

use Illuminate\Foundation\Http\FormRequest;

class StoreFriendshipRequest extends FormRequest
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
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'Pengguna dengan email tersebut tidak ditemukan.',
        ];
    }
}
