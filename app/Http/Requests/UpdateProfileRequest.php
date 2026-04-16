<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:200',
            'username' => 'sometimes|string|max:120|unique:users,username,'.$this->user()->id,
            'password' => ['sometimes', 'string', 'min:8', Password::min(8)->letters()->numbers()->mixedCase()],
        ];
    }
}
